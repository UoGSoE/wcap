package tui

import (
	"testing"
	"time"

	"github.com/wcap/cli/internal/api"
)

func TestRebuildEntriesFillsMissingDays(t *testing.T) {
	m := Model{
		days: []time.Time{
			time.Date(2026, 5, 25, 0, 0, 0, 0, time.UTC),
			time.Date(2026, 5, 26, 0, 0, 0, 0, time.UTC),
			time.Date(2026, 5, 27, 0, 0, 0, 0, time.UTC),
		},
	}
	id := 99
	fetched := []api.Entry{
		{ID: &id, EntryDate: "2026-05-26", AvailabilityStatus: api.Remote, Note: "writing"},
	}
	m.rebuildEntries(fetched)

	if len(m.entries) != 3 {
		t.Fatalf("expected 3 slots, got %d", len(m.entries))
	}
	if m.entries[0].ID != nil {
		t.Errorf("day 0 should be empty (no ID)")
	}
	if m.entries[1].ID == nil || *m.entries[1].ID != 99 {
		t.Errorf("day 1 should keep id=99")
	}
	if m.entries[1].Note != "writing" {
		t.Errorf("day 1 note lost: %q", m.entries[1].Note)
	}
	if m.entries[0].AvailabilityStatus != api.Onsite {
		t.Errorf("default availability should be Onsite, got %d", m.entries[0].AvailabilityStatus)
	}
}

func TestApplyFilterMatchesCaseInsensitively(t *testing.T) {
	m := Model{
		members: []api.TeamMember{
			{ID: 1, Name: "Bilbo Baggins"},
			{ID: 2, Name: "Frodo Baggins"},
			{ID: 3, Name: "Aragorn"},
		},
	}
	m.filter = "BAG"
	m.applyFilter()
	if len(m.filteredIdx) != 2 {
		t.Fatalf("expected 2 matches, got %d", len(m.filteredIdx))
	}

	m.filter = ""
	m.applyFilter()
	if len(m.filteredIdx) != 3 {
		t.Errorf("empty filter should match all")
	}
}

func TestSortMembersBySurnamePutsSelfFirst(t *testing.T) {
	members := []api.TeamMember{
		{ID: 1, Name: "Aragorn Elessar"},
		{ID: 2, Name: "Bilbo Baggins"},
		{ID: 3, Name: "Frodo Baggins"},
	}
	sortMembersBySurname(members, 3)
	if members[0].ID != 3 {
		t.Errorf("self should be first, got id %d (%s)", members[0].ID, members[0].Name)
	}
}

func TestCurrentDayEntryFallsBackToStub(t *testing.T) {
	m := Model{
		days: []time.Time{time.Date(2026, 5, 25, 0, 0, 0, 0, time.UTC)},
	}
	got := m.currentDayEntry()
	if got.EntryDate != "2026-05-25" {
		t.Errorf("expected stub date, got %q", got.EntryDate)
	}
}
