// Package tui is the Bubble Tea UI for the wcap CLI.
package tui

import (
	"context"
	"fmt"
	"sort"
	"strings"
	"time"

	tea "github.com/charmbracelet/bubbletea"
	"github.com/charmbracelet/huh"

	"github.com/wcap/cli/internal/api"
)

type mode int

const (
	modeLoading mode = iota
	modeList
	modeEdit
	modeFilter
	modeHelp
	modeFatal
)

type pane int

const (
	paneLeft pane = iota
	paneRight
)

// Model is the single TUI model. All transient screen state lives here.
type Model struct {
	client *api.Client

	mode mode
	pane pane

	// Loading state.
	loadedMe        bool
	loadedMembers   bool
	loadedLocations bool
	loadedPlan      bool

	// Self.
	selfID   int
	selfName string

	// Managed members. The first entry is always self (with "(me)" suffix
	// applied at render time, not stored on the struct).
	members        []api.TeamMember
	canManage      bool
	memberCursor   int
	filter         string
	filteredIdx    []int // indices into members for the current filter
	hideLeftPane   bool

	// Plan currently displayed in the right pane.
	planUserID int
	planUser   api.User
	dateRange  api.DateRange
	weekOffset int
	days       []time.Time
	entries    []api.Entry // dense: len == len(days); zero-id entries are unsaved slots
	dayCursor  int

	// Reference data.
	locations []api.Location

	// Edit-mode state. Bound values are *pointers* so the form's bindings
	// stay live across Bubble Tea's value-receiver model copies — see the
	// note on openEditForm.
	form           *huh.Form
	editAvail      *string // string-encoded int so huh.NewSelect[string] works cleanly
	editLocation   *string // slug
	editNote       *string
	editingDayIdx  int
	editingForUser int

	// UI feedback.
	status     string
	statusKind statusKind
	err        string
	width      int
	height     int

	// Help overlay context.
	prevMode mode
}

type statusKind int

const (
	statusNormal statusKind = iota
	statusGood
	statusWarn
	statusBad
)

// New builds a fresh Model wired to the given client.
func New(client *api.Client) Model {
	return Model{
		client: client,
		mode:   modeLoading,
		pane:   paneRight,
	}
}

// Init kicks off the four parallel startup requests.
func (m Model) Init() tea.Cmd {
	return tea.Batch(
		loadMeCmd(m.client),
		loadMembersCmd(m.client),
		loadLocationsCmd(m.client),
		loadPlanCmd(m.client, 0, 0, m.weekOffset, time.Now()),
	)
}

// Run starts the bubbletea program in alt-screen mode.
func Run(client *api.Client) error {
	p := tea.NewProgram(New(client), tea.WithAltScreen())
	_, err := p.Run()
	return err
}

// ---------------------------------------------------------------------------
// helpers used by both Update and View.

// currentDayEntry returns the api.Entry covering days[dayCursor] (a zero-ID
// stub if no entry exists for that date yet).
func (m *Model) currentDayEntry() api.Entry {
	if m.dayCursor < 0 || m.dayCursor >= len(m.days) {
		return api.Entry{}
	}
	if m.dayCursor < len(m.entries) {
		return m.entries[m.dayCursor]
	}
	return api.Entry{EntryDate: m.days[m.dayCursor].Format("2006-01-02")}
}

// rebuildEntries merges fetched entries into one slot per weekday in m.days,
// preserving order.
func (m *Model) rebuildEntries(fetched []api.Entry) {
	byDate := make(map[string]api.Entry, len(fetched))
	for _, e := range fetched {
		byDate[e.EntryDate] = e
	}

	out := make([]api.Entry, len(m.days))
	for i, d := range m.days {
		key := d.Format("2006-01-02")
		if e, ok := byDate[key]; ok {
			out[i] = e
		} else {
			out[i] = api.Entry{
				EntryDate:          key,
				AvailabilityStatus: api.Onsite, // sensible default; server will accept it
			}
		}
	}
	m.entries = out
}

// refreshMembers normalises the left-pane list: seed a self-only list when
// we can't manage anyone, ensure self is present and pinned first otherwise,
// then rebuild the filter index. Safe to call on every members update, since
// the members response can land after loading has already finished.
func (m *Model) refreshMembers() {
	if !m.loadedMe {
		return
	}

	// Populate the members slice so selfID can be selected even without
	// manage permission.
	if !m.canManage && len(m.members) == 0 {
		m.members = []api.TeamMember{{ID: m.selfID, Name: m.selfName}}
	} else if m.canManage {
		// Ensure self is in the list (it usually is, but staff/admin lookups
		// vary).
		hasSelf := false
		for _, x := range m.members {
			if x.ID == m.selfID {
				hasSelf = true
				break
			}
		}
		if !hasSelf {
			m.members = append(m.members, api.TeamMember{ID: m.selfID, Name: m.selfName})
		}
		sortMembersBySurname(m.members, m.selfID)
	}

	m.applyFilter()
}

// applyFilter recomputes filteredIdx based on the current filter string.
func (m *Model) applyFilter() {
	m.filteredIdx = m.filteredIdx[:0]
	q := strings.ToLower(strings.TrimSpace(m.filter))
	for i, mem := range m.members {
		if q == "" || strings.Contains(strings.ToLower(mem.Name), q) {
			m.filteredIdx = append(m.filteredIdx, i)
		}
	}
	if m.memberCursor >= len(m.filteredIdx) {
		m.memberCursor = max0(len(m.filteredIdx) - 1)
	}
}

// selectedMember returns the member currently highlighted in the left pane,
// or nil if there are none.
func (m *Model) selectedMember() *api.TeamMember {
	if len(m.filteredIdx) == 0 {
		return nil
	}
	idx := m.filteredIdx[m.memberCursor]
	if idx < 0 || idx >= len(m.members) {
		return nil
	}
	return &m.members[idx]
}

// sortMembersBySurname puts self first, then everyone else alphabetised by
// the trailing word of their name (best-effort surname extraction — the API
// already orders by surname for us, this is just defensive).
func sortMembersBySurname(members []api.TeamMember, selfID int) {
	sort.SliceStable(members, func(i, j int) bool {
		if members[i].ID == selfID {
			return true
		}
		if members[j].ID == selfID {
			return false
		}
		return surname(members[i].Name) < surname(members[j].Name)
	})
}

func surname(name string) string {
	parts := strings.Fields(name)
	if len(parts) == 0 {
		return name
	}
	return strings.ToLower(parts[len(parts)-1])
}

func max0(v int) int {
	if v < 0 {
		return 0
	}
	return v
}

// ctxBackground builds a fresh request context. Bubble Tea commands run on
// their own goroutines so we don't have a per-call context handy.
func ctxBackground() (context.Context, context.CancelFunc) {
	return context.WithTimeout(context.Background(), 20*time.Second)
}

// statusf sets a transient status line.
func (m *Model) statusf(kind statusKind, format string, args ...any) {
	m.status = fmt.Sprintf(format, args...)
	m.statusKind = kind
}
