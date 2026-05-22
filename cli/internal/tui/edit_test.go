package tui

import (
	"testing"

	"github.com/wcap/cli/internal/api"
)

func TestLocationRequiredFor(t *testing.T) {
	cases := []struct {
		name     string
		avail    int
		location string
		wantErr  bool
	}{
		{"onsite without location is rejected", int(api.Onsite), "", true},
		{"onsite with location is accepted", int(api.Onsite), "jws", false},
		{"remote without location is rejected", int(api.Remote), "", true},
		{"not-available without location is accepted", int(api.NotAvailable), "", false},
		{"not-available with location is accepted", int(api.NotAvailable), "jws", false},
	}

	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			got := locationRequiredFor(tc.avail, tc.location)
			if (got != nil) != tc.wantErr {
				t.Errorf("locationRequiredFor(%d, %q) = %v, wantErr=%v", tc.avail, tc.location, got, tc.wantErr)
			}
		})
	}
}
