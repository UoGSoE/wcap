# wcap

A terminal UI for the [WCAP](../) planning API. Two-pane layout: team members
on the left, your fourteen-day plan on the right. Edits go straight back over
the existing `/api/v1/*` endpoints — no extra backend.

```
┌─ wcap ─────────────────────────────────────────────────────────────────┐
│ Team                  │ Plan: Bilbo Baggins (you)   2026-05-25 → 2026-06-05
│ ▸ Bilbo Baggins (me)  │ ──────────────────────────────────────────────
│   Frodo Baggins       │   Mon  25 May   ● Onsite    Rankine     Sprint kick-off
│   Sam Gamgee          │   Tue  26 May   ● Onsite    Rankine
│   Merry Brandybuck    │ ▸ Wed  27 May   ◐ Remote    —           Writing day
│   Pippin Took         │   Thu  28 May   ● Onsite    Boyd Orr
│                       │   Fri  29 May   ○ Away      —           Annual leave
│                       │   Mon   1 Jun   ● Onsite    Rankine
│                       │   …
└────────────────────────────────────────────────────────────────────────┘
 j/k move · Tab pane · e edit · c copy-next · C copy-rest · ] next-wk · ? help · q quit
```

## Install

Static binaries, no runtime dependencies (CentOS 8 included).

```sh
cd cli
make build              # ./wcap on the host arch
make build-linux        # dist/wcap-linux-amd64
make build-mac          # dist/wcap-darwin-{arm64,amd64}
make build-all          # all of the above
```

Drop the binary somewhere on your `$PATH`. That's it.

## Configure

```sh
wcap config
```

Prompts for the base URL and a Sanctum personal access token (mint one from
your profile page in the web UI). The token is verified against `/api/user`
before anything gets written. Settings live at the platform's native config
dir with `0600` permissions:

- **macOS:** `~/Library/Application Support/wcap/config.yaml`
- **Linux:** `~/.config/wcap/config.yaml` (or `$XDG_CONFIG_HOME/wcap/`)
- **Windows:** `%AppData%\wcap\config.yaml`

`wcap config` prints the exact path it wrote to after a successful save.

### Encrypt the token at rest (optional)

`wcap config` ends with an optional "Passphrase" field. Leave it blank to
store the token in plaintext (same as before). Type a passphrase (4+
characters) to encrypt the token on disk; from then on every `wcap`
launch prompts for it before doing anything network.

Encryption details (for the security team's benefit):

- **KDF:** Argon2id, `time=1, memory=32 MiB, threads=2, keyLen=32`.
- **AEAD:** ChaCha20-Poly1305.
- **Salt:** 16 random bytes per encryption, stored alongside the ciphertext.
- **Nonce:** 12 random bytes per encryption.
- **On-disk format:** `v1:<base64(salt || nonce || ciphertext+tag)>` stored
  in `encrypted_token`. The version prefix lets us migrate algorithms later
  without touching existing files.
- **Where the key lives:** in your head. Never written to disk, never
  cached between invocations.

Three wrong passphrase attempts and `wcap` exits. For CI / scripted usage,
set `WCAP_PASSPHRASE` and the prompt is skipped. (`WCAP_TOKEN` also still
wins over the file entirely — handy for one-off staging access.)

You can switch a config back to plaintext (or vice versa) by re-running
`wcap config`.

Environment overrides win over the file:

```sh
export WCAP_BASE_URL=https://wcap-staging.example.glasgow.ac.uk
export WCAP_TOKEN=...
wcap
```

## Use

`wcap` (no args) launches the TUI.

### Keys

| Key | Action |
|---|---|
| `Tab` / `Shift-Tab` | Switch focus left/right |
| `h` / `l` | Focus left / right pane |
| `j` / `k` (or arrows) | Move within pane |
| `g` / `G` | First / last row |
| `1`–`9` | Jump to row N |
| `/` | Filter members (Esc clears, Enter accepts) |
| `Enter` (left) | Load that person's plan |
| `Enter` / `e` (right) | Edit selected day |
| `c` | Copy this day onto the next |
| `C` | Copy this day onto every following day |
| `]` / `[` | Next / previous two-week window |
| `r` | Reload from server |
| `?` | Help screen |
| `q` / `Ctrl-C` | Quit |

### Editing

Select a day, press `e`. A three-field form opens:

1. **Availability** — Onsite / Remote / Not available.
2. **Location** — picked from the locations the API hands us. Required when
   you're available; the form refuses to submit otherwise (mirrors the
   server-side rule).
3. **Note** — freeform text, same field as the website's "What…" input.

`Tab` between fields, `Enter` to save, `Esc` to cancel. The save goes via
`POST /api/v1/plan` for your own row or `POST /api/v1/manager/team-members/{id}/plan`
for a team member's, then the plan is refetched so you're looking at the
authoritative state.

### Who you see

- Staff with a token: just your own plan. The left pane hides itself; you can
  still edit your own days.
- Managers / admins: every user the API surfaces via
  `/api/v1/manager/team-members`. Self pinned to the top, the rest sorted by
  surname.

## Subcommands

```
wcap            launch the TUI
wcap config     set base URL and token (interactive)
wcap version    print version + git revision
wcap help       show usage
```

## Build details

- Pure Go, `CGO_ENABLED=0`. No glibc dependency, runs on CentOS 8 onwards.
- Single statically-linked binary (~7 MB stripped).
- Tested with `go test ./...` — covers the API client, config round-trip, and
  the date / model helpers. The TUI itself is exercised by hand.

```sh
make test
make fmt vet
```

## Layout

```
cli/
  main.go                  # subcommand dispatch
  internal/
    api/                   # typed HTTP client (six endpoints)
    config/                # ~/.config/wcap/config.yaml round-trip
    theme/                 # Nord palette + huh theme
    tui/                   # Bubble Tea model / update / view + edit form
```

## Troubleshooting

- **`unauthorized — token invalid or expired`** — your Sanctum token has been
  revoked or rotated. Re-run `wcap config`.
- **The left pane is missing** — you're authenticated as staff. The API
  refuses `/manager/team-members` for non-managers, so the TUI hides the
  pane. Your own plan is still editable.
- **`no config found`** — run `wcap config` once before launching the TUI, or
  set `WCAP_BASE_URL` / `WCAP_TOKEN`.
- **Rendering looks off in tmux** — the TUI assumes a 24-bit-colour terminal.
  Inside tmux you may need `tmux -2` or `set -g default-terminal "tmux-256color"`.
