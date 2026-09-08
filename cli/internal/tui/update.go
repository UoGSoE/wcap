package tui

import (
	"errors"
	"fmt"
	"strconv"
	"time"

	tea "github.com/charmbracelet/bubbletea"
	"github.com/charmbracelet/huh"

	"github.com/wcap/cli/internal/api"
)

// ---------------------------------------------------------------------------
// Messages

type meLoadedMsg struct {
	user api.AuthUser
	err  error
}

type membersLoadedMsg struct {
	members []api.TeamMember
	err     error
}

type locationsLoadedMsg struct {
	locations []api.Location
	err       error
}

type planLoadedMsg struct {
	forUserID  int
	weekOffset int
	plan       *api.PlanResponse
	err        error
}

type savedMsg struct {
	err error
}

type filledMsg struct {
	result    *api.FillDefaultsResult
	singleDay bool
	err       error
}

// ---------------------------------------------------------------------------
// Commands

func loadMeCmd(c *api.Client) tea.Cmd {
	return func() tea.Msg {
		ctx, cancel := ctxBackground()
		defer cancel()
		u, err := c.Me(ctx)
		return meLoadedMsg{user: u, err: err}
	}
}

func loadMembersCmd(c *api.Client) tea.Cmd {
	return func() tea.Msg {
		ctx, cancel := ctxBackground()
		defer cancel()
		members, err := c.TeamMembers(ctx)
		return membersLoadedMsg{members: members, err: err}
	}
}

func loadLocationsCmd(c *api.Client) tea.Cmd {
	return func() tea.Msg {
		ctx, cancel := ctxBackground()
		defer cancel()
		locs, err := c.Locations(ctx)
		return locationsLoadedMsg{locations: locs, err: err}
	}
}

// loadPlanCmd fetches a plan. If targetUserID == 0 or == selfID, use the
// personal endpoint; otherwise use the manager endpoint.
func loadPlanCmd(c *api.Client, targetUserID, selfID, weekOffset int, now time.Time) tea.Cmd {
	from, to := windowBounds(now, weekOffset)
	useSelf := targetUserID == 0 || targetUserID == selfID
	return func() tea.Msg {
		ctx, cancel := ctxBackground()
		defer cancel()
		var (
			plan *api.PlanResponse
			err  error
		)
		if useSelf {
			plan, err = c.MyPlan(ctx, from, to)
		} else {
			plan, err = c.MemberPlan(ctx, targetUserID, from, to)
		}
		uid := targetUserID
		if useSelf {
			uid = selfID
		}
		return planLoadedMsg{
			forUserID:  uid,
			weekOffset: weekOffset,
			plan:       plan,
			err:        err,
		}
	}
}

func upsertCmd(c *api.Client, targetUserID, selfID int, entries []api.Entry) tea.Cmd {
	useSelf := targetUserID == 0 || targetUserID == selfID
	return func() tea.Msg {
		ctx, cancel := ctxBackground()
		defer cancel()
		var err error
		if useSelf {
			err = c.UpsertOwnPlan(ctx, entries)
		} else {
			err = c.UpsertMemberPlan(ctx, targetUserID, entries)
		}
		return savedMsg{err: err}
	}
}

func fillDefaultsCmd(c *api.Client, userID int, weekStart, onlyDate string) tea.Cmd {
	return func() tea.Msg {
		ctx, cancel := ctxBackground()
		defer cancel()
		result, err := c.FillDefaults(ctx, userID, weekStart, onlyDate)
		return filledMsg{result: result, singleDay: onlyDate != "", err: err}
	}
}

// ---------------------------------------------------------------------------
// Update

