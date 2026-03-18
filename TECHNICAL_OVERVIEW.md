# Technical Overview

Last updated: 2026-01-08

## What This Is

WCAP is the IT team's two-week planning tool for capturing who is working, what they are focusing on, and where they will be located. Staff keep their plans up to date while managers get visibility into coverage across teams and services.

## Stack

- PHP 8.4 / Laravel 12 (streamlined structure)
- Livewire 3 + Flux UI Pro
- Tailwind CSS 4
- Pest 4 for testing
- Sanctum for API auth
- Lando for local dev

## Directory Structure

```
app/
├── Enums/           # AvailabilityStatus, Category (with label/colour/code methods)
├── Exports/         # Excel exports (Maatwebsite) - ManagerReport, Occupancy, TeamPlan
├── Http/
│   ├── Controllers/
│   │   ├── Api/     # PlanController, ManagerPlanController, ReportController
│   │   └── Auth/    # SSOController
│   ├── Middleware/  # ManagerMiddleware
│   └── Requests/    # UpsertPlanEntriesRequest, ManagerUpsertPlanEntriesRequest
├── Livewire/        # All page components (see Routes below)
├── Models/          # User, Team, PlanEntry, Location, Service
├── Providers/       # AppServiceProvider (Blade directives), SSOServiceProvider
└── Services/        # ManagerReportService, OccupancyReportService, PlanEntryImport

config/
└── wcap.php         # App-specific config (services_enabled feature flag)

database/
├── factories/       # All models have factories; User has ->admin() state
└── seeders/         # Use TestDataSeeder for local dev (not DatabaseSeeder)
```

## Domain Model

```
User ←→ Team          (many-to-many via team_user pivot)
Team.manager_id → User

User → PlanEntry      (one-to-many)
PlanEntry → Location  (belongs-to)

User ←→ Service       (many-to-many via service_user pivot)
Service.manager_id → User
```

### Key Model Fields

**User**: username, surname, forenames, email, is_admin, is_staff, default_location_id, default_category, default_availability_status

**PlanEntry**: user_id, entry_date, location_id, availability_status (enum), category (enum), note, is_holiday, created_by_manager

**Team**: name, manager_id

**Location**: name, short_label, is_physical

**Service**: name, manager_id

### Enums

- `AvailabilityStatus`: NOT_AVAILABLE (0), REMOTE (1), ONSITE (2)
- `Category`: SUPPORT, PROJECT, ADMIN, LEAVE

AvailabilityStatus has `label()`, `colour()`, `code()`, `isAvailable()` methods. Category has `label()`.

## Authorization

| Role | How it's determined |
|------|---------------------|
| Admin | `User.is_admin` boolean |
| Manager | `User->managedTeams()->count() > 0` |
| Staff | Default authenticated user |

### Blade Directives (AppServiceProvider)

- `@admin` / `@endadmin`
- `@manager` / `@endmanager`
- `@adminOrManager` / `@endadminOrManager`
- `@servicesEnabled` / `@endservicesEnabled`

### Middleware (bootstrap/app.php)

- `manager` - requires admin OR manager role
- `ability` / `abilities` - Sanctum token ability checks

### Authorization Helpers

- `User::isAdmin()` - checks is_admin flag
- `User::isManager()` - checks managedTeams count
- `User::canManagePlanFor(User $target)` - can this user manage another user's plan?

## Routes Overview

### Web Routes (routes/web.php)

All require auth:

| Route | Handler | Access |
|-------|---------|--------|
| `/` | HomeRedirectController | All (redirects by role) |
| `/profile` | Profile (Livewire) | All |
| `/manager/report` | ManagerReport | manager middleware |
| `/manager/occupancy` | OccupancyReport | manager middleware |
| `/manager/entries` | ManageTeamEntries | manager middleware |
| `/manager/import` | ImportPlanEntries | manager middleware |
| `/admin/teams` | AdminTeams | manager middleware |
| `/admin/services` | AdminServices | manager middleware |
| `/admin/locations` | AdminLocations | manager middleware |
| `/admin/users` | AdminUsers | manager middleware |

### API Routes (routes/api.php)

All under `/api/v1`, protected by Sanctum.

**Token Abilities:**
- `view:own-plan` - all users
- `view:team-plans` - managers
- `manage:team-plans` - managers
- `view:all-plans` - admins

| Endpoint | Ability Required |
|----------|------------------|
| `GET/POST/DELETE /plan` | view:own-plan |
| `GET /locations` | view:own-plan |
| `GET /reports/*` | view:team-plans OR view:all-plans |
| `/manager/team-members/*` | manage:team-plans |

## Key Business Logic

| Location | Purpose |
|----------|---------|
| `App\Services\ManagerReportService` | Builds team rows, location days, coverage matrix, service availability |
| `App\Services\OccupancyReportService` | Occupancy calculations and charts |
| `App\Services\PlanEntryImport` | Bulk import from Excel |
| `App\Services\PlanEntryRowValidator` | Validates import rows |
| `User::canManagePlanFor()` | Authorization check for plan management |
| `PlanEntry::isAvailable()` | Delegates to AvailabilityStatus enum |

## Testing

- Framework: Pest 4 with RefreshDatabase trait (in-memory SQLite)
- Pattern: Feature tests using `Livewire::test()` and `actingAs()`
- Factories: All models have factories; `User::factory()->admin()` for admin users
- Run: `php artisan test --compact` or filter with `--filter=testName`

## Feature Flags

- `config('wcap.services_enabled')` - toggles service-related features (routes, UI sections, reports)

## Local Development

- Uses Lando (`lando start`, `lando artisan`, `lando test`)
- Reset data: `lando mfs` (migrate:fresh + TestDataSeeder)
- Default login: `admin2x` / `secret`
