// Package config handles persistent settings for the wcap CLI.
//
// Config lives at ~/.config/wcap/config.yaml with 0600 permissions. Env vars
// WCAP_BASE_URL and WCAP_TOKEN override the file at load time and never get
// written back, so they're safe for one-off invocations against staging.
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
	BaseURL string `yaml:"base_url"`
	Token   string `yaml:"token"`
}

// IsComplete reports whether the config has the minimum to make an API call.
func (c Config) IsComplete() bool {
	return strings.TrimSpace(c.BaseURL) != "" && strings.TrimSpace(c.Token) != ""
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
// A missing file is not an error — an empty Config is returned and the caller
// can detect that via IsComplete.
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
	}

	c.BaseURL = strings.TrimRight(strings.TrimSpace(c.BaseURL), "/")
	c.Token = strings.TrimSpace(c.Token)

	return c, nil
}

// Save writes the config to disk with 0600 permissions, creating parent
// directories as needed. Env-var overrides are not written back — only what
// the caller passes in.
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
