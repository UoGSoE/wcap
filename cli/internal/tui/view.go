package tui

import (
	"fmt"
	"strings"
	"time"

	"github.com/charmbracelet/lipgloss"

	"github.com/wcap/cli/internal/api"
	"github.com/wcap/cli/internal/theme"
)

const (
	minWidth     = 60
	minLeftWidth = 22
	maxLeftWidth = 32
)

// View renders the current state. It returns a single string for bubbletea.
func (m Model) View() string {
	if m.width == 0 {
		return "Loading…"
	}

	switch m.mode {
	case modeFatal:
		return m.viewFatal()
	case modeLoading:
		return m.viewLoading()
	case modeEdit:
		return m.viewEdit()
	case modeHelp:
		return m.viewHelp()
	}

	return m.viewList()
}

func (m Model) viewFatal() string {
	box := lipgloss.NewStyle().
		Foreground(theme.Error).
		Bold(true).
		Padding(1, 2).
		Border(lipgloss.RoundedBorder()).
		BorderForeground(theme.Error).
		Render("Error\n\n" + m.err + "\n\nPress q to quit.")
	return lipgloss.Place(m.width, m.height, lipgloss.Center, lipgloss.Center, box)
}

func (m Model) viewLoading() string {
	msg := theme.Faint.Render("Loading wcap…")
	return lipgloss.Place(m.width, m.height, lipgloss.Center, lipgloss.Center, msg)
}

func (m Model) viewEdit() string {
	if m.form == nil {
		return ""
	}
	header := theme.Title.Render("Edit plan entry")
	body := m.form.View()
	help := theme.Faint.Render("Enter — save · Esc — cancel · Tab — next field")
	return strings.Join([]string{header, "", body, "", help}, "\n")
}

func (m Model) viewHelp() string {
	help := `
  wcap — keyboard reference

  Pane navigation
    Tab / Shift-Tab   Switch focus left/right
    h / l             Focus left / right
    j / k             Move within pane
    g / G             First / last row
    1-9               Jump to row N

  Members (left pane)
    Enter             Load that person's plan
    /                 Filter members
    Esc               Clear filter / status

  Plan (right pane)
    Enter / e         Edit selected day
    c                 Copy this day onto the next
    C                 Copy this day onto all following days
    ] / [             Next / previous week
    r                 Reload from server

  Edit form
    Tab               Next field
    Enter             Save (or advance, then Enter again)
    Esc               Cancel

  Everywhere
    ?                 This help screen
    q                 Quit
`
	return lipgloss.NewStyle().Padding(1, 2).Render(strings.TrimSpace(help))
}

func (m Model) viewList() string {
	leftWidth := computeLeftWidth(m.width, m.hideLeftPane)
	rightWidth := m.width - leftWidth
	if !m.hideLeftPane {
		rightWidth = m.width - leftWidth - 3 // separator " │ "
	}

	bodyHeight := m.height - 3 // header + footer + spacer
	if bodyHeight < 5 {
		bodyHeight = 5
	}

	left := ""
	if !m.hideLeftPane {
		left = m.renderLeftPane(leftWidth, bodyHeight)
	}
	right := m.renderRightPane(rightWidth, bodyHeight)

	var body string
	if m.hideLeftPane {
		body = right
	} else {
		sep := renderSeparator(bodyHeight)
		body = lipgloss.JoinHorizontal(lipgloss.Top, left, sep, right)
	}

	return strings.Join([]string{
		m.renderHeader(),
		body,
		m.renderFooter(),
	}, "\n")
}

func computeLeftWidth(total int, hideLeft bool) int {
	if hideLeft {
		return 0
	}
	w := total / 4
	if w < minLeftWidth {
		w = minLeftWidth
	}
	if w > maxLeftWidth {
		w = maxLeftWidth
	}
	if w > total-30 {
		w = total - 30
	}
	if w < 0 {
		w = 0
	}
	return w
}

func (m Model) renderHeader() string {
	left := "wcap"
	right := ""
	if m.selfName != "" {
		right = "signed in as " + m.selfName
	}
	pad := m.width - lipgloss.Width(left) - lipgloss.Width(right)
	if pad < 1 {
		pad = 1
	}
	line := theme.Title.Render(left) + strings.Repeat(" ", pad) + theme.Faint.Render(right)
	return line
}

func (m Model) renderFooter() string {
	keys := "j/k move · Tab pane · e edit · c copy-next · C copy-rest · ] next-wk · [ prev-wk · / filter · r reload · ? help · q quit"
	if m.pane == paneLeft {
		keys = "j/k move · Enter open · / filter · Tab pane · ? help · q quit"
	}
	if m.mode == modeFilter {
		keys = fmt.Sprintf("filter: %s_  (Enter accept · Esc cancel)", m.filter)
	}

	keyLine := padToWidth(theme.Faint.Render(keys), m.width)

	if m.status == "" {
		return keyLine
	}

	style := theme.Faint
	switch m.statusKind {
	case statusGood:
		style = theme.StatusGood
	case statusWarn:
		style = theme.StatusWarn
	case statusBad:
		style = theme.StatusError
	}
	statusLine := padToWidth(style.Render(m.status), m.width)
	return statusLine + "\n" + keyLine
}

func renderSeparator(height int) string {
	col := strings.Repeat(theme.Faint.Render("│")+"\n", height)
	col = strings.TrimSuffix(col, "\n")
	return " " + col + " "
}

// ---------------------------------------------------------------------------
// Left pane (members)

