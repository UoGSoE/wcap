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

Config lives at ~/.config/wcap/config.yaml. Env vars WCAP_BASE_URL and
WCAP_TOKEN override the file at load time.
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

	client := api.New(cfg.BaseURL, cfg.Token)
	return tui.Run(client)
}

func runConfig() error {
	cfg, err := config.Load()
	if err != nil {
		return err
	}

	current := cfg
	baseURL := cfg.BaseURL
	token := cfg.Token

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
		).Title("WCAP CLI configuration"),
	).WithTheme(theme.NordHuh())

	if err := form.Run(); err != nil {
		if errors.Is(err, huh.ErrUserAborted) {
			fmt.Println("Cancelled.")
			return nil
		}
		return err
	}

	newCfg := config.Config{
		BaseURL: strings.TrimRight(strings.TrimSpace(baseURL), "/"),
		Token:   strings.TrimSpace(token),
	}

	// Verify against the API before saving.
	fmt.Println("Verifying token…")
	client := api.New(newCfg.BaseURL, newCfg.Token)
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	user, err := client.Me(ctx)
	if err != nil {
		fmt.Fprintln(os.Stderr, "wcap: token check failed: "+err.Error())
		fmt.Fprintln(os.Stderr, "Not saving. Run `wcap config` again to try a different value.")
		// Keep the previous file as-is.
		_ = current
		return err
	}

	if err := config.Save(newCfg); err != nil {
		return err
	}

	path, _ := config.Path()
	fmt.Printf("Saved %s.\nSigned in as %s.\n", path, user.DisplayName())
	return nil
}
