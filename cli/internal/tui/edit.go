package tui

import (
	"strconv"
	"strings"

	tea "github.com/charmbracelet/bubbletea"
	"github.com/charmbracelet/huh"

	"github.com/wcap/cli/internal/api"
	"github.com/wcap/cli/internal/theme"
)

// openEditForm transitions the model into edit mode for the currently-selected
// day and returns the form's Init() command (huh requires it).
//
// Why pointers for the bound values:
//
// Bubble Tea passes the model by value through every Update, so each call
// returns a fresh copy. If we bound huh fields to &m.editFoo (a struct
// field), Go's escape analysis would heap-allocate the *original* m and
// the form would keep updating that hidden copy — while bubbletea moved on
// using new copies whose fields never saw the typed text. By storing the
// bound vars as *string we make sure every model copy shares the same
// underlying value.
func (m Model) openEditForm() (tea.Model, tea.Cmd) {
	if len(m.days) == 0 {
		return m, nil
	}
	entry := m.currentDayEntry()

	availStr := strconv.Itoa(int(entry.AvailabilityStatus))
	locStr := entry.Location
	noteStr := entry.Note
	m.editAvail = &availStr
	m.editLocation = &locStr
	m.editNote = &noteStr
	m.editingDayIdx = m.dayCursor
	m.editingForUser = m.planUserID

	avail := huh.NewSelect[string]().
		Title("Availability").
		Options(
			huh.NewOption("Onsite", strconv.Itoa(int(api.Onsite))),
			huh.NewOption("Remote", strconv.Itoa(int(api.Remote))),
			huh.NewOption("Not available", strconv.Itoa(int(api.NotAvailable))),
		).
		Value(m.editAvail)

	locOpts := make([]huh.Option[string], 0, len(m.locations))
	for _, l := range m.locations {
		locOpts = append(locOpts, huh.NewOption(l.Label, l.Value))
	}
	loc := huh.NewSelect[string]().
		Title("Location").
		Options(locOpts...).
		Value(m.editLocation).
		Validate(func(s string) error {
			if strings.TrimSpace(s) == "" {
				return errLocationRequired
			}
			return nil
		})

	note := huh.NewInput().
		Title("Note").
		Placeholder("What are you up to?").
		Value(m.editNote)

	day := m.days[m.dayCursor]
	form := huh.NewForm(
		huh.NewGroup(avail, loc, note).Title(day.Format("Mon 2 Jan 2006")),
	).WithTheme(theme.NordHuh()).WithShowHelp(true)

	m.form = form
	m.mode = modeEdit
	return m, form.Init()
}

var errLocationRequired = locationRequiredErr{}

type locationRequiredErr struct{}

func (locationRequiredErr) Error() string {
	return "Location is required."
}

// entryFromForm builds an Entry from the bound form variables. It keeps the
// existing ID (if any) so the server updates rather than inserts.
func (m *Model) entryFromForm() api.Entry {
	prev := m.entries[m.editingDayIdx]
	availInt, _ := strconv.Atoi(deref(m.editAvail))
	return api.Entry{
		ID:                 prev.ID,
		EntryDate:          m.days[m.editingDayIdx].Format("2006-01-02"),
		Location:           deref(m.editLocation),
		Note:               deref(m.editNote),
		AvailabilityStatus: api.AvailabilityStatus(availInt),
	}
}

func deref(s *string) string {
	if s == nil {
		return ""
	}
	return *s
}
