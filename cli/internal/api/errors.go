package api

import (
	"errors"
	"fmt"
	"sort"
	"strings"
)

// Sentinel errors for the common cases callers want to detect.
var (
	ErrUnauthorized = errors.New("api: unauthorized (401)")
	ErrForbidden    = errors.New("api: forbidden (403)")
	ErrNotFound     = errors.New("api: not found (404)")
)

// ValidationError carries the Laravel 422 envelope so we can surface field
// messages in the TUI.
type ValidationError struct {
	Message string              `json:"message"`
	Errors  map[string][]string `json:"errors"`
}

func (v *ValidationError) Error() string {
	if v == nil {
		return "validation error"
	}
	if len(v.Errors) == 0 {
		if v.Message != "" {
			return v.Message
		}
		return "validation error"
	}

	keys := make([]string, 0, len(v.Errors))
	for k := range v.Errors {
		keys = append(keys, k)
	}
	sort.Strings(keys)

	var b strings.Builder
	if v.Message != "" {
		b.WriteString(v.Message)
		b.WriteString(": ")
	}
	for i, k := range keys {
		if i > 0 {
			b.WriteString("; ")
		}
		b.WriteString(k)
		b.WriteString(": ")
		b.WriteString(strings.Join(v.Errors[k], ", "))
	}
	return b.String()
}

// BadRequestError is a 400 carrying a plain message (e.g. "invalid filter").
type BadRequestError struct {
	Message string
}

func (b *BadRequestError) Error() string {
	if b == nil || b.Message == "" {
		return "bad request"
	}
	return b.Message
}

// HTTPError is the fallback for any non-2xx with no structured body.
type HTTPError struct {
	Status int
	Body   string
}

func (h *HTTPError) Error() string {
	if h.Body == "" {
		return fmt.Sprintf("http %d", h.Status)
	}
	return fmt.Sprintf("http %d: %s", h.Status, h.Body)
}