func (m Model) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
	var cmds []tea.Cmd

	switch msg := msg.(type) {
	case tea.WindowSizeMsg:
		m.width, m.height = msg.Width, msg.Height
		return m, nil

	case meLoadedMsg:
		if msg.err != nil {
			m.fatal("authentication failed: " + friendlyErr(msg.err) + "\n\nRun `wcap config` to set your token.")
			return m, nil
		}
		m.selfID = msg.user.ID
		m.selfName = msg.user.DisplayName()
		m.loadedMe = true
		m.maybeFinishLoading()
		return m, nil

	case membersLoadedMsg:
		if msg.err != nil {
			if errors.Is(msg.err, api.ErrForbidden) {
				m.canManage = false
				m.hideLeftPane = true
				m.loadedMembers = true
				m.maybeFinishLoading()
				return m, nil
			}
			m.fatal("loading team members: " + friendlyErr(msg.err))
			return m, nil
		}
		m.canManage = true
		m.members = msg.members
		m.loadedMembers = true
		// The list may arrive after loading has finished (maybeFinishLoading
		// no-ops then), so normalise it here too.
		m.refreshMembers()
		m.maybeFinishLoading()
		return m, nil

	case locationsLoadedMsg:
		if msg.err != nil {
			m.statusf(statusWarn, "couldn't load locations: %s", friendlyErr(msg.err))
		} else {
			m.locations = msg.locations
		}
		m.loadedLocations = true
		m.maybeFinishLoading()
		return m, nil

	case planLoadedMsg:
		if msg.err != nil {
			m.statusf(statusBad, "loading plan: %s", friendlyErr(msg.err))
			m.loadedPlan = true
			m.maybeFinishLoading()
			return m, nil
		}
		m.applyPlan(msg)
		m.loadedPlan = true
		m.maybeFinishLoading()
		return m, nil

	case savedMsg:
		if msg.err != nil {
			m.statusf(statusBad, "save failed: %s", friendlyErr(msg.err))
		} else {
			m.statusf(statusGood, "Saved.")
		}
		m.mode = modeList
		// Refetch so we have authoritative state.
		return m, loadPlanCmd(m.client, m.planUserID, m.selfID, m.weekOffset, time.Now())

	case filledMsg:
		if msg.err != nil {
			m.statusf(statusBad, "fill failed: %s", friendlyErr(msg.err))
			return m, nil
		}
		kind := statusGood
		if msg.result.FilledDays == 0 {
			kind = statusNormal
		}
		m.statusf(kind, "%s", fillStatus(msg.result, msg.singleDay))
		// Refetch so we have authoritative state.
		return m, loadPlanCmd(m.client, m.planUserID, m.selfID, m.weekOffset, time.Now())

	case tea.KeyMsg:
		if m.mode == modeFatal {
			if msg.String() == "q" || msg.String() == "ctrl+c" {
				return m, tea.Quit
			}
			return m, nil
		}
		if m.mode == modeLoading {
			if msg.String() == "q" || msg.String() == "ctrl+c" {
				return m, tea.Quit
			}
			return m, nil
		}
		if m.mode == modeEdit {
			return m.updateEdit(msg)
		}
		if m.mode == modeFilter {
			return m.updateFilter(msg)
		}
		if m.mode == modeHelp {
			m.mode = m.prevMode
			return m, nil
		}
		return m.updateList(msg)
	}

	// In edit mode we also have to feed non-Key messages to the form (e.g.
	// blink ticks, and huh's internal nextGroupMsg which flips the form to
	// StateCompleted after Enter on the last field).
	if m.mode == modeEdit && m.form != nil {
		f, cmd := m.form.Update(msg)
		if ff, ok := f.(*huh.Form); ok {
			m.form = ff
		}
		cmds = append(cmds, cmd)

		if finishCmd := m.maybeFinishEdit(); finishCmd != nil {
			cmds = append(cmds, finishCmd)
		}
	}

	return m, tea.Batch(cmds...)
}

// maybeFinishEdit checks whether the form has just transitioned to a
// terminal state. If so, it triggers the save (or returns to the list on
// abort). Returns the command to fire, or nil if the form is still active.
func (m *Model) maybeFinishEdit() tea.Cmd {
	if m.form == nil {
		return nil
	}
	switch m.form.State {
	case huh.StateCompleted:
		entry := m.entryFromForm()
		m.entries[m.editingDayIdx] = entry
		m.form = nil
		m.mode = modeList
		m.statusf(statusNormal, "Saving…")
		return upsertCmd(m.client, m.editingForUser, m.selfID, []api.Entry{entry})
	case huh.StateAborted:
		m.form = nil
		m.mode = modeList
		return nil
	}
	return nil
}