func (m Model) renderLeftPane(width, height int) string {
	var lines []string

	title := "Team"
	if !m.canManage {
		title = "You"
	}
	lines = append(lines, padToWidth(theme.Title.Render(title), width))
	lines = append(lines, padToWidth(theme.Faint.Render(strings.Repeat("─", width)), width))

	for i, idx := range m.filteredIdx {
		mem := m.members[idx]
		label := mem.Name
		if mem.ID == m.selfID {
			label += " (me)"
		}
		selected := i == m.memberCursor
		isFocused := m.pane == paneLeft

		var line string
		switch {
		case selected && isFocused:
			// Plain "▸" — row-wide Selected style will recolour it.
			line = theme.Selected.Render(padToWidth("▸ "+truncate(label, width-2), width))
		case selected:
			line = padToWidth(theme.Faint.Render("▸ ")+truncate(label, width-2), width)
		default:
			line = padToWidth("  "+truncate(label, width-2), width)
		}
		lines = append(lines, line)
	}

	for len(lines) < height {
		lines = append(lines, padToWidth("", width))
	}
	if len(lines) > height {
		lines = lines[:height]
	}
	return strings.Join(lines, "\n")
}

// ---------------------------------------------------------------------------
// Right pane (plan)

func (m Model) renderRightPane(width, height int) string {
	var lines []string

	title := "Plan"
	if m.planUser.Name != "" {
		title = "Plan: " + m.planUser.Name
		if m.planUser.ID == m.selfID {
			title += " (you)"
		}
	}
	if m.dateRange.Start != "" {
		title += theme.Faint.Render(fmt.Sprintf("   %s → %s", m.dateRange.Start, m.dateRange.End))
	}
	lines = append(lines, padToWidth(theme.Title.Render(title), width))
	lines = append(lines, padToWidth(theme.Faint.Render(strings.Repeat("─", width)), width))

	if len(m.days) == 0 {
		lines = append(lines, theme.Faint.Render("No days in this window."))
	} else {
		for i, day := range m.days {
			line := m.renderDayRow(day, i, width)
			lines = append(lines, line)
		}
	}

	for len(lines) < height {
		lines = append(lines, padToWidth("", width))
	}
	if len(lines) > height {
		lines = lines[:height]
	}
	return strings.Join(lines, "\n")
}

func (m Model) renderDayRow(day time.Time, idx, width int) string {
	dayLabel := day.Format("Mon  2 Jan")
	entry := api.Entry{EntryDate: day.Format("2006-01-02"), AvailabilityStatus: api.Onsite}
	if idx < len(m.entries) {
		entry = m.entries[idx]
	}

	statusGlyph, statusLabel, statusStyle := statusBadge(entry.AvailabilityStatus)
	locLabel := entry.LocationLabel
	if locLabel == "" && entry.Location != "" {
		locLabel = entry.Location
	}
	if locLabel == "" {
		locLabel = "—"
	}
	note := entry.Note
	if note == "" {
		note = theme.Faint.Render("—")
	}

	// Layout: <2 ch indicator><10 ch date><10 ch status><14 ch location><note>
	const (
		dateCol   = 13
		statusCol = 14
		locCol    = 16
	)

	focused := m.pane == paneRight && idx == m.dayCursor
	selected := idx == m.dayCursor

	if focused {
		// Plain text, no nested ANSI — Selected style applies to the whole row.
		plain := strings.Join([]string{
			"▸ ",
			padToWidth(dayLabel, dateCol),
			padToWidth(statusGlyph+" "+statusLabel, statusCol),
			padToWidth(truncate(locLabel, locCol-1), locCol),
			truncate(entry.Note, max(1, width-2-dateCol-statusCol-locCol)),
		}, "")
		plain = truncate(plain, width)
		return theme.Selected.Render(padToWidth(plain, width))
	}

	indicator := "  "
	if selected {
		indicator = theme.Faint.Render("▸ ")
	}
	parts := []string{
		indicator,
		padToWidth(dayLabel, dateCol),
		padToWidth(statusStyle.Render(statusGlyph+" "+statusLabel), statusCol),
		padToWidth(truncate(locLabel, locCol-1), locCol),
		truncate(note, max(1, width-2-dateCol-statusCol-locCol)),
	}
	row := strings.Join(parts, "")
	return padToWidth(truncate(row, width), width)
}

func statusBadge(s api.AvailabilityStatus) (glyph, label string, style lipgloss.Style) {
	switch s {
	case api.Onsite:
		return "●", "Onsite ", theme.OnsiteFg
	case api.Remote:
		return "◐", "Remote ", theme.RemoteFg
	default:
		return "○", "Away   ", theme.UnavailFg
	}
}

// ---------------------------------------------------------------------------
// String helpers

func padToWidth(s string, w int) string {
	if w <= 0 {
		return ""
	}
	vw := lipgloss.Width(s)
	if vw >= w {
		return s
	}
	return s + strings.Repeat(" ", w-vw)
}

func truncate(s string, w int) string {
	if w <= 0 {
		return ""
	}
	if lipgloss.Width(s) <= w {
		return s
	}
	// Cheap rune-aware truncate; ANSI styles in strings are kept intact at
	// the cost of slight over-width when escape codes are present.
	runes := []rune(s)
	if len(runes) > w {
		return string(runes[:w-1]) + "…"
	}
	return s
}

// stripANSI is intentionally a no-op for now — left as a hook in case we want
// to drop styling before applying a row-wide highlight.
func stripANSI(s string) string { return s }

func max(a, b int) int {
	if a > b {
		return a
	}
	return b
}
