# Time Tracker Implementation Plan

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

## What's Next? Post-v1 Roadmap

### Context & Decisions
- **Authentication**: Already handled via SSO using Laravel Sanctum ✓
- **Environment**: Higher education campus - whole campus shuts on weekends
- **Users**: IT team (tech-savvy, might want API access)
- **Management Structure**: Hierarchical - managers have managers

---

### Phase 2: Core Improvements (Immediate Priority)

#### 1. Hide Weekends in UI
**Priority**: HIGH
**Rationale**: Campus closed weekends, no need to plan for Sat/Sun

**Implementation**:
- Keep backend logic simple - still work with 14 days
- Filter out Saturday/Sunday in the view (Blade template)
- Still save entries for Sat/Sun (they'll just be empty/hidden)
- Use hidden inputs if needed to maintain the array structure

**Notes**:
- Part-time staff who work different days can deal with it for now
- Future enhancement: configurable working days per user

**Code approach**:
```blade
@foreach ($days as $index => $day)
    @if (!in_array($day->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]))
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

#### 2. Manager Report Page
**Priority**: HIGH
**Rationale**: Managers need to see their team's plans

**Requirements**:
- Show all team members' plans
- Hierarchical: Managers see everyone below them (recursive down the tree)
- Filter by date range
- Export to Excel/CSV

**UI Considerations**:
- Table view? (rows = team members, columns = days)
- Need to experiment with the best layout
- How to navigate deep hierarchies clearly?
- Maybe collapsible team sections?

**Questions to explore**:
- Show just direct reports with "View Team" button to drill down?
- Or flat list of everyone with indentation/tree view?
- Calendar/grid view vs list view?

**Routes**:
- `/manager/reports` - Main manager report page
- `/manager/team/{team-id}` - Specific team view

#### 3. Admin: User & Team Management
**Priority**: HIGH

**Features needed**:

**a) Make Users Admin**
- UI to promote users to admin role
- Only admins can do this
- Route: `/admin/users`
- Simple table with "Make Admin" / "Remove Admin" toggle

**b) Team Management UI**
- Create teams
- Assign users to teams
- Set team manager
- Routes: `/admin/teams`, `/admin/teams/create`, `/admin/teams/{id}/edit`

**Data Model Notes**:
- One manager per team (existing model)
- Users can be on multiple teams
- Multiple managers can see the same user's entries
- Only admins can manage teams

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

## Future Enhancements (Parked Ideas)
- MS Teams bot (parked - "dumpster fire" API 😄)
- iCal feed for calendar subscriptions
- Slack integration (if team ever switches)
- "Where's X this week?" quick search
- Bulk operations for managers
- Holiday auto-detection (UK bank holidays + university closures)