func (m *Model) maybeFinishLoading() {
	if m.mode != modeLoading {
		return
	}
	// We can render the right pane as soon as we have me + plan; members and
	// locations can lag behind without blocking the UI. But we don't have a
	// member to select before /user resolves either.
	if !m.loadedMe {
		return
	}

	m.refreshMembers()

	// If the plan response landed before /user, planUserID may be 0; tag it
	// as self so the right pane title is sensible.
	if m.planUserID == 0 {
		m.planUserID = m.selfID
	}

	if m.loadedPlan {
		m.mode = modeList
	}
}

func (m *Model) applyPlan(msg planLoadedMsg) {
	m.planUserID = msg.forUserID
	if msg.plan != nil {
		m.planUser = msg.plan.User
		m.dateRange = msg.plan.DateRange
	}
	m.weekOffset = msg.weekOffset
	m.days = weekdays(time.Now(), m.weekOffset)
	if msg.plan != nil {
		m.rebuildEntries(msg.plan.Entries)
	} else {
		m.rebuildEntries(nil)
	}
	if m.dayCursor >= len(m.days) {
		m.dayCursor = 0
	}
}

// ---------------------------------------------------------------------------
// List-mode key handling

func (m Model) updateList(msg tea.KeyMsg) (tea.Model, tea.Cmd) {
	key := msg.String()

	switch key {
	case "q", "ctrl+c":
		return m, tea.Quit

	case "?":
		m.prevMode = m.mode
		m.mode = modeHelp
		return m, nil

	case "tab", "shift+tab":
		if m.hideLeftPane {
			return m, nil
		}
		if m.pane == paneLeft {
			m.pane = paneRight
		} else {
			m.pane = paneLeft
		}
		return m, nil

	case "h", "left":
		if !m.hideLeftPane {
			m.pane = paneLeft
		}
		return m, nil

	case "l", "right":
		m.pane = paneRight
		return m, nil

	case "j", "down":
		m.moveCursor(+1)
		return m, nil

	case "k", "up":
		m.moveCursor(-1)
		return m, nil

	case "g", "home":
		m.setCursor(0)
		return m, nil

	case "G", "end":
		m.setCursor(-1) // last
		return m, nil

	case "/":
		if !m.hideLeftPane {
			m.mode = modeFilter
			m.pane = paneLeft
		}
		return m, nil

	case "enter":
		if m.pane == paneLeft {
			// Switch to that member's plan.
			mem := m.selectedMember()
			if mem == nil {
				return m, nil
			}
			m.pane = paneRight
			m.dayCursor = 0
			m.entries = nil
			m.statusf(statusNormal, "Loading %s…", mem.Name)
			return m, loadPlanCmd(m.client, mem.ID, m.selfID, m.weekOffset, time.Now())
		}
		return m.openEditForm()

	case "e":
		if m.pane == paneRight {
			return m.openEditForm()
		}
		return m, nil

	case "c":
		if m.pane == paneRight {
			return m.copyNext()
		}
		return m, nil

	case "C":
		if m.pane == paneRight {
			return m.copyRest()
		}
		return m, nil

	case "d":
		if m.pane == paneRight {
			return m.fillFromDefaults(m.currentDayEntry().EntryDate)
		}
		return m, nil

	case "D":
		if m.pane == paneRight {
			return m.fillFromDefaults("")
		}
		return m, nil

	case "a":
		if m.pane == paneRight {
			return m.markAnnualLeave()
		}
		return m, nil

	case "r":
		m.statusf(statusNormal, "Reloading…")
		return m, loadPlanCmd(m.client, m.planUserID, m.selfID, m.weekOffset, time.Now())

	case "]":
		m.weekOffset++
		m.statusf(statusNormal, "Loading…")
		return m, loadPlanCmd(m.client, m.planUserID, m.selfID, m.weekOffset, time.Now())

	case "[":
		m.weekOffset--
		m.statusf(statusNormal, "Loading…")
		return m, loadPlanCmd(m.client, m.planUserID, m.selfID, m.weekOffset, time.Now())

	case "esc":
		m.status = ""
		return m, nil
	}

	// Number keys 1-9 jump within the focused pane.
	if len(key) == 1 && key >= "1" && key <= "9" {
		n, _ := strconv.Atoi(key)
		m.setCursor(n - 1)
		return m, nil
	}

	return m, nil
}

