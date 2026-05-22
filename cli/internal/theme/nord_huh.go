package theme

import (
	"github.com/charmbracelet/huh"
	"github.com/charmbracelet/lipgloss"
)

// NordHuh returns a huh.Theme themed with the Nord palette.
func NordHuh() *huh.Theme {
	t := huh.ThemeBase()

	t.Focused.Base = t.Focused.Base.BorderForeground(Nord3)
	t.Focused.Title = t.Focused.Title.Foreground(Accent).Bold(true)
	t.Focused.NoteTitle = t.Focused.NoteTitle.Foreground(Accent).Bold(true)
	t.Focused.Description = t.Focused.Description.Foreground(FgMuted)
	t.Focused.ErrorIndicator = t.Focused.ErrorIndicator.Foreground(Error)
	t.Focused.ErrorMessage = t.Focused.ErrorMessage.Foreground(Error)

	t.Focused.SelectSelector = t.Focused.SelectSelector.Foreground(Accent)
	t.Focused.NextIndicator = t.Focused.NextIndicator.Foreground(Accent)
	t.Focused.PrevIndicator = t.Focused.PrevIndicator.Foreground(Accent)
	t.Focused.MultiSelectSelector = t.Focused.MultiSelectSelector.Foreground(Accent)

	t.Focused.Option = t.Focused.Option.Foreground(Fg)
	t.Focused.SelectedOption = t.Focused.SelectedOption.Foreground(Success)
	t.Focused.SelectedPrefix = t.Focused.SelectedPrefix.Foreground(Success)
	t.Focused.UnselectedOption = t.Focused.UnselectedOption.Foreground(Fg)
	t.Focused.UnselectedPrefix = t.Focused.UnselectedPrefix.Foreground(FgMuted)

	t.Focused.FocusedButton = t.Focused.FocusedButton.
		Foreground(Nord0).Background(Accent).Bold(true)
	t.Focused.BlurredButton = t.Focused.BlurredButton.
		Foreground(Fg).Background(BgAlt)

	t.Focused.TextInput.Cursor = t.Focused.TextInput.Cursor.Foreground(Accent)
	t.Focused.TextInput.Placeholder = t.Focused.TextInput.Placeholder.Foreground(FgMuted)
	t.Focused.TextInput.Prompt = t.Focused.TextInput.Prompt.Foreground(Accent)
	t.Focused.TextInput.Text = t.Focused.TextInput.Text.Foreground(Fg)

	t.Blurred = t.Focused
	t.Blurred.Base = t.Blurred.Base.BorderForeground(Nord2)
	t.Blurred.Title = t.Blurred.Title.Foreground(FgMuted)
	t.Blurred.NoteTitle = t.Blurred.NoteTitle.Foreground(FgMuted)
	t.Blurred.TextInput.Prompt = t.Blurred.TextInput.Prompt.Foreground(FgMuted)
	t.Blurred.TextInput.Text = t.Blurred.TextInput.Text.Foreground(FgMuted)

	t.Help.ShortKey = lipgloss.NewStyle().Foreground(Info)
	t.Help.ShortDesc = lipgloss.NewStyle().Foreground(FgMuted)
	t.Help.ShortSeparator = lipgloss.NewStyle().Foreground(Nord3)
	t.Help.FullKey = t.Help.ShortKey
	t.Help.FullDesc = t.Help.ShortDesc
	t.Help.FullSeparator = t.Help.ShortSeparator

	return t
}
