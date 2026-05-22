package tui

import (
	"testing"
	"time"
)

func TestWeekdaysReturnsTenWeekdaysStartingMonday(t *testing.T) {
	// 2026-05-22 is a Friday. The week containing it starts Mon 2026-05-18.
	friday := time.Date(2026, 5, 22, 14, 0, 0, 0, time.UTC)
	days := weekdays(friday, 0)

	if len(days) != 10 {
		t.Fatalf("expected 10 weekdays, got %d", len(days))
	}
	if days[0].Format("2006-01-02") != "2026-05-18" {
		t.Errorf("first day: got %s", days[0].Format("2006-01-02"))
	}
	if days[0].Weekday() != time.Monday {
		t.Errorf("first day should be Monday, got %s", days[0].Weekday())
	}
	if days[9].Format("2006-01-02") != "2026-05-29" {
		t.Errorf("last day: got %s", days[9].Format("2006-01-02"))
	}
	for _, d := range days {
		if d.Weekday() == time.Saturday || d.Weekday() == time.Sunday {
			t.Errorf("weekend leaked in: %s", d)
		}
	}
}

func TestWeekdaysHonoursWeekOffset(t *testing.T) {
	wed := time.Date(2026, 5, 20, 14, 0, 0, 0, time.UTC)
	next := weekdays(wed, 1)
	if next[0].Format("2006-01-02") != "2026-05-25" {
		t.Errorf("offset=1 first day: got %s", next[0].Format("2006-01-02"))
	}
	prev := weekdays(wed, -1)
	if prev[0].Format("2006-01-02") != "2026-05-11" {
		t.Errorf("offset=-1 first day: got %s", prev[0].Format("2006-01-02"))
	}
}

func TestWindowBoundsReturnsFirstAndLastWeekday(t *testing.T) {
	wed := time.Date(2026, 5, 20, 14, 0, 0, 0, time.UTC)
	from, to := windowBounds(wed, 0)
	if from != "2026-05-18" || to != "2026-05-29" {
		t.Errorf("bounds: got %s -> %s", from, to)
	}
}

func TestStartOfWeekFromSunday(t *testing.T) {
	sun := time.Date(2026, 5, 24, 23, 30, 0, 0, time.UTC) // Sunday
	monday := startOfWeek(sun)
	if monday.Format("2006-01-02") != "2026-05-18" {
		t.Errorf("Sunday should map back to the previous Monday, got %s", monday.Format("2006-01-02"))
	}
}
