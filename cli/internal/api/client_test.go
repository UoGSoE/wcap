package api

import (
	"context"
	"encoding/json"
	"errors"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
)

func newTestClient(t *testing.T, handler http.HandlerFunc) (*Client, *httptest.Server) {
	t.Helper()
	srv := httptest.NewServer(handler)
	t.Cleanup(srv.Close)
	return New(srv.URL, "test-token"), srv
}

func TestMyPlanHappyPath(t *testing.T) {
	c, _ := newTestClient(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Authorization") != "Bearer test-token" {
			t.Errorf("missing bearer token: %q", r.Header.Get("Authorization"))
		}
		if r.Header.Get("Accept") != "application/json" {
			t.Errorf("wrong Accept header: %q", r.Header.Get("Accept"))
		}
		if r.URL.Path != "/api/v1/plan" {
			t.Errorf("wrong path: %q", r.URL.Path)
		}
		_, _ = io.WriteString(w, `{
			"user": {"id": 7, "name": "Alex"},
			"date_range": {"start": "2026-05-25", "end": "2026-06-05"},
			"entries": [
				{"id": 1, "entry_date": "2026-05-25", "availability_status": 2, "location": "rankine"}
			]
		}`)
	})

	plan, err := c.MyPlan(context.Background(), "", "")
	if err != nil {
		t.Fatalf("unexpected: %v", err)
	}
	if plan.User.ID != 7 {
		t.Errorf("user id: got %d", plan.User.ID)
	}
	if len(plan.Entries) != 1 {
		t.Fatalf("expected 1 entry, got %d", len(plan.Entries))
	}
	if plan.Entries[0].Location != "rankine" {
		t.Errorf("location: got %q", plan.Entries[0].Location)
	}
	if plan.Entries[0].AvailabilityStatus != Onsite {
		t.Errorf("availability: got %d", plan.Entries[0].AvailabilityStatus)
	}
}

func TestMyPlanWithDateWindow(t *testing.T) {
	gotURL := ""
	c, _ := newTestClient(t, func(w http.ResponseWriter, r *http.Request) {
		gotURL = r.URL.String()
		_, _ = io.WriteString(w, `{"user":{"id":1,"name":"x"},"date_range":{"start":"","end":""},"entries":[]}`)
	})

	if _, err := c.MyPlan(context.Background(), "2026-06-01", "2026-06-12"); err != nil {
		t.Fatal(err)
	}
	if !strings.Contains(gotURL, "filter%5Bfrom%5D=2026-06-01") {
		t.Errorf("missing from filter: %q", gotURL)
	}
	if !strings.Contains(gotURL, "filter%5Bto%5D=2026-06-12") {
		t.Errorf("missing to filter: %q", gotURL)
	}
}

func TestUnauthorizedMapsToTypedError(t *testing.T) {
	c, _ := newTestClient(t, func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusUnauthorized)
	})

	_, err := c.MyPlan(context.Background(), "", "")
	if !errors.Is(err, ErrUnauthorized) {
		t.Fatalf("expected ErrUnauthorized, got %v", err)
	}
}

func TestForbiddenMapsToTypedError(t *testing.T) {
	c, _ := newTestClient(t, func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusForbidden)
	})

	_, err := c.TeamMembers(context.Background())
	if !errors.Is(err, ErrForbidden) {
		t.Fatalf("expected ErrForbidden, got %v", err)
	}
}

func TestValidationErrorIsTyped(t *testing.T) {
	c, _ := newTestClient(t, func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusUnprocessableEntity)
		_, _ = io.WriteString(w, `{
			"message": "The given data was invalid.",
			"errors": {
				"entries.0.entry_date": ["The entries.0.entry_date field is required."],
				"entries.0.location_id": ["Location is required when available."]
			}
		}`)
	})

	err := c.UpsertOwnPlan(context.Background(), []Entry{{EntryDate: ""}})
	var ve *ValidationError
	if !errors.As(err, &ve) {
		t.Fatalf("expected *ValidationError, got %T %v", err, err)
	}
	if !strings.Contains(ve.Error(), "entries.0.entry_date") {
		t.Errorf("error should mention bad field: %q", ve.Error())
	}
}

