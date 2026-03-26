## Project Overview — WCAP

WCAP is the IT team's two-week planning tool.  Staff record where they will be, what they are focusing on, and their availability across a 14-day weekday grid.  Managers get visibility into coverage across teams and services.

### Domain Model

The core models and their relationships:

- **User** — has many PlanEntry records, belongs to many Teams, belongs to many Services.  Key flags: `is_admin`, `is_staff`.  Has defaults for location, category, and availability status.
- **PlanEntry** — one per user per date.  Tracks location, availability status (enum), category (enum), note, whether it's a holiday, and whether it was created by a manager.
- **Team** — has a manager (User via `manager_id`), has many members (Users via pivot).
- **Service** — has a manager (User via `manager_id`), has many members (Users via pivot).
- **Location** — name, short_label, `is_physical` boolean.

### Enums

- `AvailabilityStatus`: NOT_AVAILABLE (0), REMOTE (1), ONSITE (2) — has `label()`, `colour()`, `code()`, `isAvailable()` methods.
- `Category`: SUPPORT, PROJECT, ADMIN, LEAVE — has `label()`.

### Authorisation (three roles)

| Role    | How it's determined                          |
|---------|----------------------------------------------|
| Admin   | `User.is_admin` boolean                      |
| Manager | `User->managedTeams()->count() > 0`          |
| Staff   | Default authenticated user                   |

Blade directives: `@@admin`, `@@manager`, `@@adminOrManager`, `@@servicesEnabled` (and their `@@end` counterparts).

Middleware: `manager` (requires admin OR manager), plus Sanctum `ability`/`abilities` for API tokens.

Key helper: `User::canManagePlanFor(User $target)` — checks whether the current user can manage another user's plan.

### Routes at a glance

**Web** (all require auth):
- `/` — redirects by role
- `/profile` — personal defaults
- `/manager/*` — report, occupancy, entries, import (manager middleware)
- `/admin/*` — teams, services, locations, users (manager middleware)

**API** (`/api/v1`, Sanctum):
- Own plan CRUD, locations list (`view:own-plan`)
- Reports (`view:team-plans` or `view:all-plans`)
- Manager team-member plan management (`manage:team-plans`)

### Key Services

- `ManagerReportService` — team rows, location days, coverage matrix, service availability.
- `OccupancyReportService` — occupancy calculations and charts.
- `PlanEntryImport` / `PlanEntryRowValidator` — bulk import from Excel.

### Feature Flag

`config('wcap.services_enabled')` — toggles all service-related features (routes, UI sections, reports).

### Local Dev Quick Reference

- Lando: `lando start`, `lando artisan`, `lando test`, `lando mfs` (migrate:fresh + TestDataSeeder).
- Default login: `admin2x` / `secret`.
- Tests use in-memory SQLite via `RefreshDatabase` — no migrations or seeders needed.
- Roadmap: `PROJECT_PLAN.md` and `SERVICE_PLAN.md`.
