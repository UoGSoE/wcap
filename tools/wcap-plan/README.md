# wcap-plan

A small Python script for managers who'd rather edit their team's WCAP plan in
a text editor than click through the web UI.

The flow:

1. Fetch your team and their existing plan entries for the next two weeks.
2. Open the lot in `$EDITOR` as a YAML file — one section per person, one row
   per weekday.
3. You edit the cells you need to change. The 90% case is "everything's fine,
   save and quit".
4. Save and close — the script diffs against what was fetched, shows a summary
   of changes, and asks for confirmation before posting them back to the API.

## Requirements

- [`uv`](https://docs.astral.sh/uv/) for running the script. The script
  declares its Python version and dependencies inline (PEP 723), so `uv` will
  set up an ephemeral venv on first run.
- A WCAP API token with manager or admin role. Mint one at `/profile` in the
  WCAP web UI.

## Setup

```bash
# Required: your API token from /profile
export WCAP_TOKEN='1|abc...'

# Optional: defaults to https://wcap.lndo.site for local dev
export WCAP_BASE_URL='https://wcap.example.ac.uk'

# Optional: set to 1 to skip TLS verification (e.g. self-signed lando cert)
export WCAP_INSECURE=1
```

## Usage

```bash
./tools/wcap-plan/wcap-plan.py
# or
uv run tools/wcap-plan/wcap-plan.py
```

### Carry-forward mode (`--carry-forward` / `-c`)

For the typical "this fortnight is mostly the same as last fortnight" case, run:

```bash
./tools/wcap-plan/wcap-plan.py --carry-forward
```

Before opening the editor, the script also fetches the **previous** fortnight
for each team member and uses it to pre-fill any blank days in the new window —
matched by weekday, so last fortnight's Mondays project onto next fortnight's
Mondays, and so on.

The diff compares against what the API *currently* has (mostly blank), not
against the carried-forward defaults — so projected cells appear as `+ NEW` in
the change summary. You'll see something like:

```
Changes to apply:

  Adams, Jane (id 12):
    + 2026-04-27 Mon: NEW → location=rankine, availability=onsite, ...
    + 2026-04-28 Tue: NEW → location=jws, availability=onsite, ...
    ...

Apply these changes? [y/N]:
```

Tweak any holidays, training days, or odd weeks before saving — those edits ride
along with the projected entries in the same confirmation step.

The file format is YAML, one entry per line:

```yaml
- name: "Adams, Jane"
  id: 12
  days:
  - {date: 2026-04-27, day: Mon, location: rankine, availability: onsite, category: support, holiday: no, note: ""}
  - {date: 2026-04-28, day: Tue, location: rankine, availability: onsite, category: support, holiday: no, note: ""}
```

### Editing rules

- Use `~` for an empty location or category.
- Use `yes` / `no` for `holiday`.
- Don't change `date` or `id` fields — they're used to match entries to records.
- Quote notes that contain commas, colons, or other punctuation: `note: "Off, back Mon"`.

### Valid values

| Field          | Values                                       |
|----------------|----------------------------------------------|
| `location`     | any slug from `/api/v1/locations`, or `~`    |
| `availability` | `onsite`, `remote`, `not_available`          |
| `category`     | `support`, `project`, `admin`, `leave`, `~`  |
| `holiday`      | `yes`, `no`                                  |

The script validates these client-side before posting; the API will also
reject anything dodgy with a 422.

## What it does and doesn't do

- **Does**: only post entries that actually changed, per person. Idempotent —
  re-running with no edits posts nothing.
- **Does**: surface API errors verbatim so you can see what went wrong.
- **Does** (with `--carry-forward`): pre-fill blank days using last fortnight's
  pattern, matched by weekday. Without the flag, the editor shows exactly what
  the API has now.
- **Doesn't**: handle adding or removing people. The list is whoever the API
  reports as your manageable users at the moment you run it.

## Troubleshooting

- **`authentication failed (401)`** — token is wrong, expired, or revoked. Mint
  a new one.
- **`forbidden (403)`** — your role doesn't include manager or admin access to
  the API. Speak to whoever handles permissions.
- **TLS errors against a local lando install** — set `WCAP_INSECURE=1`.
- **Editor doesn't open / opens the wrong thing** — set `$EDITOR` explicitly,
  e.g. `EXPORT EDITOR=nano`.
