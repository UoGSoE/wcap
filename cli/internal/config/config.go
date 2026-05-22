// Package config handles persistent settings for the wcap CLI.
//
// Config lives at the platform's user config dir (e.g.
// ~/Library/Application Support/wcap/config.yaml on macOS) with 0600
// permissions. Env vars WCAP_BASE_URL and WCAP_TOKEN override the file at
// load time and never get written back, so they're safe for one-off
// invocations against staging.
//
// The token can be stored two ways:
//   - `token`: plaintext (the default — same threat surface as ~/.aws/credentials).
//   - `encrypted_token`: AEAD blob, key derived from a passphrase the user
//     supplies on every launch (see crypt.go).
//
// Exactly one of those should be set after a successful `wcap config` run.
// If both are set, the encrypted form wins.
package config

import (
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"strings"

	"gopkg.in/yaml.v3"
)

const (
	envBaseURL = "WCAP_BASE_URL"
	envToken   = "WCAP_TOKEN"
	dirName    = "wcap"
	fileName   = "config.yaml"
)

// Config is the on-disk shape.
type Config struct {
	BaseURL        string `yaml:"base_url"`
	Token          string `yaml:"token,omitempty"`
	EncryptedToken string `yaml:"encrypted_token,omitempty"`
}

// IsComplete reports whether the config has the minimum to make an API call.
// A locked (encrypted) token still counts as complete — the caller is
// expected to call Unlock before talking to the API.
func (c Config) IsComplete() bool {
	if strings.TrimSpace(c.BaseURL) == "" {
		return false
	}
	return strings.TrimSpace(c.Token) != "" || strings.TrimSpace(c.EncryptedToken) != ""
}

// IsLocked reports whether the token needs unlocking with a passphrase
// before use. False when the user has set WCAP_TOKEN (env wins) or stored
// the token in plaintext.
func (c Config) IsLocked() bool {
	return c.Token == "" && c.EncryptedToken != ""
}

// Unlock decrypts the stored token with the given passphrase. Only valid
// when IsLocked() is true.
func (c Config) Unlock(passphrase string) (string, error) {
	if !c.IsLocked() {
		return c.Token, nil
	}
	return DecryptToken(c.EncryptedToken, passphrase)
}

// Path returns the absolute path the config is loaded from / saved to.
func Path() (string, error) {
	dir, err := os.UserConfigDir()
	if err != nil {
		return "", fmt.Errorf("locate user config dir: %w", err)
	}
	return filepath.Join(dir, dirName, fileName), nil
}

// Load reads the YAML file (if present), then applies env-var overrides.
// A missing file is not an error — an empty Config is returned and the
// caller can detect that via IsComplete.
//
// When WCAP_TOKEN is set, the env value wins and clears EncryptedToken so
// the caller doesn't think the config is locked.
func Load() (Config, error) {
	var c Config

	p, err := Path()
	if err != nil {
		return c, err
	}

	raw, err := os.ReadFile(p)
	switch {
	case err == nil:
		if err := yaml.Unmarshal(raw, &c); err != nil {
			return c, fmt.Errorf("parse %s: %w", p, err)
		}
	case errors.Is(err, os.ErrNotExist):
		// fine — first run.
	default:
		return c, fmt.Errorf("read %s: %w", p, err)
	}

	if v := os.Getenv(envBaseURL); v != "" {
		c.BaseURL = v
	}
	if v := os.Getenv(envToken); v != "" {
		c.Token = v
		c.EncryptedToken = "" // env wins; nothing to unlock
	}

	c.BaseURL = strings.TrimRight(strings.TrimSpace(c.BaseURL), "/")
	c.Token = strings.TrimSpace(c.Token)
	c.EncryptedToken = strings.TrimSpace(c.EncryptedToken)

	return c, nil
}

// Save writes the config to disk with 0600 permissions, creating parent
// directories as needed. Env-var overrides are not written back — only
// what the caller passes in.
func Save(c Config) error {
	p, err := Path()
	if err != nil {
		return err
	}

	if err := os.MkdirAll(filepath.Dir(p), 0o700); err != nil {
		return fmt.Errorf("create config dir: %w", err)
	}

	out, err := yaml.Marshal(c)
	if err != nil {
		return fmt.Errorf("marshal config: %w", err)
	}

	if err := os.WriteFile(p, out, 0o600); err != nil {
		return fmt.Errorf("write %s: %w", p, err)
	}

	return nil
}