func (m *Model) moveCursor(delta int) {
	switch m.pane {
	case paneLeft:
		if len(m.filteredIdx) == 0 {
			return
		}
		m.memberCursor = clamp(m.memberCursor+delta, 0, len(m.filteredIdx)-1)
	case paneRight:
		if len(m.days) == 0 {
			return
		}
		m.dayCursor = clamp(m.dayCursor+delta, 0, len(m.days)-1)
	}
}

func (m *Model) setCursor(idx int) {
	switch m.pane {
	case paneLeft:
		if len(m.filteredIdx) == 0 {
			return
		}
		if idx < 0 {
			idx = len(m.filteredIdx) - 1
		}
		m.memberCursor = clamp(idx, 0, len(m.filteredIdx)-1)
	case paneRight:
		if len(m.days) == 0 {
			return
		}
		if idx < 0 {
			idx = len(m.days) - 1
		}
		m.dayCursor = clamp(idx, 0, len(m.days)-1)
	}
}

func clamp(v, lo, hi int) int {
	if v < lo {
		return lo
	}
	if v > hi {
		return hi
	}
	return v
}

// ---------------------------------------------------------------------------
// Filter-mode handling

func (m Model) updateFilter(msg tea.KeyMsg) (tea.Model, tea.Cmd) {
	switch msg.Type {
	case tea.KeyEsc:
		m.filter = ""
		m.applyFilter()
		m.mode = modeList
		return m, nil
	case tea.KeyEnter:
		m.mode = modeList
		return m, nil
	case tea.KeyBackspace:
		if len(m.filter) > 0 {
			m.filter = m.filter[:len(m.filter)-1]
			m.applyFilter()
		}
		return m, nil
	case tea.KeyRunes, tea.KeySpace:
		m.filter += string(msg.Runes)
		if msg.Type == tea.KeySpace {
			m.filter += " "
		}
		m.applyFilter()
		return m, nil
	}
	return m, nil
}

// ---------------------------------------------------------------------------
// Edit-mode handling

func (m Model) updateEdit(msg tea.KeyMsg) (tea.Model, tea.Cmd) {
	if m.form == nil {
		m.mode = modeList
		return m, nil
	}

	// Esc cancels at any time.
	if msg.Type == tea.KeyEsc {
		m.mode = modeList
		m.form = nil
		return m, nil
	}

	f, cmd := m.form.Update(msg)
	if ff, ok := f.(*huh.Form); ok {
		m.form = ff
	}

	if finishCmd := m.maybeFinishEdit(); finishCmd != nil {
		return m, finishCmd
	}
	// Edit may have been aborted (form is now nil) — fall through.
	if m.form == nil {
		return m, nil
	}

	return m, cmd
}

// ---------------------------------------------------------------------------
// Copy actions, mirroring Livewire copyNext / copyRest

func (m Model) copyNext() (tea.Model, tea.Cmd) {
	if m.dayCursor+1 >= len(m.entries) {
		m.statusf(statusWarn, "Nothing after this day to copy to.")
		return m, nil
	}
	src := m.entries[m.dayCursor]
	dst := m.entries[m.dayCursor+1]
	dst.Note = src.Note
	dst.Location = src.Location
	dst.LocationLabel = src.LocationLabel
	dst.AvailabilityStatus = src.AvailabilityStatus
	dst.AvailabilityStatusLabel = src.AvailabilityStatusLabel
	m.entries[m.dayCursor+1] = dst
	m.statusf(statusNormal, "Saving…")
	return m, upsertCmd(m.client, m.planUserID, m.selfID, []api.Entry{dst})
}

