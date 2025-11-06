# Time Tracker Implementation Plan

## 🎯 PRIME DIRECTIVE: SIMPLICITY FIRST

**The team's cardinal rule: Keep it simple until you know otherwise.**

- **No premature optimization** - Don't create variables "just in case"
- **No premature abstraction** - Don't extract code until there's duplication
- **Chain when possible** - Use `auth()->user()->update()` not `$user = auth()->user(); $user->update()`
- **Write the simplest thing that works** - If you need complexity later, refactor then
- **YAGNI (You Aren't Gonna Need It)** - Don't build features before they're required

This applies to all code, all decisions, all features. Simple code is maintainable code.

---

## Project Overview
Building a two-week time tracking form for the IT team where users can plan what they'll be working on and where they'll be located.

## Requirements
- Note field is **optional** (empty = "no idea")
- Location field is **required**
- Copy buttons **overwrite** existing data in target days
- Show **loading state** during save + **success message** after

---

## Implementation Checklist

### 1. Fix Migration Issue
- [X] Fix `team_user` table name mismatch in migration (create uses `team_user`, drop uses `team_users`)

### 2. Create Models

#### PlanEntry Model
- [X] Generate `PlanEntry` model using artisan (already exists as stub)
- [X] Add `belongsTo` relationship to User
- [X] Add `casts()` method for Location enum and dates
- [X] Set up `$fillable` attributes
- [X] Create factory (already exists as stub)
- [X] Add realistic fake data to factory

#### Team Model
- [X] Generate `Team` model using artisan (already exists as stub)
- [X] Add `belongsToMany` relationship to User (via team_user pivot)
- [X] Add `belongsTo` relationship for manager (User)
- [X] Set up `$fillable` attributes
- [X] Create factory (already exists as stub)
- [X] Add realistic data to factory

### 3. Update HomePage Livewire Component

- [X] Add public properties for storing 14 days of entry data (array structure with id tracking)
- [X] Implement `mount()` method to load existing entries from database
- [X] Implement `save()` method to persist all 14 days at once (using id-based updateOrCreate)
- [X] Implement `copyNext($dayIndex)` method to copy entry to next day
- [X] Implement `copyRest($dayIndex)` method to copy entry to all remaining days
- [X] Add success notification after save (using Flux::toast())

### 4. Update Blade Template (home-page.blade.php)

- [X] Add `wire:model.live` bindings to note inputs (e.g., `entries.0.note`)
- [X] Add `wire:model.live` bindings to location selects (e.g., `entries.0.location`)
- [X] Add `wire:click="save"` to Save button
- [X] Add `wire:loading` state to Save button
- [X] Add `wire:click="copyNext(index)"` to "Copy next" buttons
- [X] Add `wire:click="copyRest(index)"` to "Copy rest" buttons
- [X] Add `wire:key` to the day loop for proper reactivity
- [X] Success toast handled via Flux::toast() (no separate component needed)

### 5. Create Form Request for Validation

- [X] Validation implemented inline in HomePage component (not separate Form Request)
- [X] Add validation rules:
  - `entries.*.id`: nullable, integer, exists
  - `entries.*.note`: nullable
  - `entries.*.location`: required (must be valid Location enum value)
  - `entries.*.entry_date`: required, date format
- [X] Add custom error messages for better UX

### 6. Write Tests

#### Feature Tests
- [X] Test: Rendering the page shows 14 days starting from Monday
- [X] Test: Saving new entries creates database records
- [X] Test: Saving updates existing entries (not duplicates) - fixed with id-based approach!
- [X] Test: Copy next functionality copies to the next day only
- [X] Test: Copy rest functionality copies to all remaining days
- [X] Test: Validation requires location field
- [X] Test: Validation allows empty note field
- [X] Test: Existing entries are loaded on mount

### 7. Run Tests & Format Code

- [X] Run relevant feature tests with `--filter`
- [X] All 8 tests pass with 48 assertions!
- [X] Run `vendor/bin/pint` to format all modified PHP files

---

## Implementation Notes: What We Actually Did

### The Journey & Key Learnings

#### 1. The Date Matching Problem (And The Elegant Solution!)
**Issue**: Initially used `updateOrCreate` with `user_id` and `entry_date` as the matching criteria. This caused duplicates because Laravel's date casting and string comparison weren't matching existing records properly - we got 15 entries instead of 14!

**Solution**: Stored the `id` field in the `$entries` array on mount. Then used `updateOrCreate(['id' => $entry['id']], [...])`. When `id` is null, it creates a new record. When `id` exists, it updates that specific record. Much cleaner and more reliable!

```php
// In mount() - store the id
$this->entries[$index] = [
    'id' => $existing?->id,  // ← This was the key insight!
    'entry_date' => $dateKey,
    'note' => $existing?->note ?? '',
    'location' => $existing?->location?->value ?? '',
];

// In save() - use the id to match
PlanEntry::updateOrCreate(
    ['id' => $entry['id']],  // ← Matches by id, or null = create
    [/* attributes to update/create */]
);
```

#### 2. Flux UI - Toast Notifications
**Key Learning**: Flux Pro has built-in toast functionality that's triggered from PHP, not from Blade templates!

```php
// In the Livewire component
use Flux\Flux;

Flux::toast(
    heading: 'Success!',
    text: 'Your plan has been saved successfully.',
    variant: 'success'
);
```

**No need for**:
- `<flux:toast wire:show="event-name" />` in the template
- `$this->dispatch('event-name')` in the component
- Any JavaScript or Alpine.js code

The toast just appears automatically! Much simpler than expected.

#### 3. Flux UI - Automatic Error Handling
**Key Learning**: Flux components automatically display validation errors when using Livewire's `$this->validate()`. You don't need to add explicit error blocks!

```blade
{{-- This is all you need --}}
<flux:input wire:model.live="entries.{{ $index }}.note" />
<flux:select wire:model.live="entries.{{ $index }}.location">
    {{-- options --}}
</flux:select>

{{-- NO NEED FOR: --}}
@error("entries.{$index}.location")
    <flux:error>{{ $message }}</flux:error>
@enderror
```

Flux handles the error display automatically based on the validation rules in your Livewire component.

#### 4. Category Enum - Deliberately Omitted
**Decision**: The Category enum exists in the database migration but we intentionally didn't use it in v1. The reasoning:
- Team doesn't yet know what categories they need
- Better to let them enter free-form text for a while
- Analyze the actual data to inform better category choices later
- Can add the dropdown in v2 once we have real-world data

**In the code**:
- PlanEntry model has `category` in `$fillable` but no enum cast
- Factory sets `category` to `null`
- Blade template has no category field

#### 5. Form Request Pattern
**Decision**: Used inline validation in the Livewire component instead of creating a separate Form Request class.

**Why**: For Livewire components, inline validation is often cleaner and more straightforward than Form Requests. Form Requests are great for traditional controllers but can feel like overkill for Livewire.

```php
// Simple and clear in the component
$validated = $this->validate([
    'entries.*.id' => 'nullable|integer|exists:plan_entries,id',
    'entries.*.note' => 'nullable|string',
    'entries.*.location' => 'required|string',
    'entries.*.entry_date' => 'required|date',
], [
    'entries.*.location.required' => 'Location is required for each day.',
]);
```

#### 6. Testing Challenges
**Issue**: Tests initially failed with "no such table: users" errors.

**Solution**: Added `uses(RefreshDatabase::class);` at the top of the test file to ensure the database is migrated before each test.

**Issue**: Tests failed when we added the `id` field to the save logic because test data didn't include `id`.

**Solution**: Updated all test entry arrays to include `'id' => null` for new entries, and explicitly set the id for existing entries being updated.

#### 7. Wire:model.live vs Wire:model
**Decision**: Used `wire:model.live` for real-time updates throughout the form.

**Why**: Gives immediate feedback and allows copy buttons to work with the current state. The form isn't so large that the extra requests would cause performance issues. If performance becomes a problem later, could switch to `wire:model.blur` or plain `wire:model`.

#### 8. Laravel Boost MCP Server
**Key Tool**: Used Laravel Boost's `search-docs` tool to get version-specific documentation for Flux UI. This was crucial for understanding:
- How `Flux::toast()` works in Flux Pro
- That error handling is automatic in Flux components
- Proper syntax for Flux components

Much better than googling and potentially finding outdated documentation!

#### 9. Testing Strategy
**Coverage**: Wrote 8 feature tests covering:
- Rendering and data display
- Creating new entries
- Updating existing entries (the tricky one!)
- Copy functionality (both next and rest)
- Validation (both required and optional fields)
- Loading existing data on mount

**Result**: 48 assertions all passing, giving good confidence the feature works correctly.

---

## Technical Notes

### Data Structure for Entries
```php
public array $entries = [
    0 => ['note' => '', 'location' => '', 'entry_date' => '2025-11-04'],
    1 => ['note' => '', 'location' => '', 'entry_date' => '2025-11-05'],
    // ... 14 days total
];
```

### Database Considerations
- Need to handle create vs update logic (upsert by user_id + entry_date)
- Category field exists in DB but not used in v1 (free-form learning phase)

### UI/UX Notes
- Use Flux UI components throughout (flux:input, flux:select, flux:button, flux:toast)
- Leverage `wire:loading` for better feedback
- Consider using `wire:model.live` for real-time updates vs `wire:model.blur` for performance

---

## Phase 2 Implementation: Profile & Defaults Feature

### What We Built
- Profile page where users can set default location and work category
- HomePage now pre-fills new entries with user's defaults
- Navigation links added (sidebar + small button on HomePage)
- Full test coverage for both Profile and HomePage defaults functionality

### Critical Lessons Learned

#### 🚨 LESSON 1: NEVER RUN DESTRUCTIVE DATABASE COMMANDS (EVER)

**What almost happened**: When a test failed, there was an impulse to run `lando php artisan migrate:fresh --force` to "fix" the database.

**Why this would have been catastrophic**:
- Tests use `:memory:` SQLite database (see phpunit.xml)
- `RefreshDatabase` trait handles test migrations automatically
- Development database is completely separate from test database
- Running `migrate:fresh` would have **DELETED ALL DEVELOPMENT DATA**
- This would have wiped out hours of work by other developers

**The rule**:
```
NEVER EVER run these commands without explicit user approval:
- migrate:fresh
- migrate:reset
- db:wipe
- Any command that truncates/drops/resets data

If you think you need to run one of these, STOP and ask the user.
```

**The actual fix**: The test failed because UserFactory needed to include `default_location` and `default_category` fields. A one-line addition to the factory, not a database wipe.

#### 📖 LESSON 2: Code Must Be Readable Aloud

**The principle**: If you can't read the code aloud and have it make sense, it's too clever.

**Bad example** (triple null coalescing):
```php
'note' => $existing?->note ?? $user->default_category ?? '',
```
Try reading that aloud. It's confusing.

**Good example** (extract variables):
```php
$defaultNote = $user->default_category;
// ... later in loop ...
'note' => $existing?->note ?? $defaultNote,
```
This reads clearly: "If there's an existing note, use it. Otherwise, use the default note."

**Key insight**: Clever, shorthand, ultra-optimized code that we can't read is worthless. Code is read 10x more than it's written. Optimize for reading.

#### 🔗 LESSON 3: Use Relationships and Helper Methods

**The principle**: Relationships and helper methods make business logic self-documenting.

**Before** (raw query):
```php
PlanEntry::where('user_id', $user->id)->whereBetween('entry_date', [...])
```

**After** (relationship):
```php
$user->planEntries()->whereBetween('entry_date', [...])
```

**Why this matters**:
- More semantic - reads as "the user's plan entries"
- Can't accidentally forget the `user_id` constraint
- More secure - automatically scoped
- Reusable across the entire application (managers, admins, APIs, etc.)

**Added to User model**:
```php
public function planEntries(): HasMany
{
    return $this->hasMany(PlanEntry::class);
}
```

#### 🗄️ LESSON 4: Database Defaults > Code Null Coalescing

**The principle**: Push defaults to the database layer when possible.

**Before** (nulls in database, coalescing in code):
```php
// Migration
$table->string('default_location')->nullable();

// Code (everywhere)
$location = $user->default_location ?? '';
$this->default_location = auth()->user()->default_location ?? '';
```

**After** (defaults in database):
```php
// Migration
$table->string('default_location')->default('');

// Code (everywhere)
$location = $user->default_location;
$this->default_location = auth()->user()->default_location;
```

**Why this matters**:
- Simpler code throughout the application
- Consistent behavior - can't forget the `?? ''`
- Database enforces the default for ALL operations (factories, seeders, direct SQL, etc.)
- One source of truth

#### ✅ LESSON 5: Don't Write Tautology Tests

**The principle**: Tests must verify actual behavior, not mathematical truths.

**Bad test** (removed):
```php
test('empty defaults result in empty fields', function () {
    $user = User::factory()->create([
        'default_location' => '',
        'default_category' => '',
    ]);

    actingAs($user);

    Livewire::test(\App\Livewire\HomePage::class)
        ->assertSet('entries.0.note', '')
        ->assertSet('entries.0.location', '');
});
```

**Why this is bad**: This literally tests that `'' == ''`. It provides zero confidence that the code works correctly. It's a tautology.

**Good tests** (kept):
- "new entries use user defaults" - Tests that defaults ARE applied
- "existing entries override user defaults" - Tests precedence/priority

**The rule**: Every test should verify meaningful behavior. Ask yourself: "If this test passes, what does it tell me about my application?"

### Code Simplification Wins

During this implementation, we continuously simplified:

1. **Auth calls**: From 3 separate `auth()` calls to 1 `$user = auth()->user()` (more secure!)
2. **Null coalescing chains**: From `?? ?? ''` to simple variables
3. **Query security**: From `where('user_id', $user->id)` to `$user->planEntries()`
4. **Database defaults**: From code-level `?? ''` to migration `default('')`

All following the prime directive: **Simple until you know otherwise.**

### Test Results

**Profile Tests**: 4 tests, 12 assertions ✓
- Page rendering
- Loading existing defaults
- Saving updates
- Allowing empty values

**HomePage Tests**: 10 tests, 56 assertions ✓
- Original 8 tests (form rendering, saving, copying, validation, loading)
- 2 new tests (defaults applied, defaults overridden by existing entries)

**Total**: 14 tests, 68 assertions, all passing ✓

---

## What's Next? Post-v1 Roadmap

### Context & Decisions
- **Authentication**: Already handled via SSO using Laravel Sanctum ✓
- **Environment**: Higher education campus - whole campus shuts on weekends
- **Users**: IT team (tech-savvy, might want API access)
- **Management Structure**: Hierarchical - managers have managers

---

### Phase 2: Core Improvements (Immediate Priority)

(Small additional note: we should add a 'Profile' link in the sidebar (and as a small button at the top-left of the HomePage) to let users set defaults for their
location and what they work on.  If they spend 90% of their time doing, say, Active Directory work in the Such-and-such
building - we can pre-fill their form - I have added a default_location and default_category column to the user model in preperation).

#### 1. Hide Weekends in UI ✅
**Priority**: HIGH
**Rationale**: Campus closed weekends, no need to plan for Sat/Sun

**Implementation**:
- Keep backend logic simple - still work with 14 days
- Filter out Saturday/Sunday in the view (Blade template)
- Still save entries for Sat/Sun (they'll just be empty/hidden)
- Use hidden inputs if needed to maintain the array structure

**What We Actually Did**:
- Updated `home-page.blade.php` to check `$day->isWeekday()` (Carbon method)
- Wrapped the card display in `@if ($day->isWeekday())`
- Added hidden inputs for weekend entries to maintain array structure
- Zero backend changes needed - all existing logic (save, copyNext, copyRest) works unchanged
- Single template change - perfectly simple solution!

**Code Implementation**:
```blade
@foreach ($days as $index => $day)
    @if ($day->isWeekday())
        {{-- Show the day card --}}
    @else
        {{-- Hidden inputs to maintain array structure --}}
        <input type="hidden" wire:model="entries.{{ $index }}.id" />
        <input type="hidden" wire:model="entries.{{ $index }}.entry_date" />
        <input type="hidden" wire:model="entries.{{ $index }}.note" />
        <input type="hidden" wire:model="entries.{{ $index }}.location" />
    @endif
@endforeach
```

**Notes**:
- Part-time staff who work different days can deal with it for now
- Future enhancement: configurable working days per user

#### 2. Manager Report Page ✅
**Priority**: HIGH
**Rationale**: Managers need to see their team's plans

**What We Built**:
- Dual-tab layout: "My Team" (person-centric) and "By Location" (day-centric)
- Authorization: Only users who manage teams can access
- Shows 10 weekdays (current + next week, no weekends)
- Handles multiple teams and deduplicates members across teams
- Color-coded location badges with hover tooltips for notes

**Implementation Details**:

**Database & Models**:
- Added `managedTeams()` HasMany relationship to User model (app/Models/User.php:63-66)
- Uses existing Team/User many-to-many relationship via pivot table

**Route**:
- `/manager/report` → `ManagerReport::class` (routes/web.php:10)

**Livewire Component** (app/Livewire/ManagerReport.php):
- `mount()`: Authorization check - aborts with 403 if user has no managed teams
- `getDays()`: Returns 10 weekdays using `$day->isWeekday()` filter
- `getTeamMembers()`: Flattens all users from all managed teams, deduplicates, sorts by surname
- `render()`: Loads plan entries for team members, organizes data for both tab views

**Blade Template** (resources/views/livewire/manager-report.blade.php):
- **Tab 1 - "My Team"**:
  - `flux:table` with sticky first column (team member names)
  - 10 columns for weekdays (M, T, W, Th, F × 2)
  - Color-coded `flux:badge` for locations
  - `flux:tooltip` on hover to show work notes
  - Empty entries show "-" badge
- **Tab 2 - "By Location"**:
  - Daily `flux:card` for each weekday
  - Groups team members by location
  - Shows person count per location
  - Displays notes inline

**Navigation**:
- Added "Team Report" link to sidebar with user-group icon (resources/views/components/layouts/app.blade.php:30)

**Color Coding**:
- Home = zinc (gray)
- JWS = blue
- JWN = green
- Rankine = purple
- Boyd-Orr = orange

**Test Coverage** (tests/Feature/ManagerReportTest.php):
- 10 tests, 33 assertions ✓
- Authorization (manager vs non-manager)
- Team member display
- Weekdays only (10 days)
- Plan entry rendering
- Empty state handling
- Multiple teams
- Member deduplication
- Location grouping

**Test Data Seeder** (database/seeders/TestDataSeeder.php):
- admin2x user is manager of Infrastructure team
- 10 team members with realistic plan entries
- 10 weekdays of varied locations and tasks
- 80/20 split: primary location / secondary location for variety

**Key Lesson Learned**:
🔥 **Flux Tabs Component Structure** - Tabs do NOT use `wire:model`. The component manages state internally:
```blade
<flux:tab.group>
    <flux:tabs>
        <flux:tab name="team">My Team</flux:tab>
        <flux:tab name="location">By Location</flux:tab>
    </flux:tabs>

    <flux:tab.panel name="team">...</flux:tab.panel>
    <flux:tab.panel name="location">...</flux:tab.panel>
</flux:tab.group>
```
NOT: `<flux:tabs wire:model.live="activeTab">` with panels inside tabs - that doesn't work!

**Post-Implementation Enhancement #1: Toggle Switch** ✅
Added a `flux:switch` toggle to the "My Team" tab that lets managers flip between viewing locations and viewing notes.

**Implementation**:
- Added `public bool $showLocation = true` property to component
- `flux:switch` with `wire:model.live="showLocation"`
- Conditional rendering in table cells:
  - `$showLocation = true`: Color-coded location badges with tooltips (original behavior)
  - `$showLocation = false`: Work notes displayed as text
- Clean UI with "View: Locations/Work Notes" indicator

**Why this is useful**: Managers can focus on what's important to them at any moment - either "where is everyone?" or "what is everyone doing?" - without changing pages.

**Post-Implementation Enhancement #2: Coverage Tab** ✅
Added a third tab showing a visual grid of location coverage across all days.

**Implementation**:
- Coverage calculation in render(): `$coverage[location][date] = count`
- CSS Grid layout (11 columns: location names + 10 weekdays)
- Visual design:
  - Gray cells (`bg-zinc-300`) when count > 0 with number displayed
  - Transparent/white cells when count = 0 (visual gap)
  - Grid gaps for clarity
- Header row with day abbreviations
- One row per location

**Why this is useful**: Senior managers can instantly spot coverage gaps - like "no one at Boyd-Orr on Tuesday" - at a glance. The visual gap metaphor makes it immediately obvious where coverage is missing.

**Test Coverage**:
- 12 tests total, 46 assertions ✓
- Added 2 new tests for toggle and coverage features

**Post-Implementation Enhancement #3: Admin View All Users Toggle** ✅
Added an admin-only toggle that allows admins to view all users in the system, not just their direct reports.

**Implementation**:
- Added `is_admin` boolean field to User model with `isAdmin(): bool` helper method
- Created custom `@admin` Blade directive in AppServiceProvider for cleaner templates
  - Usage: `@admin ... @endadmin` (checks `auth()->check() && auth()->user()->isAdmin()`)
- Added `public bool $showAllUsers = false` property to ManagerReport component
- Updated `getTeamMembers()` method to return `User::orderBy('surname')->get()` when admin toggle is enabled
- Added toggle switch at top of page (only visible to admins)
- Non-admins never see the toggle and always see only their team members

**Why this is useful**: Senior admins (who may not be direct managers of everyone) can view organization-wide planning without needing to be assigned as a manager to every team. The default state (toggle off) still shows their direct reports, maintaining the normal manager workflow.

**Test Coverage**:
- 16 tests total, 60 assertions ✓
- Added 4 new tests for admin toggle functionality

**Post-Implementation Enhancement #4: Service Availability Tab** ✅
Added a fourth tab to the Manager Report showing service coverage across the two-week period.

**Implementation**:
- Added `buildServiceAvailabilityMatrix()` method to ManagerReport component (app/Livewire/ManagerReport.php:196-234)
  - Loads all services (ordered by name)
  - Counts only plan entries where `is_available === true`
  - Returns matrix structure matching Coverage tab pattern
- Updated `render()` method to pass `$serviceAvailabilityMatrix` to view
- Added "Service Availability" tab to Blade view (resources/views/livewire/manager-report.blade.php:38, 179-210)
  - Visual grid layout with 200px first column (wider for service names)
  - Gray cells when people available, blank when no coverage
  - Same design pattern as Coverage tab for consistency

**Key Design Decisions**:
- Shows **all services** in the system (not filtered by manager's teams)
- Admin "Show All Users" toggle does **not** affect service list
- Counts **all service members** (not just team members visible in report)
- Only counts entries where `is_available === true`

**Why this is useful**: Managers can quickly spot:
- Services with no coverage on specific days
- Consistently understaffed services
- Days where multiple services have reduced capacity
- Patterns in service availability over time
- Where cross-training or backup assignments are needed

**Test Coverage** (tests/Feature/ManagerReportServiceAvailabilityTest.php):
- 12 tests, 34 assertions ✓
- Tab visibility and service display
- Availability filtering (`is_available === true`)
- Zero/empty state handling
- Multiple available people counts
- Admin toggle independence
- Service with no members
- All 10 weekdays coverage
- Manager-only coverage scenarios (added later)

**Test Data Seeder Update**:
- Expanded from 6 to 20 realistic services (Active Directory, Email, DNS, VPN, Firewall, VLE, etc.)
- Changed service membership from 2-4 users to 1-3 users per service (realistic for small team)
- Added demo service "DEMO: Coverage Scenarios" showing all three coverage states

**Manager-Only Coverage Indicator** (Enhancement added 2025-11-06):
When a service has zero available members but the manager is available, a red "Manager" badge is displayed instead of a blank cell. This provides senior managers with a clear visual indicator of minimal coverage situations where only someone with high-level knowledge is available.

**Visual Hierarchy**:
- Gray cell with number = Normal coverage (team members available)
- Red "Manager" badge = Minimal coverage (only manager available, not ideal)
- Blank cell = No coverage (critical gap)

**Implementation Details**:
- Added `manager_only` boolean flag to each entry in the matrix
- Eager loads manager relationship to avoid N+1 queries
- Logic: If `count === 0` AND manager has entry for day AND `is_available === true`, show red badge
- 4 additional tests covering all manager-only scenarios

**Demo Service** (database/seeders/TestDataSeeder.php):
- Creates "DEMO: Coverage Scenarios" service with predictable pattern
- Days 1-4: Normal coverage (gray cells with "1")
- Days 5-7: Manager-only coverage (red "Manager" badges)
- Days 8-10: No coverage (blank cells)
- Always appears at top of list for easy discovery by new users

**Future Enhancements**:
- Hierarchical teams (managers of managers) - currently only direct reports
- Date range selector to view beyond current 2 weeks
- Export functionality (CSV/Excel)
- Filter/search team members

#### 3. Admin: User & Team Management
**Priority**: HIGH

**Features needed**:

**a) Make Users Admin**
- UI to promote users to admin role
- Only admins can do this
- Route: `/admin/users`
- Simple table with "Make Admin" / "Remove Admin" toggle

**b) Team Management UI** ✅
- Create teams
- Assign users to teams
- Set team manager
- Routes: `/admin/teams`, `/admin/teams/create`, `/admin/teams/{id}/edit`

**Data Model Notes**:
- One manager per team (existing model)
- Users can be on multiple teams
- Multiple managers can see the same user's entries
- Only admins can manage teams

**What We Built**:
- **Route**: `/admin/teams` → `AdminTeams::class` (routes/web.php:11)
- **Authorization**: Admin-only access via `@admin` directive
- **Navigation**: "Manage Teams" link in sidebar (resources/views/components/layouts/app.blade.php:32-34)

**Livewire Component** (app/Livewire/AdminTeams.php):
- **Team List View**: Table showing all teams with manager and member count
- **Create Team Modal**: Flyout modal for creating new teams
  - Team name (required, unique validation)
  - Manager selection via combobox (required - enforces "must always have a manager")
  - Multi-select team members via pillbox
- **Edit Team Modal**: Same flyout modal for editing existing teams
  - Pre-loads team data
  - Unique validation allows keeping same name but prevents duplicates
  - Update manager and members
- **Delete Team Modal**: Flyout modal with transfer option
  - Optional: Select another team to transfer all members to
  - Or: Delete team without transferring (just removes associations)
  - Uses `syncWithoutDetaching()` for safe member transfers

**Blade Template** (resources/views/livewire/admin-teams.blade.php):
- Consistent flyout modals for both create/edit and delete operations
- Uses Flux Pro components: `flux:table`, `flux:modal`, `flux:select`, `flux:pillbox`
- Table shows: Team name, manager full_name, member count badges, action buttons
- Proper use of `full_name` accessor (surname, forenames) throughout

**Key Implementation Details**:
- Team name uniqueness enforced: `unique:teams,name` on create, `unique:teams,name,{id}` on update
- Manager is required field - validation ensures "always have a manager" rule
- Users ordered by `surname` then `forenames` (not by non-existent `name` field)
- Flux combobox variant is inherently searchable (no `searchable` prop needed)
- Modal state managed via `wire:model="showEditModal"` and `wire:model="showDeleteModal"`

**Test Coverage** (tests/Feature/AdminTeamsTest.php):
- 15 tests, 55 assertions ✓
- Authorization (admin vs non-admin)
- Team CRUD operations
- Unique name validation (create and update)
- Team member management (add/remove/replace)
- Delete with and without member transfer
- Form validation (team name required, manager required)
- Cancel/close modal operations
- Member count display accuracy

**Lessons Learned**:
- 🔍 **User Model Structure**: Users have `surname` and `forenames` fields with a `full_name` accessor, not a `name` field
- 📋 **Flux Table Syntax**: Use `flux:table.columns`, `flux:table.column`, `flux:table.row`, `flux:table.cell` (not the shorter forms)
- 🔍 **Combobox Searchability**: The combobox variant is searchable by default - adding `searchable` prop throws an error
- 🎨 **Consistent UI Pattern**: Using flyout modals for both edit and delete operations creates a cohesive UX

**Next Steps**:
- Section 3.a still pending: Admin user management UI (promote/demote users to admin role)

#### 4. Excel/CSV Export
**Priority**: MEDIUM

**Requirements**:
- Simple CSV export (saved as .xlsx)
- Managers: export data they can see (their team hierarchy)
- Admins: export everything
- Filter by date range
- One row per plan entry

**Format**:
```
User, Date, Location, Note, Category, Team, Is Holiday
John Smith, 2025-11-04, Home, "Bug fixes", null, IT Support Team, false
```

**Implementation**:
- Use Laravel Excel package or simple CSV generation
- Routes:
  - `/manager/export` (filtered by their teams)
  - `/admin/export` (all data)

---

### Phase 3: API Development (Stretch Goals)

#### 5. Reporting API (PowerBI Integration)
**Priority**: MEDIUM
**Rationale**: Power users want to build reports and dashboards

**Endpoints** (under `/api/report/...`):
- `GET /api/report/location/{location}` - Who's in this location today
- `GET /api/report/team/{team-id}/schedule` - Team schedule
- `GET /api/report/user/{user-id}/schedule` - Individual schedule
- `GET /api/report/summary` - Overall statistics

**Authentication**: Sanctum tokens
**Format**: JSON
**Read-only**: Yes

**Example response for location endpoint**:
```json
{
  "location": "jws",
  "date": "2025-11-04",
  "people": [
    {
      "id": 1,
      "name": "John Smith",
      "note": "Support tickets",
      "team": "IT Support Team"
    }
  ]
}
```

#### 6. Full CRUD API
**Priority**: LOW (stretch goal)
**Rationale**: Tech-savvy team might want to build custom integrations

**Endpoints** (under `/api/v1/...`):
- `GET /api/v1/plan-entries` - List entries (filtered)
- `GET /api/v1/plan-entries/{id}` - Single entry
- `POST /api/v1/plan-entries` - Create entry
- `PUT /api/v1/plan-entries/{id}` - Update entry
- `DELETE /api/v1/plan-entries/{id}` - Delete entry

**Also consider**:
- `GET /api/v1/teams` - List teams
- `GET /api/v1/users` - List users (filtered by permissions)
- `GET /api/v1/locations` - Enum values
- `GET /api/v1/categories` - Enum values (future)

**Authentication**: Sanctum tokens
**Authorization**: User can only edit their own entries (unless admin)
**Rate Limiting**: Consider implementing
**Versioning**: `/api/v1/` for future compatibility

---

### Phase 4: Nice-to-Haves (Future)

#### 7. Category Analysis & Implementation
**Priority**: LOW (wait 2-4 weeks for data)
- Analyze free-form `note` field data
- Identify common patterns
- Update Category enum
- Add dropdown to UI
- Maybe AI-assisted categorization of existing entries?

#### 8. Reporting Dashboard
**Priority**: MEDIUM
- "Who's in the office today?" view
- Team capacity planning
- Location usage statistics
- Most common tasks/categories

#### 9. Absence/Leave Integration
**Priority**: LOW
- Auto-fill holiday days
- Mark as `is_holiday = true`
- Different styling for holidays
- Maybe integrate with HR system?

#### 10. Mobile Optimization
**Priority**: MEDIUM
- Test responsive design on phones/tablets
- Optimize for touch interactions
- Maybe PWA for offline access?

---

### Technical Considerations

**Manager Hierarchy Implementation**:
```php
// Recursive method to get all team members under a manager
public function getAllTeamMembers(User $manager): Collection
{
    $directTeams = $manager->managedTeams; // Teams where user is manager
    $members = collect();

    foreach ($directTeams as $team) {
        // Add all team members
        $members = $members->merge($team->users);

        // Find managers in this team and recurse
        foreach ($team->users as $user) {
            if ($user->managedTeams->isNotEmpty()) {
                $members = $members->merge($this->getAllTeamMembers($user));
            }
        }
    }

    return $members->unique('id');
}
```

**Weekend Filtering**:
```php
// In HomePage component or shared helper
private function getWorkingDaysOnly(array $allDays): array
{
    return collect($allDays)
        ->filter(fn($day) => !in_array($day->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]))
        ->values()
        ->toArray();
}
```

**API Endpoint Examples**:
```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    // Reporting API (read-only for PowerBI)
    Route::prefix('report')->group(function () {
        Route::get('location/{location}', [ReportController::class, 'byLocation']);
        Route::get('team/{team}/schedule', [ReportController::class, 'teamSchedule']);
        Route::get('user/{user}/schedule', [ReportController::class, 'userSchedule']);
    });

    // Full CRUD API (for tech-savvy users)
    Route::prefix('v1')->group(function () {
        Route::apiResource('plan-entries', PlanEntryController::class);
        Route::apiResource('teams', TeamController::class)->only(['index', 'show']);
    });
});
```

---

### Questions Still to Answer

1. **Manager UI Layout**: What's the best way to display hierarchical team data? Table? Tree? Cards?
2. **Permissions Model**: Do we need granular permissions, or is Admin/Manager/User sufficient?
3. **Data Retention**: How long to keep old plan entries?
4. **Audit Trail**: Do we need to track who changed what and when?
5. **Notifications**: Should managers get notified when team members update plans?

---

## Phase 3: Power BI API Integration ✅

**Priority**: HIGH (completed 2025-11-06)
**Rationale**: Senior management needs to pull planning data into Power BI dashboards

### What We Built

**1. API Endpoints** (RESTful JSON API with Sanctum authentication)

**Personal Data Endpoint** (all authenticated users):
- `GET /api/v1/plan` - Returns user's own plan entries for next 10 weekdays
- Returns: User info, date range, array of plan entries with locations, notes, availability status

**Organizational Reporting Endpoints** (managers/admins only):
- `GET /api/v1/reports/team` - Person × day grid (who's where and doing what)
- `GET /api/v1/reports/location` - Day × location grouping (who's at each location per day)
- `GET /api/v1/reports/coverage` - Location coverage matrix (counts per location per day)
- `GET /api/v1/reports/service-availability` - Service availability with manager-only indicators

**2. Token Management UI**
- Profile page section: "API Access"
- Generate tokens with auto-assigned abilities based on user role
- View existing tokens with abilities displayed as badges
- Revoke tokens with confirmation dialog
- Copy-to-clipboard functionality for newly generated tokens
- Flyout modal pattern (consistent with AdminTeams)

**3. Security Model**

**Token Abilities** (auto-assigned):
- `view:own-plan` - All users (access personal data)
- `view:team-plans` - Managers (access team data)
- `view:all-plans` - Admins (access all organizational data)

**Route Protection**:
- `auth:sanctum` middleware - Validates Bearer token
- `abilities` middleware - Checks for ANY required ability (OR logic)
- 401 Unauthorized - Invalid/missing/revoked token
- 403 Forbidden - Valid token but insufficient abilities

**Data Scoping**:
- Staff: See only their own entries
- Managers: See only their team members' entries
- Admins: See all users' entries
- Scoping handled in controller via `ManagerReportService->getScopedUserIds()`

### Implementation Details

**Files Created**:
- `app/Http/Controllers/Api/PlanController.php` - Personal data endpoint
- `app/Http/Controllers/Api/ReportController.php` - Organizational reports (4 methods)
- `tests/Feature/Api/ApiEndpointsTest.php` - API tests using Sanctum::actingAs()

**Files Modified**:
- `app/Models/User.php` - Added `HasApiTokens` trait
- `app/Services/ManagerReportService.php` - Added safe defaults + `configure()` method + `getScopedUserIds()`
- `app/Livewire/Profile.php` - Added token management methods
- `resources/views/livewire/profile.blade.php` - Added API Access section with token UI
- `routes/api.php` - Added v1 API routes with middleware
- `bootstrap/app.php` - Registered Sanctum ability middleware aliases
- `database/seeders/TestDataSeeder.php` - Auto-creates token for admin2x user

**Public Methods in ManagerReportService** (for API use):
- `buildDays()` - Returns 10 weekdays starting from current Monday
- `buildEntriesByUser()` - Loads plan entries indexed by user/date
- `buildTeamRows()` - Person-centric view with state/location/notes per day
- `buildLocationDays()` - Day-centric view grouped by location
- `buildCoverageMatrix()` - Location coverage counts
- `buildServiceAvailabilityMatrix()` - Service availability with manager-only flags
- `getScopedUserIds()` - NEW: Returns user IDs based on token ability

### Critical Lessons Learned

#### 🏗️ LESSON: Service Classes and the Laravel Container

**The Problem We Almost Had**:
Initially, `ManagerReportService` had required constructor parameters with no defaults:

```php
// ❌ BAD: Cannot be resolved from container
public function __construct(
    private bool $showLocation,
    private bool $showAllUsers,
    private array $selectedTeams,
) {}
```

This meant:
- ❌ Cannot do `app(ManagerReportService::class)` - Laravel can't resolve it
- ❌ Must use `new ManagerReportService($a, $b, $c)` everywhere - tight coupling
- ❌ Adding dependencies later means finding every instantiation
- ❌ Can't use dependency injection in controllers properly
- ❌ Harder to test (can't mock easily)

**The Solution**:
```php
// ✅ GOOD: Safe defaults + configuration method
public function __construct(
    private bool $showLocation = true,
    private bool $showAllUsers = false,
    private array $selectedTeams = [],
) {}

public function configure(
    bool $showLocation = true,
    bool $showAllUsers = false,
    array $selectedTeams = [],
): self {
    $this->showLocation = $showLocation;
    $this->showAllUsers = $showAllUsers;
    $this->selectedTeams = $selectedTeams;
    return $this;
}
```

**Why This Is Better**:
- ✅ Container-resolvable: `app(ManagerReportService::class)` works
- ✅ Fluent API: `$service->configure(showLocation: true)->buildReportPayload()`
- ✅ Named parameters: Clear, readable, self-documenting
- ✅ Easy to extend: Add new dependencies to constructor without breaking existing code
- ✅ Testable: Can inject mock dependencies
- ✅ Follows Laravel conventions

**Usage Examples**:

```php
// Web UI (Livewire component)
app(ManagerReportService::class)
    ->configure(
        showLocation: $this->showLocation,
        showAllUsers: $this->showAllUsers,
        selectedTeams: $this->selectedTeams,
    )
    ->buildReportPayload();

// API (controller with DI)
public function team(Request $request, ManagerReportService $service): JsonResponse
{
    $userIds = $service->getScopedUserIds($user, $ability);
    // Service has safe defaults, no configuration needed for API use
}
```

**The Rule**: A constructor should never fail. If a service needs configuration, provide safe defaults and a separate configuration method. This keeps dependency injection working and the container happy.

### Demo Usage

**Token Already Created**:
When you run `lando db:seed --class=TestDataSeeder`, a token is auto-created for admin2x and printed:

```
===========================================
API Token for admin2x:
1|AbC...xyz
===========================================
```

**Testing the API**:
```bash
# Store the token
TOKEN="1|AbC...xyz"

# Personal plan (any user)
curl -H "Authorization: Bearer $TOKEN" \
  https://wcap.youruniversity.ac.uk/api/v1/plan

# Team report (managers/admins)
curl -H "Authorization: Bearer $TOKEN" \
  https://wcap.youruniversity.ac.uk/api/v1/reports/team

# Location report
curl -H "Authorization: Bearer $TOKEN" \
  https://wcap.youruniversity.ac.uk/api/v1/reports/location

# Coverage matrix
curl -H "Authorization: Bearer $TOKEN" \
  https://wcap.youruniversity.ac.uk/api/v1/reports/coverage

# Service availability
curl -H "Authorization: Bearer $TOKEN" \
  https://wcap.youruniversity.ac.uk/api/v1/reports/service-availability
```

**In the UI**:
1. Log in as admin2x
2. Navigate to Profile page
3. Scroll to "API Access" section
4. See existing token or generate a new one
5. Copy token and use in Power BI/curl/Postman

### Power BI Integration

**Connection Steps** (from API.md):
1. Power BI Desktop → Get Data → Web (Advanced)
2. URL: `https://wcap.youruniversity.ac.uk/api/v1/reports/team`
3. Add HTTP header: `Authorization` = `Bearer YOUR_TOKEN_HERE`
4. Authentication: Anonymous (token is in header)
5. Transform data in Power Query Editor
6. Create visuals (matrix, charts, heat maps)

**Recommended Visuals**:
- **Team Report** → Matrix (Rows: Person, Columns: Date, Values: Location)
- **Location Report** → Stacked bar chart (Axis: Date, Legend: Location, Values: Count)
- **Coverage** → Heat map with conditional formatting (white = 0, blue = high)
- **Service Availability** → Cards with traffic light colors (green/yellow/red)

### Test Coverage

**New Tests** (`tests/Feature/Api/ApiEndpointsTest.php`):
- ✅ Unauthenticated request returns 401
- ✅ Staff user with token can access personal plan
- ✅ Manager with `view:team-plans` can access reports
- ✅ Admin with `view:all-plans` can access all 4 report endpoints

**Test Results**:
- **Before**: 110 tests, 435 assertions ✓
- **After**: 114 tests, 454 assertions ✓
- **New**: 4 API tests, 19 assertions ✓

**All existing tests still pass** - Zero impact on web UI.

### JSON Response Examples

See `API.md` for complete examples. Quick preview:

```json
// GET /api/v1/plan
{
  "user": { "id": 1, "name": "Smith, John" },
  "date_range": { "start": "2025-11-06", "end": "2025-11-20" },
  "entries": [
    {
      "id": 123,
      "entry_date": "2025-11-06",
      "location": "jws",
      "location_label": "James Watt South",
      "note": "Server maintenance",
      "is_available": true,
      "is_holiday": false
    }
    // ... 9 more entries
  ]
}

// GET /api/v1/reports/team
{
  "scope": "view:all-plans",
  "days": [
    { "date": "2025-11-06", "day_name": "Wednesday" }
    // ... 9 more days
  ],
  "team_rows": [
    {
      "member_id": 5,
      "name": "Adams, Sarah",
      "days": [
        {
          "date": "2025-11-06",
          "state": "planned",
          "location": "jws",
          "location_short": "JWS",
          "note": "Support desk coverage"
        }
        // ... 9 more days
      ]
    }
    // ... more team members
  ]
}
```

### What's Next

**Completed** (Phase 3):
- ✅ Power BI API with 5 endpoints
- ✅ Token management UI
- ✅ Sanctum authentication and authorization
- ✅ Comprehensive documentation in API.md
- ✅ Admin API key management toggle (admins can view/revoke all tokens)

**Test Results**:
- **Total**: 122 tests, 483 assertions ✓
- **Profile Tests**: 11 tests, 29 assertions ✓

**Quality of Life Enhancements**:
- **Dynamic API Documentation on Profile Page** - When a user clicks an API token name, show contextual documentation below the token table:
  - Display `curl` examples that work with their specific token abilities
  - Show PowerBI connection setup with their token
  - Examples should be scope-aware (only show endpoints they have access to)
  - Should appear under the current grid layout
  - Make it interactive/copyable for easy use

**Still To Do** (from original roadmap):
- Excel/CSV export functionality
- Admin: Make users admin UI
- Date range filters for API (currently fixed to 10 weekdays)
- Rate limiting on API endpoints
- Token expiration policy

---

## Future Enhancements (Parked Ideas)
- MS Teams bot (parked - "dumpster fire" API 😄)
- iCal feed for calendar subscriptions
- Slack integration (if team ever switches)
- "Where's X this week?" quick search
- Bulk operations for managers
- Holiday auto-detection (UK bank holidays + university closures)
