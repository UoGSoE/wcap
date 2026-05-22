// wcap — a TUI for the WCAP planning API.
//
// Subcommands:
//
//	wcap            launch the TUI (default)
//	wcap config     interactively set base_url + token
//	wcap version    print version
//	wcap help       print usage
package main

import (
	"context"
	"errors"
	"fmt"
	"io"
	"os"
	"strings"
	"time"

	"github.com/charmbracelet/huh"

	"github.com/wcap/cli/internal/api"
	"github.com/wcap/cli/internal/config"
	"github.com/wcap/cli/internal/theme"
	"github.com/wcap/cli/internal/tui"
)

// version is overridden via -ldflags at build time.
var version = "dev"

func main() {
	if err := run(os.Args[1:]); err != nil {
		fmt.Fprintln(os.Stderr, "wcap: "+err.Error())
		os.Exit(1)
	}
}

func run(args []string) error {
	cmd := ""
	if len(args) > 0 {
		cmd = args[0]
	}

	switch cmd {
	case "", "tui":
		return runTUI()
	case "config":
		return runConfig()
	case "version", "-v", "--version":
		fmt.Println("wcap " + version)
		return nil
	case "help", "-h", "--help":
		printUsage(os.Stdout)
		return nil
	default:
		printUsage(os.Stderr)
		return fmt.Errorf("unknown command %q", cmd)
	}
}

func printUsage(w io.Writer) {
	fmt.Fprintln(w, strings.TrimSpace(`
wcap — terminal UI for the WCAP planning API

Usage:
  wcap            launch the TUI
  wcap config     set base URL and token interactively
  wcap version    show version
  wcap help       show this message

Config lives at the platform's native user config dir:
  macOS:   ~/Library/Application Support/wcap/config.yaml
  Linux:   ~/.config/wcap/config.yaml  (or $XDG_CONFIG_HOME/wcap/)
  Windows: %AppData%\wcap\config.yaml

Env vars WCAP_BASE_URL and WCAP_TOKEN override the file at load time.
WCAP_PASSPHRASE unlocks an encrypted token without prompting (handy for CI).
`))
}

func runTUI() error {
	cfg, err := config.Load()
	if err != nil {
		return err
	}
	if !cfg.IsComplete() {
		fmt.Fprintln(os.Stderr, "wcap: no config found — run `wcap config` first.")
		return errors.New("missing config")
	}

	token := cfg.Token
	if cfg.IsLocked() {
		token, err = unlockToken(cfg)
		if err != nil {
			return err
		}
	}

	client := api.New(cfg.BaseURL, token)
	return tui.Run(client)
}

// unlockToken prompts for the passphrase (up to three attempts) and returns
// the decrypted token. Honours WCAP_PASSPHRASE for CI / scripting.
func unlockToken(cfg config.Config) (string, error) {
	if pass := os.Getenv("WCAP_PASSPHRASE"); pass != "" {
		token, err := cfg.Unlock(pass)
		if err != nil {
			return "", fmt.Errorf("WCAP_PASSPHRASE rejected: %w", err)
		}
		return token, nil
	}

	for attempt := 1; attempt <= 3; attempt++ {
		var passphrase string
		title := "Passphrase"
		if attempt > 1 {
			title = fmt.Sprintf("Passphrase (attempt %d of 3)", attempt)
		}
		form := huh.NewForm(
			huh.NewGroup(
				huh.NewInput().
					Title(title).
					Description("Unlock the saved token").
					EchoMode(huh.EchoModePassword).
					Value(&passphrase),
			),
		).WithTheme(theme.NordHuh())

		if err := form.Run(); err != nil {
			if errors.Is(err, huh.ErrUserAborted) {
				return "", errors.New("cancelled")
			}
			return "", err
		}

		token, err := cfg.Unlock(passphrase)
		if err == nil {
			return token, nil
		}
		if !errors.Is(err, config.ErrBadPassphrase) {
			return "", err
		}
		fmt.Fprintln(os.Stderr, "wcap: "+err.Error())
	}
	return "", errors.New("too many bad passphrase attempts")
}

func runConfig() error {
	cfg, err := config.Load()
	if err != nil {
		return err
	}

	baseURL := cfg.BaseURL
	var (
		token      string
		passphrase string
	)

	form := huh.NewForm(
		huh.NewGroup(
			huh.NewInput().
				Title("Base URL").
				Description("e.g. https://wcap.example.glasgow.ac.uk").
				Placeholder("https://...").
				Value(&baseURL).
				Validate(func(s string) error {
					s = strings.TrimSpace(s)
					if s == "" {
						return errors.New("base URL is required")
					}
					if !strings.HasPrefix(s, "http://") && !strings.HasPrefix(s, "https://") {
						return errors.New("must start with http:// or https://")
					}
					return nil
				}),
			huh.NewInput().
				Title("Sanctum token").
				Description("Mint one from your profile page in the web UI").
				Placeholder("wcap_xxxx…").
				EchoMode(huh.EchoModePassword).
				Value(&token).
				Validate(func(s string) error {
					if strings.TrimSpace(s) == "" {
						return errors.New("token is required")
					}
					return nil
				}),
			huh.NewInput().
				Title("Passphrase (optional)").
				Description("Set a passphrase to encrypt the token on disk (4+ chars). Leave blank to store plaintext.").
				EchoMode(huh.EchoModePassword).
				Value(&passphrase).
				Validate(func(s string) error {
					if s != "" && len(s) < 4 {
						return errors.New("passphrase must be at least 4 characters (or blank)")
					}
					return nil
				}),
		).Title("WCAP CLI configuration"),
	).WithTheme(theme.NordHuh())

	if err := form.Run(); err != nil {
		if errors.Is(err, huh.ErrUserAborted) {
			fmt.Println("Cancelled.")
			return nil
		}
		return err
	}

	cleanToken := strings.TrimSpace(token)
	newCfg := config.Config{
		BaseURL: strings.TrimRight(strings.TrimSpace(baseURL), "/"),
	}

	// Verify the raw token against the API before saving, in either mode.
	fmt.Println("Verifying token…")
	client := api.New(newCfg.BaseURL, cleanToken)
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	user, err := client.Me(ctx)
	if err != nil {
		fmt.Fprintln(os.Stderr, "wcap: token check failed: "+err.Error())
		fmt.Fprintln(os.Stderr, "Not saving. Run `wcap config` again to try a different value.")
		return err
	}

	encrypt := passphrase != ""
	if encrypt {
		blob, err := config.EncryptToken(cleanToken, passphrase)
		if err != nil {
			return fmt.Errorf("encrypt token: %w", err)
		}
		newCfg.EncryptedToken = blob
	} else {
		newCfg.Token = cleanToken
	}

	if err := config.Save(newCfg); err != nil {
		return err
	}

	path, _ := config.Path()
	mode := "plain"
	if encrypt {
		mode = "encrypted (passphrase required on next launch)"
	}
	fmt.Printf("Saved %s — token is %s.\nSigned in as %s.\n", path, mode, user.DisplayName())
	return nil
}
