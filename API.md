# WCAP API

A small JSON API over the planning data, designed to be useful from PowerBI, Excel,
ad-hoc scripts, and AI agents pointed at the app with a Sanctum token.

The full, live OpenAPI specification is at **[`/docs/api`](#)** when you're logged
in to the app. This file just covers the bits the spec doesn't (or shouldn't) say.

## Authentication

All endpoints sit behind Sanctum (`auth:sanctum`).

1. Sign in to the app.
2. Mint a personal access token from your profile page.
3. Send it as `Authorization: Bearer <token>` on each request, with
   `Accept: application/json`.

Access is decided by your **role** on the user record, not by token abilities. A
plain token is enough — staff get personal-plan endpoints, managers/admins also
get the manager and report endpoints.

## Endpoint overview

Everything lives under `/api/v1`.

| Endpoint | Who can call it |
|---|---|
| `GET /plan` — your next two weeks | any signed-in user |
| `POST /plan` — upsert your entries | any signed-in user |
| `DELETE /plan/{id}` — remove one of your entries | any signed-in user |
| `GET /locations` — reference data | any signed-in user |
| `GET /reports/team` — person × day grid | manager, admin |
| `GET /reports/location` — day × location grouping | manager, admin |
| `GET /reports/coverage` — location × day counts | manager, admin |
| `GET /reports/service-availability` — service × day counts | manager, admin (when services enabled) |
| `GET /manager/team-members` — list manageable users | manager, admin |
| `GET /manager/team-members/{id}/plan` — view their plan | manager, admin |
| `POST /manager/team-members/{id}/plan` — upsert their plan | manager, admin |
| `DELETE /manager/team-members/{id}/plan/{entryId}` | manager, admin |

For exact request/response shapes, parameters, and a Try-It console, go to
`/docs/api` once you're signed in.

## Conventions worth knowing up front

These are the things that catch consumers out, so they're called out here rather
than buried in field-level docs.

### Responses are always wrapped in named keys

Every response is a JSON object with named top-level keys (`entries`,
`team_rows`, `coverage_matrix`, `days`, …). You will never get a bare array at
the top level. If you're binding in PowerBI or letting an agent walk the
response, you can rely on the key being there.

### Codes and labels both come back

Anywhere there's an enum-ish value, the API returns both the machine value and
a human label so you don't have to keep your own translation table:

```json
{
  "availability_status": 2,
  "availability_status_label": "On site",
  "category": "support",
  "category_label": "Support",
  "location": "rankine",
  "location_label": "Rankine"
}
```

Filter and write using the code. Display using the label.

### Slugs, not IDs, are the public references

Locations and services are referenced by slug (`rankine`, `vpn-service`) rather
than database ID. Slugs are stable across environments and human-friendly to
type. You can fetch the master list from `GET /locations`.

### Dates are always `YYYY-MM-DD`

Date fields are date-only strings — no time, no timezone. There are tests
guarding this so it won't drift.

### `scope` tells you what data you're seeing

Report responses carry a `scope` field — `"all"`, `"team"`, or `"global"` —
that describes the slice of users being reported on. Useful for AI agents that
need to honestly answer "how many of our staff are on site this week?" without
silently confusing "in your team" with "in the whole organisation".

### Filtering: `?filter[name]=value`

All filterable endpoints use Spatie-style filters:

```
GET /api/v1/reports/coverage?filter[location_slug]=rankine&filter[is_physical]=true
```

If you pass a filter name the endpoint doesn't recognise you'll get a `400`
with a message listing the allowed filters. This is deliberate — it's
discoverable, and an AI agent can read the error and self-correct.

### Date windows: `filter[from]` and `filter[to]` together

Endpoints that take a date window default to the next ten weekdays. To override:

```
GET /api/v1/plan?filter[from]=2026-04-20&filter[to]=2026-05-15
```

Rules:

- Both must be supplied together. Just one is a `400`.
- ISO format only (`YYYY-MM-DD`).
- `from` must be on or before `to`.
- Maximum span of 62 days.

### Sparse fieldsets: `?fields[entries]=…`

Where supported (`/plan` is the obvious one) you can ask for only the fields
you care about:

```
GET /api/v1/plan?fields[entries]=entry_date,location,note
```

Unknown fields return a `400` with the offender named, so you can probe what
exists by trying.

### Errors

- `400` — your filter, fieldset, or date window was malformed. Body has a
  human-readable `message`.
- `401` — no token, or invalid token.
- `403` — you don't have the role this endpoint requires.
- `404` — record missing, or you're trying to access something that isn't
  yours.
- `422` — validation error on a write. Standard Laravel validation envelope
  (`message`, `errors[field][]`).

## Examples

A staff user pulling their own next two weeks:

```
GET /api/v1/plan
Authorization: Bearer <token>
Accept: application/json
```

A manager pulling location coverage for a specific week, on-campus locations
only:

```
GET /api/v1/reports/coverage?filter[from]=2026-04-20&filter[to]=2026-04-24&filter[is_physical]=true
```

A manager booking someone in for a day:

```
POST /api/v1/manager/team-members/42/plan
Content-Type: application/json
Accept: application/json
{
  "entries": [
    { "entry_date": "2026-04-22", "location": "rankine", "note": "Lab visit" }
  ]
}
```

## Where the docs live

- This file — high-level conventions and gotchas.
- `/docs/api` (live, Stoplight) — every endpoint, every parameter, with a
  Try-It console.
- `api.json` (downloadable from the docs page) — the OpenAPI spec for tooling.