func (m Model) copyRest() (tea.Model, tea.Cmd) {
	if m.dayCursor+1 >= len(m.entries) {
		m.statusf(statusWarn, "Nothing after this day to copy to.")
		return m, nil
	}
	src := m.entries[m.dayCursor]
	changed := make([]api.Entry, 0, len(m.entries)-m.dayCursor-1)
	for i := m.dayCursor + 1; i < len(m.entries); i++ {
		e := m.entries[i]
		e.Note = src.Note
		e.Location = src.Location
		e.LocationLabel = src.LocationLabel
		e.AvailabilityStatus = src.AvailabilityStatus
		e.AvailabilityStatusLabel = src.AvailabilityStatusLabel
		m.entries[i] = e
		changed = append(changed, e)
	}
	m.statusf(statusNormal, "Saving %d days…", len(changed))
	return m, upsertCmd(m.client, m.planUserID, m.selfID, changed)
}

// markAnnualLeave sets the selected day to Not Available at the
// "not-applicable" location with an "Annual leave" note, then saves it.
func (m Model) markAnnualLeave() (tea.Model, tea.Cmd) {
	if m.dayCursor >= len(m.entries) {
		return m, nil
	}
	e := m.entries[m.dayCursor]
	e.AvailabilityStatus = api.NotAvailable
	e.AvailabilityStatusLabel = api.NotAvailable.Label()
	e.Location = annualLeaveLocation
	e.LocationLabel = m.locationLabel(annualLeaveLocation)
	e.Note = "Annual leave"
	m.entries[m.dayCursor] = e
	m.statusf(statusNormal, "Saving…")
	return m, upsertCmd(m.client, m.planUserID, m.selfID, []api.Entry{e})
}

// locationLabel looks up the display label for a location slug, falling
// back to the slug itself if the server hasn't told us about it.
func (m Model) locationLabel(slug string) string {
	for _, l := range m.locations {
		if l.Value == slug {
			return l.Label
		}
	}
	return slug
}

// ---------------------------------------------------------------------------
// Fill from defaults, mirroring the web UI's fill button

// fillFromDefaults fills empty weekdays from the plan owner's profile
// defaults via the manager endpoint. onlyDate limits the fill to that one
// day; empty means the whole visible fortnight. The server only ever writes
// to empty days, so no confirm is needed.
func (m Model) fillFromDefaults(onlyDate string) (tea.Model, tea.Cmd) {
	userID := m.planUserID
	if userID == 0 {
		userID = m.selfID
	}
	if userID == 0 {
		return m, nil
	}
	weekStart, _ := windowBounds(time.Now(), m.weekOffset)
	m.statusf(statusNormal, "Filling from defaults…")
	return m, fillDefaultsCmd(m.client, userID, weekStart, onlyDate)
}

// fillStatus renders a fill result for the status line. Zero filled days is
// a normal outcome, not an error.
func fillStatus(result *api.FillDefaultsResult, singleDay bool) string {
	if result.SkippedReason == "no_defaults" {
		return "No defaults set for this user"
	}
	if result.FilledDays == 0 {
		if singleDay {
			return "Day already planned"
		}
		return "Nothing to fill - all days already planned"
	}
	return fmt.Sprintf("Filled %d days from defaults", result.FilledDays)
}

// ---------------------------------------------------------------------------
// Fatal-state helper

func (m *Model) fatal(message string) {
	m.err = message
	m.mode = modeFatal
}

// friendlyErr renders an error for the status/fatal line.
func friendlyErr(err error) string {
	switch {
	case errors.Is(err, api.ErrUnauthorized):
		return "unauthorized — token invalid or expired"
	case errors.Is(err, api.ErrForbidden):
		return "forbidden — your account can't access this"
	case errors.Is(err, api.ErrNotFound):
		return "not found"
	default:
		return err.Error()
	}
}
