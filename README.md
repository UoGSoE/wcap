# WCAP

WCAP is the IT team's two-week planning tool for capturing who is working, what they are focusing on, and where they will be located. The MVP delivers a lightweight way for staff to keep their plans up to date, while giving managers visibility into coverage across teams and services.

## Highlights
- **14-day planner** – Weekday-focused grid with live updates, availability toggle, location picker, and quick “copy next/rest” helpers to keep plans fast to update.
- **Personal defaults** – Profile screen for setting preferred location and task category that pre-fills new plan entries.
- **Team & service admin** – Flux-powered CRUD for teams, services, managers, and memberships to keep grouping data accurate.
- **Manager reporting** – Coverage dashboard with location summaries, service availability matrix, and Excel export for sharing snapshots.
- **Seeded demo data** – `TestDataSeeder` builds realistic teams, services, and plan entries so new installs have something meaningful to explore.

## Tech Stack
- Laravel 12 using the streamlined application structure introduced in recent releases
- Livewire 3 + Flux UI Pro for reactive Blade components
- Tailwind CSS 4 for styling
- Pest 4 for automated testing
- Lando for the local development environment

## Getting Started

### Prerequisites
- [Lando](https://lando.dev/) (which in turn requires Docker Desktop or a compatible Docker engine)
- Node.js 20+ and npm (only needed for building assets before the first `lando start`)

### First-time Setup
1. Clone the repository:
   ```bash
   git clone https://github.com/UoGSoE/wcap.git
   cd wcap
   ```
2. Create your environment file:
   ```bash
   cp .env.example .env
   ```
3. Install PHP dependencies (on the host machine):
   ```bash
   composer install
   ```
4. Install frontend dependencies and build the production assets:
   ```bash
   npm install
   npm run build
   ```
5. Start the Lando environment:
   ```bash
   lando start
   ```
6. Run migrations and seed the demo data (the custom `mfs` tooling command runs `migrate:fresh` and the `TestDataSeeder`):
   ```bash
   lando mfs
   ```

Lando will print the accessible URLs when the stack boots (by default `https://wcap.lndo.site`). MailHog is available for local email testing as part of the recipe.

### First Login
- **Username**: `admin2x`
- **Password**: `secret`

Use this account to explore the planner, admin consoles, and manager tools. Update the credentials in your local database if you need alternative logins.

## Day-to-Day Development
- Start/stop the environment with `lando start` / `lando stop`.
- Run Laravel commands inside the container, e.g. `lando artisan test` or the shortcut `lando test` for the full parallel suite.
- Keep code style aligned with `lando vendor/bin/pint -- --dirty` before committing.
- Watch assets during development with either `npm run dev` on the host or `lando npm run dev` inside the Node service.
- Reset data at any time with `lando mfs`.

## Roadmap & Planning
The active roadmap lives in [`PROJECT_PLAN.md`](PROJECT_PLAN.md) and service work is tracked in [`SERVICE_PLAN.md`](SERVICE_PLAN.md). These documents outline completed milestones, upcoming features (like API endpoints and expanded reporting), and nice-to-have ideas for future phases.

## License
This project is licensed under the [MIT License](LICENSE).
