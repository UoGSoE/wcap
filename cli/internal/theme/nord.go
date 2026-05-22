// Package theme provides Nord-coloured lipgloss styles for the wcap TUI.
//
// Use the semantic aliases (Accent, Success, Error, ...) in the rest of the
// app rather than the raw Nord* names — switching palettes later then becomes
// a one-file change to the alias mapping.
//
// Reference: https://www.nordtheme.com/docs/colors-and-palettes
package theme

import "github.com/charmbracelet/lipgloss"

// Polar Night (backgrounds)
var (
	Nord0 = lipgloss.Color("#2e3440")
	Nord1 = lipgloss.Color("#3b4252")
	Nord2 = lipgloss.Color("#434c5e")
	Nord3 = lipgloss.Color("#4c566a")
)

// Snow Storm (foregrounds)
var (
	Nord4 = lipgloss.Color("#d8dee9")
	Nord5 = lipgloss.Color("#e5e9f0")
	Nord6 = lipgloss.Color("#eceff4")
)

// Frost (blues / teals — primary accents)
var (
	Nord7  = lipgloss.Color("#8fbcbb")
	Nord8  = lipgloss.Color("#88c0d0")
	Nord9  = lipgloss.Color("#81a1c1")
	Nord10 = lipgloss.Color("#5e81ac")
)

// Aurora (reds / oranges / yellows / greens / purples)
var (
	Nord11 = lipgloss.Color("#bf616a")
	Nord12 = lipgloss.Color("#d08770")
	Nord13 = lipgloss.Color("#ebcb8b")
	Nord14 = lipgloss.Color("#a3be8c")
	Nord15 = lipgloss.Color("#b48ead")
)

// Semantic aliases.
var (
	Bg      = Nord0
	BgAlt   = Nord1
	Fg      = Nord4
	FgMuted = Nord3
	Accent  = Nord8
	Success = Nord14
	Warning = Nord13
	Error   = Nord11
	Info    = Nord9
)

// Common lipgloss styles.
var (
	Title    = lipgloss.NewStyle().Foreground(Accent).Bold(true)
	Selected = lipgloss.NewStyle().Foreground(Nord0).Background(Accent).Bold(true)
	Normal   = lipgloss.NewStyle().Foreground(Fg)
	Faint    = lipgloss.NewStyle().Foreground(FgMuted)
	Border   = lipgloss.NewStyle().BorderForeground(Nord3)

	// Availability-status colours, semantically named.
	OnsiteFg    = lipgloss.NewStyle().Foreground(Success)
	RemoteFg    = lipgloss.NewStyle().Foreground(Info)
	UnavailFg   = lipgloss.NewStyle().Foreground(FgMuted)
	StatusGood  = lipgloss.NewStyle().Foreground(Success)
	StatusWarn  = lipgloss.NewStyle().Foreground(Warning)
	StatusError = lipgloss.NewStyle().Foreground(Error)
)
