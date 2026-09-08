package tui

import (
	"testing"

	tea "github.com/charmbracelet/bubbletea"

	"github.com/wcap/cli/internal/api"
)

func TestMembersArrivingAfterLoadingFinishesStillPopulateLeftPane(t *testing.T) {
	m := New(nil)
	step := func(msg tea.Msg) {
		model, _ := m.Update(msg)
		m = model.(Model)
	}

	// The race: /user and the plan respond before the team-members request.
	step(meLoadedMsg{user: api.AuthUser{ID: 1, FullName: "Frodo Baggins"}})
	step(planLoadedMsg{forUserID: 1, plan: &api.PlanResponse{User: api.User{ID: 1, Name: "Frodo Baggins"}}})
	if m.mode != modeList {
		t.Fatalf("expected list mode once me+plan have loaded, got %d", m.mode)
	}

	step(membersLoadedMsg{members: []api.TeamMember{
		{ID: 2, Name: "Merry Brandybuck"},
		{ID: 3, Name: "Sam Gamgee"},
		{ID: 1, Name: "Frodo Baggins"},
	}})

	if len(m.filteredIdx) != 3 {
		t.Fatalf("expected 3 visible members, got %d", len(m.filteredIdx))
	}
	if got := m.members[m.filteredIdx[0]]; got.ID != 1 {
		t.Errorf("self should be pinned first, got %q", got.Name)
	}
}

func TestFillStatusWording(t *testing.T) {
	cases := []struct {
		name      string
		result    *api.FillDefaultsResult
		singleDay bool
		want      string
	}{
		{"filled fortnight", &api.FillDefaultsResult{FilledDays: 6}, false, "Filled 6 days from defaults"},
		{"filled single day", &api.FillDefaultsResult{FilledDays: 1}, true, "Filled 1 days from defaults"},
		{"single day already planned", &api.FillDefaultsResult{FilledDays: 0}, true, "Day already planned"},
		{"fortnight nothing to fill", &api.FillDefaultsResult{FilledDays: 0}, false, "Nothing to fill - all days already planned"},
		{"no defaults", &api.FillDefaultsResult{FilledDays: 0, SkippedReason: "no_defaults"}, false, "No defaults set for this user"},
	}
	for _, c := range cases {
		if got := fillStatus(c.result, c.singleDay); got != c.want {
			t.Errorf("%s: got %q want %q", c.name, got, c.want)
		}
	}
}

func TestPressingAMarksSelectedDayAsAnnualLeave(t *testing.T) {
	m := Model{
		mode:      modeList,
		pane:      paneRight,
		locations: []api.Location{{Value: "office", Label: "Office"}, {Value: "not-applicable", Label: "Not Applicable"}},
		entries: []api.Entry{
			{EntryDate: "2026-09-07", Location: "office", Note: "Support tickets", AvailabilityStatus: api.Onsite},
			{EntryDate: "2026-09-08", Location: "office", Note: "Support tickets", AvailabilityStatus: api.Onsite},
		},
		dayCursor: 1,
	}

	model, cmd := m.Update(tea.KeyMsg{Type: tea.KeyRunes, Runes: []rune("a")})
	m = model.(Model)

	if cmd == nil {
		t.Fatal("expected a save command to be returned")
	}
	got := m.entries[1]
	if got.AvailabilityStatus != api.NotAvailable || got.Location != "not-applicable" || got.Note != "Annual leave" {
		t.Errorf("selected day not marked as annual leave: %+v", got)
	}
	if got.LocationLabel != "Not Applicable" {
		t.Errorf("expected location label to be refreshed, got %q", got.LocationLabel)
	}
	if untouched := m.entries[0]; untouched.Note != "Support tickets" || untouched.AvailabilityStatus != api.Onsite {
		t.Errorf("other day should be untouched: %+v", untouched)
	}
}
