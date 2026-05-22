package config

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// withTempHome points os.UserConfigDir at a temp directory for the test's
// lifetime via XDG_CONFIG_HOME (Linux) and HOME (macOS).
func withTempHome(t *testing.T) string {
	t.Helper()
	dir := t.TempDir()
	t.Setenv("HOME", dir)
	t.Setenv("XDG_CONFIG_HOME", filepath.Join(dir, ".config"))
	return dir
}

func TestSaveAndLoadRoundTrip(t *testing.T) {
	withTempHome(t)
	t.Setenv("WCAP_BASE_URL", "")
	t.Setenv("WCAP_TOKEN", "")

	in := Config{BaseURL: "https://wcap.example.com/", Token: "  abc123  "}
	if err := Save(in); err != nil {
		t.Fatal(err)
	}

	out, err := Load()
	if err != nil {
		t.Fatal(err)
	}
	if out.BaseURL != "https://wcap.example.com" { // trailing slash stripped
		t.Errorf("BaseURL: got %q", out.BaseURL)
	}
	if out.Token != "abc123" {
		t.Errorf("Token: got %q", out.Token)
	}
	if !out.IsComplete() {
		t.Error("expected complete config")
	}
}

func TestLoadMissingFile(t *testing.T) {
	withTempHome(t)
	t.Setenv("WCAP_BASE_URL", "")
	t.Setenv("WCAP_TOKEN", "")

	out, err := Load()
	if err != nil {
		t.Fatal(err)
	}
	if out.IsComplete() {
		t.Error("expected incomplete config")
	}
}

func TestEnvOverridesFile(t *testing.T) {
	withTempHome(t)
	if err := Save(Config{BaseURL: "https://file-url", Token: "file-token"}); err != nil {
		t.Fatal(err)
	}
	t.Setenv("WCAP_BASE_URL", "https://env-url")
	t.Setenv("WCAP_TOKEN", "env-token")

	out, err := Load()
	if err != nil {
		t.Fatal(err)
	}
	if out.BaseURL != "https://env-url" {
		t.Errorf("BaseURL: got %q", out.BaseURL)
	}
	if out.Token != "env-token" {
		t.Errorf("Token: got %q", out.Token)
	}
}

func TestSaveHasRestrictivePermissions(t *testing.T) {
	withTempHome(t)
	if err := Save(Config{BaseURL: "https://x", Token: "t"}); err != nil {
		t.Fatal(err)
	}
	p, _ := Path()
	info, err := os.Stat(p)
	if err != nil {
		t.Fatal(err)
	}
	if info.Mode().Perm() != 0o600 {
		t.Errorf("expected 0600, got %v", info.Mode().Perm())
	}
}

func TestIsCompleteHandlesWhitespace(t *testing.T) {
	cases := []struct {
		c    Config
		want bool
	}{
		{Config{}, false},
		{Config{BaseURL: "https://x"}, false},
		{Config{Token: "abc"}, false},
		{Config{BaseURL: "   ", Token: "abc"}, false},
		{Config{BaseURL: "https://x", Token: "abc"}, true},
	}
	for i, tc := range cases {
		if got := tc.c.IsComplete(); got != tc.want {
			t.Errorf("case %d: got %v want %v", i, got, tc.want)
		}
	}
	_ = strings.TrimSpace
}
