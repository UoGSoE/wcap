package tui

import (
	"testing"

	"github.com/wcap/cli/internal/api"
)

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
