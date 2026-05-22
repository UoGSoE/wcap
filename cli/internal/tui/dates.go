package tui

import "time"

// weekdays returns the 10 weekday dates in the two-week window starting on the
// Monday of the given week, offset by `weekOffset * 7` days from "now".
//
// This mirrors the Livewire PlanEntryEditor.getDays() logic but skips
// weekends (which Livewire renders as hidden inputs only).
func weekdays(now time.Time, weekOffset int) []time.Time {
	monday := startOfWeek(now).AddDate(0, 0, weekOffset*7)
	out := make([]time.Time, 0, 10)
	for offset := 0; offset < 14; offset++ {
		d := monday.AddDate(0, 0, offset)
		if d.Weekday() == time.Saturday || d.Weekday() == time.Sunday {
			continue
		}
		out = append(out, d)
	}
	return out
}

// startOfWeek returns the Monday at 00:00 local for the week containing t.
func startOfWeek(t time.Time) time.Time {
	wd := int(t.Weekday())
	// Go's Weekday: Sunday=0..Saturday=6. We want Monday=0..Sunday=6.
	if wd == 0 {
		wd = 6
	} else {
		wd--
	}
	monday := t.AddDate(0, 0, -wd)
	return time.Date(monday.Year(), monday.Month(), monday.Day(), 0, 0, 0, 0, monday.Location())
}

// windowBounds returns the YYYY-MM-DD start and end strings for the given
// week offset. start = first weekday (Monday), end = last weekday (Friday of
// the following week).
func windowBounds(now time.Time, weekOffset int) (string, string) {
	days := weekdays(now, weekOffset)
	if len(days) == 0 {
		return "", ""
	}
	const f = "2006-01-02"
	return days[0].Format(f), days[len(days)-1].Format(f)
}
