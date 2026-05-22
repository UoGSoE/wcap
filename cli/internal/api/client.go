// Package api is a thin, typed wrapper around the wcap /api/v1 endpoints.
//
// The whole surface is six endpoints — the client deliberately exposes one
// method per endpoint we use, with strict typed errors so the TUI can render
// 401/403/422 sensibly instead of dumping raw JSON.
package api

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"sync"
	"time"
)

const userAgent = "wcap-cli/0.1"

// Client talks to a single wcap instance with a single bearer token.
type Client struct {
	baseURL string
	token   string
	hc      *http.Client

	mu        sync.RWMutex
	locations []Location // cached after first fetch
}

// New creates a Client. baseURL should be the scheme+host (e.g.
// "https://wcap.example.com") with no trailing slash.
func New(baseURL, token string) *Client {
	return &Client{
		baseURL: strings.TrimRight(baseURL, "/"),
		token:   token,
		hc:      &http.Client{Timeout: 15 * time.Second},
	}
}

// BaseURL returns the configured base URL.
func (c *Client) BaseURL() string { return c.baseURL }

// Me fetches the authenticated user via GET /api/v1/user. Use it as a login
// probe — a successful return confirms the token is good.
func (c *Client) Me(ctx context.Context) (AuthUser, error) {
	var u AuthUser
	if err := c.do(ctx, http.MethodGet, "/api/user", nil, nil, &u); err != nil {
		return AuthUser{}, err
	}
	return u, nil
}

// MyPlan returns the authenticated user's plan for the default 10-weekday
// window, or a custom window if from/to are non-empty (both must be set).
func (c *Client) MyPlan(ctx context.Context, from, to string) (*PlanResponse, error) {
	q := planWindowQuery(from, to)
	var out PlanResponse
	if err := c.do(ctx, http.MethodGet, "/api/v1/plan", q, nil, &out); err != nil {
		return nil, err
	}
	return &out, nil
}

// UpsertOwnPlan saves the given entries against the authenticated user's plan.
func (c *Client) UpsertOwnPlan(ctx context.Context, entries []Entry) error {
	body := upsertPayload{Entries: toUpsertEntries(entries)}
	return c.do(ctx, http.MethodPost, "/api/v1/plan", nil, body, nil)
}

// TeamMembers returns users the caller can manage. Staff users get ErrForbidden.
func (c *Client) TeamMembers(ctx context.Context) ([]TeamMember, error) {
	var resp struct {
		TeamMembers []TeamMember `json:"team_members"`
	}
	if err := c.do(ctx, http.MethodGet, "/api/v1/manager/team-members", nil, nil, &resp); err != nil {
		return nil, err
	}
	return resp.TeamMembers, nil
}

// MemberPlan returns a team member's plan for the given window.
func (c *Client) MemberPlan(ctx context.Context, userID int, from, to string) (*PlanResponse, error) {
	q := planWindowQuery(from, to)
	path := "/api/v1/manager/team-members/" + strconv.Itoa(userID) + "/plan"
	var out PlanResponse
	if err := c.do(ctx, http.MethodGet, path, q, nil, &out); err != nil {
		return nil, err
	}
	return &out, nil
}

// UpsertMemberPlan saves entries for the given team member.
func (c *Client) UpsertMemberPlan(ctx context.Context, userID int, entries []Entry) error {
	body := upsertPayload{Entries: toUpsertEntries(entries)}
	path := "/api/v1/manager/team-members/" + strconv.Itoa(userID) + "/plan"
	return c.do(ctx, http.MethodPost, path, nil, body, nil)
}

// Locations returns the locations list, fetching once and caching it on the
// client. The reference data doesn't change often enough to justify TTLs.
func (c *Client) Locations(ctx context.Context) ([]Location, error) {
	c.mu.RLock()
	if c.locations != nil {
		out := c.locations
		c.mu.RUnlock()
		return out, nil
	}
	c.mu.RUnlock()

	var resp struct {
		Locations []Location `json:"locations"`
	}
	if err := c.do(ctx, http.MethodGet, "/api/v1/locations", nil, nil, &resp); err != nil {
		return nil, err
	}

	c.mu.Lock()
	c.locations = resp.Locations
	c.mu.Unlock()

	return resp.Locations, nil
}

func planWindowQuery(from, to string) url.Values {
	if from == "" || to == "" {
		return nil
	}
	q := url.Values{}
	q.Set("filter[from]", from)
	q.Set("filter[to]", to)
	return q
}

// do is the one HTTP method shared by every endpoint. It marshals body (when
// non-nil), adds the bearer token, and unmarshals into out (when non-nil).
// Non-2xx responses are mapped to typed errors.
func (c *Client) do(ctx context.Context, method, path string, query url.Values, body any, out any) error {
	if c.baseURL == "" {
		return errors.New("api: base URL not configured")
	}
	if c.token == "" {
		return errors.New("api: token not configured")
	}

	u := c.baseURL + path
	if len(query) > 0 {
		u += "?" + query.Encode()
	}

	var reader io.Reader
	if body != nil {
		buf, err := json.Marshal(body)
		if err != nil {
			return fmt.Errorf("encode request body: %w", err)
		}
		reader = bytes.NewReader(buf)
	}

	req, err := http.NewRequestWithContext(ctx, method, u, reader)
	if err != nil {
		return fmt.Errorf("build request: %w", err)
	}
	req.Header.Set("Accept", "application/json")
	req.Header.Set("Authorization", "Bearer "+c.token)
	req.Header.Set("User-Agent", userAgent)
	if reader != nil {
		req.Header.Set("Content-Type", "application/json")
	}

	res, err := c.hc.Do(req)
	if err != nil {
		return fmt.Errorf("%s %s: %w", method, path, err)
	}
	defer res.Body.Close()

	raw, _ := io.ReadAll(res.Body)

	if res.StatusCode >= 200 && res.StatusCode < 300 {
		if out == nil || len(raw) == 0 {
			return nil
		}
		if err := json.Unmarshal(raw, out); err != nil {
			return fmt.Errorf("decode response: %w", err)
		}
		return nil
	}

	return decodeError(res.StatusCode, raw)
}

func decodeError(status int, body []byte) error {
	switch status {
	case http.StatusUnauthorized:
		return ErrUnauthorized
	case http.StatusForbidden:
		return ErrForbidden
	case http.StatusNotFound:
		return ErrNotFound
	case http.StatusUnprocessableEntity:
		var ve ValidationError
		if err := json.Unmarshal(body, &ve); err == nil && (ve.Message != "" || len(ve.Errors) > 0) {
			return &ve
		}
	case http.StatusBadRequest:
		var br struct {
			Message string `json:"message"`
		}
		if err := json.Unmarshal(body, &br); err == nil && br.Message != "" {
			return &BadRequestError{Message: br.Message}
		}
	}
	return &HTTPError{Status: status, Body: strings.TrimSpace(string(body))}
}