func TestUpsertSendsCorrectBody(t *testing.T) {
	var gotBody map[string]any
	c, _ := newTestClient(t, func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			t.Errorf("expected POST, got %s", r.Method)
		}
		if r.Header.Get("Content-Type") != "application/json" {
			t.Errorf("expected JSON content-type, got %q", r.Header.Get("Content-Type"))
		}
		_ = json.NewDecoder(r.Body).Decode(&gotBody)
		_, _ = io.WriteString(w, `{"message":"ok"}`)
	})

	id := 42
	err := c.UpsertOwnPlan(context.Background(), []Entry{{
		ID:                 &id,
		EntryDate:          "2026-05-25",
		Location:           "rankine",
		Note:               "Sprint planning",
		AvailabilityStatus: Onsite,
	}})
	if err != nil {
		t.Fatal(err)
	}

	entries, ok := gotBody["entries"].([]any)
	if !ok || len(entries) != 1 {
		t.Fatalf("expected entries array of len 1, got %v", gotBody)
	}
	first := entries[0].(map[string]any)
	if first["entry_date"] != "2026-05-25" {
		t.Errorf("entry_date: got %v", first["entry_date"])
	}
	if first["location"] != "rankine" {
		t.Errorf("location: got %v", first["location"])
	}
	if first["availability_status"].(float64) != 2 {
		t.Errorf("availability: got %v", first["availability_status"])
	}
	if int(first["id"].(float64)) != 42 {
		t.Errorf("id: got %v", first["id"])
	}
}

func TestLocationsCachesAcrossCalls(t *testing.T) {
	calls := 0
	c, _ := newTestClient(t, func(w http.ResponseWriter, r *http.Request) {
		calls++
		_, _ = io.WriteString(w, `{"locations":[{"value":"rankine","label":"Rankine","short_label":"RAN"}]}`)
	})

	if _, err := c.Locations(context.Background()); err != nil {
		t.Fatal(err)
	}
	if _, err := c.Locations(context.Background()); err != nil {
		t.Fatal(err)
	}
	if calls != 1 {
		t.Errorf("expected one network call, got %d", calls)
	}
}

func TestMemberPlanHitsManagerEndpoint(t *testing.T) {
	c, _ := newTestClient(t, func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/api/v1/manager/team-members/9/plan" {
			t.Errorf("wrong path: %q", r.URL.Path)
		}
		_, _ = io.WriteString(w, `{"user":{"id":9,"name":"sam"},"date_range":{"start":"","end":""},"entries":[]}`)
	})

	if _, err := c.MemberPlan(context.Background(), 9, "", ""); err != nil {
		t.Fatal(err)
	}
}

func TestMissingBaseURLOrToken(t *testing.T) {
	c := New("", "")
	if _, err := c.MyPlan(context.Background(), "", ""); err == nil {
		t.Fatal("expected error from missing base URL")
	}
	c = New("https://x", "")
	if _, err := c.MyPlan(context.Background(), "", ""); err == nil {
		t.Fatal("expected error from missing token")
	}
}

func TestAvailabilityStatusHelpers(t *testing.T) {
	cases := []struct {
		s         AvailabilityStatus
		label     string
		code      string
		available bool
	}{
		{Onsite, "Onsite", "O", true},
		{Remote, "Remote", "R", true},
		{NotAvailable, "Not Available", "N", false},
	}
	for _, c := range cases {
		if got := c.s.Label(); got != c.label {
			t.Errorf("Label(%d): got %q want %q", c.s, got, c.label)
		}
		if got := c.s.Code(); got != c.code {
			t.Errorf("Code(%d): got %q want %q", c.s, got, c.code)
		}
		if got := c.s.IsAvailable(); got != c.available {
			t.Errorf("IsAvailable(%d): got %v want %v", c.s, got, c.available)
		}
	}
}
