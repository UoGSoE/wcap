# Manager API for Power BI Integration

## Overview
Create a REST API that allows users to pull planning data into Power BI. Permissions are automatically assigned based on user role, and route-level middleware enforces access control.

### User Roles & Automatic Token Abilities
- **Staff**: Can export their own planning data
  - Token abilities: `['view:own-plan']`
- **Managers**: Can export their team's planning data + their own
  - Token abilities: `['view:own-plan', 'view:team-plans']`
- **Admins**: Can export all planning data
  - Token abilities: `['view:own-plan', 'view:team-plans', 'view:all-plans']`

### Controller Architecture
Two separate controllers for semantic clarity:

1. **PlanController** - Personal data (all users)
   - Routes: `/api/v1/plan`
   - Use case: Individual users accessing their own records

2. **ReportController** - Organizational reporting (managers/admins only)
   - Routes: `/api/v1/reports/*`
   - Use case: Management pulling team/org data into Power BI

---

## Table of Contents
1. [Implementation Checklist](#implementation-checklist)
2. [Route Structure with Middleware](#route-structure-with-middleware)
3. [Data Scoping Strategy](#data-scoping-strategy)
4. [Power BI Integration Guide](#power-bi-integration-guide)
5. [JSON Response Examples](#json-response-examples)
6. [Testing Strategy](#testing-strategy)

---

## Implementation Checklist

### 1. Create ManagerReportService
**File**: `app/Services/ManagerReportService.php`

- [ ] Create `app/Services/` directory
- [ ] Generate service class: `lando artisan make:class Services/ManagerReportService`
- [ ] Extract all report-building logic from `ManagerReport.php`

#### Methods to Extract:

##### Core Orchestrator
- [ ] `buildReportPayload($user, $showAllUsers, $selectedTeams): array`
  - Main method that calls all other build methods
  - Returns complete payload for web UI and API
  - Parameters:
    - `$user` - User model instance
    - `$showAllUsers` - bool (admin toggle)
    - `$selectedTeams` - array of team IDs to filter by

##### Day/Date Methods
- [ ] `buildDays(): array`
  - Generates array of next 10 weekdays
  - Returns: `[['date' => Carbon, 'key' => '2025-11-06'], ...]`
  - Starts from current week's Monday
  - Filters out Saturday/Sunday

##### Data Loading Methods
- [ ] `buildEntriesByUser($teamMembers, $days): array`
  - Loads PlanEntry records for given users and date range
  - Returns: `[$userId => [$dateKey => PlanEntry]]` (nested associative array)
  - Uses `whereBetween('entry_date', [$start, $end])`

##### Report Building Methods
- [ ] `buildTeamRows($teamMembers, $days, $entriesByUser): array`
  - Person-centric view (person × day grid)
  - Returns: `[['member_id' => int, 'name' => string, 'days' => [...]], ...]`
  - Each day includes: state, location, location_short, note
  - States: 'planned', 'away', 'missing'

- [ ] `buildLocationDays($days, $teamMembers, $entriesByUser): array`
  - Day-centric view (day × location grouping)
  - Returns: `[['date' => Carbon, 'locations' => ['jws' => ['label' => string, 'members' => [...]]]], ...]`
  - Groups members by location for each day

- [ ] `buildCoverageMatrix($days, $locationDays): array`
  - Location coverage counts per day
  - Returns: `[['label' => 'JWS', 'entries' => [['date' => Carbon, 'count' => 3], ...]], ...]`
  - One row per Location enum case

- [ ] `buildServiceAvailabilityMatrix($days): array`
  - Service availability with manager-only indicators
  - Returns: `[['label' => 'Service Name', 'entries' => [['date' => Carbon, 'count' => 2, 'manager_only' => false], ...]], ...]`
  - Counts only entries where `is_available === true`
  - Eager loads `services.users` and `services.manager` relationships

##### Team Member Methods
- [ ] `getTeamMembers($user, $showAllUsers, $selectedTeams): Collection`
  - Returns User collection based on permissions and filters
  - Logic:
    - If `$selectedTeams` not empty → users from those teams
    - Else if `$user->isAdmin() && $showAllUsers` → all users
    - Else → users from `$user->managedTeams()`
  - Orders by `surname`
  - Deduplicates via `unique('id')`

##### NEW: API Scoping Method
- [ ] `getScopedUserIds($user, $tokenAbility): array`
  - Returns array of user IDs based on token ability
  - Logic:
    ```php
    return match($tokenAbility) {
        'view:own-plan' => [$user->id],
        'view:team-plans' => $user->managedTeams()
            ->with('users')
            ->get()
            ->flatMap(fn($team) => $team->users)
            ->unique('id')
            ->pluck('id')
            ->toArray(),
        'view:all-plans' => User::pluck('id')->toArray(),
        default => [],
    };
    ```

#### Implementation Notes:
- [ ] All methods should be `public` (service class pattern)
- [ ] Add comprehensive PHPDoc blocks to all methods
- [ ] Type-hint all parameters and return types
- [ ] Use dependency injection for any Laravel services needed
- [ ] Keep methods focused and single-purpose

---

### 2. Refactor ManagerReport Livewire Component
**File**: `app/Livewire/ManagerReport.php`

- [ ] Type-hint `ManagerReportService` in `render()` method
  ```php
  public function render(ManagerReportService $service)
  ```
- [ ] Remove all private `build*()` methods (lines 78-321)
- [ ] Update `render()` to use service:
  ```php
  $payload = $service->buildReportPayload(
      auth()->user(),
      $this->showAllUsers,
      $this->selectedTeams
  );
  ```
- [ ] Update `exportAll()` method (lines 42-53) to use service:
  ```php
  public function exportAll(ManagerReportService $service)
  {
      $payload = $service->buildReportPayload(
          auth()->user(),
          $this->showAllUsers,
          $this->selectedTeams
      );

      // ... rest of export logic
  }
  ```
- [ ] Keep existing `mount()` authorization unchanged (lines 21-33)
- [ ] Keep all public properties unchanged (`$showLocation`, `$showAllUsers`, `$selectedTeams`)

#### Testing After Refactor:
- [ ] Run: `lando artisan test --filter=ManagerReport`
- [ ] Verify all existing tests still pass
- [ ] Check web UI manually - all tabs should work identically

---

### 3. Create PlanController (Personal Data)
**File**: `app/Http/Controllers/Api/PlanController.php`

- [ ] Create directory: `app/Http/Controllers/Api/`
- [ ] Generate controller: `lando artisan make:controller Api/PlanController`
- [ ] Import required classes:
  ```php
  use Illuminate\Http\Request;
  use Illuminate\Http\JsonResponse;
  use App\Models\PlanEntry;
  use Carbon\Carbon;
  ```

#### Endpoint: myPlan()
```php
/**
 * Get the authenticated user's plan entries for the next 10 weekdays.
 *
 * @param Request $request
 * @return JsonResponse
 */
public function myPlan(Request $request): JsonResponse
{
    $user = $request->user();

    // Calculate date range (next 10 weekdays)
    $start = now()->startOfWeek();
    $weekdayDates = [];

    for ($offset = 0; $offset < 14; $offset++) {
        $day = $start->copy()->addDays($offset);
        if ($day->isWeekday()) {
            $weekdayDates[] = $day->toDateString();
        }
        if (count($weekdayDates) === 10) {
            break;
        }
    }

    // Load user's plan entries
    $entries = PlanEntry::where('user_id', $user->id)
        ->whereIn('entry_date', $weekdayDates)
        ->orderBy('entry_date')
        ->get()
        ->map(function ($entry) {
            return [
                'id' => $entry->id,
                'entry_date' => $entry->entry_date->toDateString(),
                'location' => $entry->location?->value,
                'location_label' => $entry->location?->label(),
                'note' => $entry->note,
                'is_available' => $entry->is_available,
                'is_holiday' => $entry->is_holiday,
                'category' => $entry->category?->value,
                'category_label' => $entry->category?->label(),
            ];
        });

    return response()->json([
        'user' => [
            'id' => $user->id,
            'name' => $user->full_name,
        ],
        'date_range' => [
            'start' => $weekdayDates[0],
            'end' => end($weekdayDates),
        ],
        'entries' => $entries,
    ]);
}
```

**Implementation Checklist**:
- [ ] Add method to controller
- [ ] Add PHPDoc block with description
- [ ] Type-hint Request parameter and JsonResponse return
- [ ] Calculate 10 weekdays (reuse buildDays logic or inline)
- [ ] Query only authenticated user's entries
- [ ] Map entries to simple array structure
- [ ] Include enum labels for Power BI readability
- [ ] Return JSON with metadata (user, date_range) and entries

---

### 4. Create ReportController (Organizational Reporting)
**File**: `app/Http/Controllers/Api/ReportController.php`

- [ ] Generate controller: `lando artisan make:controller Api/ReportController`
- [ ] Import required classes:
  ```php
  use Illuminate\Http\Request;
  use Illuminate\Http\JsonResponse;
  use App\Services\ManagerReportService;
  use App\Enums\Location;
  ```

#### Shared Helper Method
```php
/**
 * Determine which token ability the user has for scoping.
 *
 * @param Request $request
 * @return string
 */
private function getTokenAbility(Request $request): string
{
    $user = $request->user();

    if ($user->tokenCan('view:all-plans')) {
        return 'view:all-plans';
    }

    if ($user->tokenCan('view:team-plans')) {
        return 'view:team-plans';
    }

    // Shouldn't reach here due to middleware, but safe default
    return 'view:own-plan';
}
```

- [ ] Add helper method to determine scoping ability
- [ ] Use `tokenCan()` to check abilities in order (most permissive first)
- [ ] Return ability string for service scoping

#### Endpoint 1: team()
```php
/**
 * Get team report (person × day grid).
 *
 * Requires: view:team-plans or view:all-plans token ability
 *
 * @param Request $request
 * @param ManagerReportService $service
 * @return JsonResponse
 */
public function team(Request $request, ManagerReportService $service): JsonResponse
{
    $user = $request->user();
    $ability = $this->getTokenAbility($request);

    $userIds = $service->getScopedUserIds($user, $ability);
    $days = $service->buildDays();

    // Get team members (filtered by scoped user IDs)
    $teamMembers = \App\Models\User::whereIn('id', $userIds)
        ->orderBy('surname')
        ->get()
        ->all();

    $entriesByUser = $service->buildEntriesByUser($teamMembers, $days);
    $teamRows = $service->buildTeamRows($teamMembers, $days, $entriesByUser);

    return response()->json([
        'scope' => $ability,
        'days' => array_map(fn($d) => [
            'date' => $d['date']->toDateString(),
            'day_name' => $d['date']->format('l'),
        ], $days),
        'team_rows' => $teamRows,
    ]);
}
```

**Implementation Checklist**:
- [ ] Add method with PHPDoc
- [ ] Type-hint ManagerReportService in method signature
- [ ] Get token ability using helper
- [ ] Get scoped user IDs from service
- [ ] Load User models matching scoped IDs
- [ ] Build entries and team rows using service
- [ ] Return JSON with scope indicator and data
- [ ] Format dates as strings for JSON serialization

#### Endpoint 2: location()
```php
/**
 * Get location report (day × location grouping).
 *
 * Requires: view:team-plans or view:all-plans token ability
 *
 * @param Request $request
 * @param ManagerReportService $service
 * @return JsonResponse
 */
public function location(Request $request, ManagerReportService $service): JsonResponse
{
    $user = $request->user();
    $ability = $this->getTokenAbility($request);

    $userIds = $service->getScopedUserIds($user, $ability);
    $days = $service->buildDays();

    $teamMembers = \App\Models\User::whereIn('id', $userIds)
        ->orderBy('surname')
        ->get()
        ->all();

    $entriesByUser = $service->buildEntriesByUser($teamMembers, $days);
    $locationDays = $service->buildLocationDays($days, $teamMembers, $entriesByUser);

    return response()->json([
        'scope' => $ability,
        'location_days' => array_map(function($day) {
            return [
                'date' => $day['date']->toDateString(),
                'day_name' => $day['date']->format('l'),
                'locations' => $day['locations'],
            ];
        }, $locationDays),
    ]);
}
```

**Implementation Checklist**:
- [ ] Add method with PHPDoc
- [ ] Same scoping pattern as team()
- [ ] Build location days using service
- [ ] Format dates in response
- [ ] Include day_name for readability

#### Endpoint 3: coverage()
```php
/**
 * Get coverage matrix (location × day with counts).
 *
 * Requires: view:team-plans or view:all-plans token ability
 *
 * @param Request $request
 * @param ManagerReportService $service
 * @return JsonResponse
 */
public function coverage(Request $request, ManagerReportService $service): JsonResponse
{
    $user = $request->user();
    $ability = $this->getTokenAbility($request);

    $userIds = $service->getScopedUserIds($user, $ability);
    $days = $service->buildDays();

    $teamMembers = \App\Models\User::whereIn('id', $userIds)
        ->orderBy('surname')
        ->get()
        ->all();

    $entriesByUser = $service->buildEntriesByUser($teamMembers, $days);
    $locationDays = $service->buildLocationDays($days, $teamMembers, $entriesByUser);
    $coverageMatrix = $service->buildCoverageMatrix($days, $locationDays);

    return response()->json([
        'scope' => $ability,
        'days' => array_map(fn($d) => [
            'date' => $d['date']->toDateString(),
            'day_name' => $d['date']->format('l'),
        ], $days),
        'coverage_matrix' => array_map(function($row) {
            return [
                'location' => $row['label'],
                'entries' => array_map(fn($e) => [
                    'date' => $e['date']->toDateString(),
                    'count' => $e['count'],
                ], $row['entries']),
            ];
        }, $coverageMatrix),
    ]);
}
```

**Implementation Checklist**:
- [ ] Add method with PHPDoc
- [ ] Same scoping pattern
- [ ] Build coverage matrix using service
- [ ] Format all Carbon dates as strings
- [ ] Include both days array and matrix in response

#### Endpoint 4: serviceAvailability()
```php
/**
 * Get service availability matrix (service × day with availability counts).
 *
 * Requires: view:team-plans or view:all-plans token ability
 *
 * Note: Shows ALL services (not scoped by user's teams), but counts
 * may differ based on token ability (team vs all users).
 *
 * @param Request $request
 * @param ManagerReportService $service
 * @return JsonResponse
 */
public function serviceAvailability(Request $request, ManagerReportService $service): JsonResponse
{
    $days = $service->buildDays();
    $serviceAvailabilityMatrix = $service->buildServiceAvailabilityMatrix($days);

    return response()->json([
        'scope' => $this->getTokenAbility($request),
        'days' => array_map(fn($d) => [
            'date' => $d['date']->toDateString(),
            'day_name' => $d['date']->format('l'),
        ], $days),
        'service_availability_matrix' => array_map(function($row) {
            return [
                'service' => $row['label'],
                'entries' => array_map(fn($e) => [
                    'date' => $e['date']->toDateString(),
                    'count' => $e['count'],
                    'manager_only' => $e['manager_only'],
                ], $row['entries']),
            ];
        }, $serviceAvailabilityMatrix),
    ]);
}
```

**Implementation Checklist**:
- [ ] Add method with PHPDoc
- [ ] Note in docblock that services are NOT scoped
- [ ] Build service matrix using service
- [ ] Format dates and structure for JSON
- [ ] Include manager_only flag in response

---

### 5. Update Profile Livewire Component
**File**: `app/Livewire/Profile.php`

#### Add Properties
```php
// Token management
public bool $showTokenModal = false;
public string $newTokenName = '';
public ?string $generatedToken = null;
```

- [ ] Add three new public properties for token modal state

#### Add Computed Property
```php
use Livewire\Attributes\Computed;

#[Computed]
public function tokens()
{
    return auth()->user()->tokens()
        ->orderBy('created_at', 'desc')
        ->get();
}
```

- [ ] Add computed property to load user's tokens
- [ ] Order by created_at descending (newest first)
- [ ] Use `#[Computed]` for automatic caching

#### Add Helper Method
```php
/**
 * Determine which token abilities to assign based on user role.
 *
 * @return array
 */
private function determineTokenAbilities(): array
{
    $user = auth()->user();

    // Start with base ability (everyone gets this)
    $abilities = ['view:own-plan'];

    // Managers get team viewing ability
    if ($user->isManager()) {
        $abilities[] = 'view:team-plans';
    }

    // Admins get all abilities
    if ($user->isAdmin()) {
        $abilities[] = 'view:all-plans';
    }

    return $abilities;
}
```

- [ ] Add private helper to calculate abilities
- [ ] Check `isManager()` and `isAdmin()` helper methods
- [ ] Return array of ability strings

#### Add createToken() Method
```php
use Flux\Flux;

public function createToken(): void
{
    $this->validate([
        'newTokenName' => 'required|string|max:255',
    ]);

    $abilities = $this->determineTokenAbilities();

    $token = auth()->user()->createToken($this->newTokenName, $abilities);

    $this->generatedToken = $token->plainTextToken;
    $this->newTokenName = '';

    Flux::toast(
        heading: 'Token Created',
        text: 'Your API token has been generated. Copy it now - you won\'t see it again!',
        variant: 'success'
    );
}
```

- [ ] Validate token name (required, max 255 chars)
- [ ] Get abilities using helper method
- [ ] Create token with Sanctum: `createToken($name, $abilities)`
- [ ] Store plaintext token in `$generatedToken` property
- [ ] Clear form: `$newTokenName = ''`
- [ ] Show success toast with warning message
- [ ] Modal will reactively show token display section

#### Add revokeToken() Method
```php
public function revokeToken(int $tokenId): void
{
    $token = auth()->user()->tokens()->find($tokenId);

    if ($token) {
        $token->delete();

        Flux::toast(
            heading: 'Token Revoked',
            text: 'The API token has been deleted.',
            variant: 'success'
        );
    }
}
```

- [ ] Find token by ID (scoped to authenticated user)
- [ ] Delete token if found
- [ ] Show success toast
- [ ] Computed property will auto-refresh token list

#### Add closeTokenModal() Method
```php
public function closeTokenModal(): void
{
    $this->showTokenModal = false;
    $this->generatedToken = null;
    $this->newTokenName = '';
}
```

- [ ] Reset all modal state
- [ ] Clear generated token (security - don't leave in memory)
- [ ] Clear form input

---

**File**: `resources/views/livewire/profile.blade.php`

- [ ] Add new section after existing profile fields: "API Access"
- [ ] Add Flux card with heading "API Tokens"
- [ ] Add description text about API usage
- [ ] Add "Generate New Token" button

#### Token Table (Existing Tokens)
```blade
@if($this->tokens->isNotEmpty())
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Abilities</flux:table.column>
            <flux:table.column>Created</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach($this->tokens as $token)
                <flux:table.row :key="$token->id">
                    <flux:table.cell>{{ $token->name }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1 flex-wrap">
                            @foreach($token->abilities as $ability)
                                <flux:badge size="sm" variant="subtle">{{ $ability }}</flux:badge>
                            @endforeach
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $token->created_at->format('M j, Y') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:button
                            size="sm"
                            variant="danger"
                            wire:click="revokeToken({{ $token->id }})"
                            wire:confirm="Are you sure you want to revoke this token? Any applications using it will lose access."
                        >
                            Revoke
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
@else
    <flux:text>No API tokens yet. Generate one to get started.</flux:text>
@endif
```

- [ ] Show table if tokens exist, empty state otherwise
- [ ] Display token name, abilities as badges, created date
- [ ] Add "Revoke" button with confirmation dialog
- [ ] Use `wire:confirm` for delete confirmation

#### Modal Trigger
```blade
<flux:modal.trigger name="create-token">
    <flux:button>Generate New Token</flux:button>
</flux:modal.trigger>
```

- [ ] Add trigger button
- [ ] Use Flux modal trigger component

#### Flyout Modal
```blade
<flux:modal name="create-token" variant="flyout" wire:model="showTokenModal">
    @if($generatedToken)
        {{-- Token Display Section --}}
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Token Generated!</flux:heading>
                <flux:text class="mt-2">
                    Copy your token now. For security reasons, you won't be able to see it again.
                </flux:text>
            </div>

            <flux:field>
                <flux:label>Your API Token</flux:label>
                <div class="flex gap-2">
                    <flux:input
                        value="{{ $generatedToken }}"
                        readonly
                        class="font-mono text-sm"
                    />
                    <flux:button
                        x-data
                        @click="navigator.clipboard.writeText('{{ $generatedToken }}'); $flux.toast({ heading: 'Copied!', text: 'Token copied to clipboard' })"
                    >
                        Copy
                    </flux:button>
                </div>
            </flux:field>

            <div class="flex justify-end">
                <flux:button wire:click="closeTokenModal">Done</flux:button>
            </div>
        </div>
    @else
        {{-- Token Creation Form --}}
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Generate API Token</flux:heading>
                <flux:text class="mt-2">
                    Create a new API token for accessing your planning data via Power BI or other tools.
                </flux:text>
            </div>

            {{-- Show which abilities will be assigned --}}
            <flux:callout variant="info">
                <div class="space-y-2">
                    <flux:text><strong>Automatic Permissions:</strong></flux:text>
                    <div class="flex gap-1 flex-wrap">
                        @foreach($this->determineTokenAbilities() as $ability)
                            <flux:badge size="sm">{{ $ability }}</flux:badge>
                        @endforeach
                    </div>
                    @if(auth()->user()->isAdmin())
                        <flux:text size="sm">As an admin, you can access all organizational data.</flux:text>
                    @elseif(auth()->user()->isManager())
                        <flux:text size="sm">As a manager, you can access your team's data.</flux:text>
                    @else
                        <flux:text size="sm">You can access your own planning data.</flux:text>
                    @endif
                </div>
            </flux:callout>

            <flux:field>
                <flux:label>Token Name</flux:label>
                <flux:input
                    wire:model="newTokenName"
                    placeholder="e.g., Power BI - Team Dashboard"
                />
                <flux:error name="newTokenName" />
            </flux:field>

            <div class="flex gap-2 justify-end">
                <flux:button wire:click="closeTokenModal" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="createToken" variant="primary">Generate Token</flux:button>
            </div>
        </div>
    @endif
</flux:modal>
```

**Implementation Checklist**:
- [ ] Add flyout modal with two states
- [ ] **State 1** (before creation): Show form with token name input
- [ ] Show callout explaining auto-assigned abilities
- [ ] Display which abilities user will get (call helper method)
- [ ] Show role-specific message (admin/manager/staff)
- [ ] Cancel and Generate buttons
- [ ] **State 2** (after creation): Show generated token
- [ ] Display token in readonly input with copy button
- [ ] Use Alpine.js clipboard API for copy functionality
- [ ] Show warning about token visibility
- [ ] "Done" button to close modal
- [ ] Use `wire:model="showTokenModal"` for reactive state

---

### 6. Add API Routes with Sanctum Abilities Middleware
**File**: `routes/api.php`

```php
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ReportController;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // Personal endpoint (all authenticated users with valid token)
    Route::get('/plan', [PlanController::class, 'myPlan']);

    // Reporting endpoints (requires view:team-plans OR view:all-plans)
    Route::prefix('reports')
        ->middleware('ability:view:team-plans,view:all-plans')
        ->group(function () {
            Route::get('/team', [ReportController::class, 'team']);
            Route::get('/location', [ReportController::class, 'location']);
            Route::get('/coverage', [ReportController::class, 'coverage']);
            Route::get('/service-availability', [ReportController::class, 'serviceAvailability']);
        });
});
```

**Implementation Checklist**:
- [ ] Import both controller classes at top of file
- [ ] Add route group with `auth:sanctum` middleware
- [ ] Add `/v1` prefix for API versioning
- [ ] Add `/plan` route (no additional middleware - all authenticated users)
- [ ] Add `/reports` prefix group
- [ ] Add `ability` middleware to reports group
- [ ] Use singular `ability` (not `abilities`) for OR logic
- [ ] List both abilities: `view:team-plans,view:all-plans`
- [ ] Register all four report endpoints

**How Sanctum Abilities Middleware Works**:
- `ability:view:team-plans,view:all-plans` → Token must have **ANY ONE** of these abilities
- `abilities:view:team-plans,view:all-plans` → Token must have **BOTH** abilities
- Middleware returns **403 Forbidden** if token lacks required abilities
- Controllers don't need authorization checks - handled at route level
- Controllers only need to handle **data scoping** based on which ability they have

---

### 7. Write Comprehensive Tests

#### Test File 1: API Endpoints - PlanController
**File**: `tests/Feature/Api/PlanControllerTest.php`

- [ ] Create test: `lando artisan make:test Api/PlanControllerTest --pest`
- [ ] Add `use RefreshDatabase;` at top

**Test Cases**:
```php
test('unauthenticated request returns 401', function () {
    $response = $this->getJson('/api/v1/plan');

    $response->assertUnauthorized();
});

test('authenticated user with token can access their own plan', function () {
    $user = User::factory()->create();

    // Create plan entries for user (next 10 weekdays)
    $start = now()->startOfWeek();
    $weekdays = [];
    for ($i = 0; $i < 14; $i++) {
        $day = $start->copy()->addDays($i);
        if ($day->isWeekday()) {
            $weekdays[] = $day;
            PlanEntry::factory()->create([
                'user_id' => $user->id,
                'entry_date' => $day,
                'location' => Location::JWS,
                'note' => 'Working on project',
            ]);
        }
        if (count($weekdays) === 10) break;
    }

    $token = $user->createToken('test', ['view:own-plan'])->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/plan');

    $response->assertOk();
    $response->assertJsonStructure([
        'user' => ['id', 'name'],
        'date_range' => ['start', 'end'],
        'entries' => [
            '*' => [
                'id', 'entry_date', 'location', 'location_label',
                'note', 'is_available', 'is_holiday', 'category', 'category_label'
            ]
        ]
    ]);
    $response->assertJsonCount(10, 'entries');
    $response->assertJsonPath('user.id', $user->id);
});

test('user only sees their own entries, not other users', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $date = now()->startOfWeek();

    // Create entry for authenticated user
    PlanEntry::factory()->create([
        'user_id' => $user->id,
        'entry_date' => $date,
    ]);

    // Create entry for other user (should not appear)
    PlanEntry::factory()->create([
        'user_id' => $otherUser->id,
        'entry_date' => $date,
    ]);

    $token = $user->createToken('test', ['view:own-plan'])->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/plan');

    $response->assertOk();
    expect($response->json('entries'))->toHaveCount(1);
    expect($response->json('entries.0'))->toHaveKey('id');
});

test('plan endpoint only returns next 10 weekdays', function () {
    $user = User::factory()->create();

    // Create entries for 20 days (should only get 10 weekdays)
    for ($i = 0; $i < 20; $i++) {
        PlanEntry::factory()->create([
            'user_id' => $user->id,
            'entry_date' => now()->addDays($i),
        ]);
    }

    $token = $user->createToken('test', ['view:own-plan'])->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/plan');

    $response->assertOk();
    expect($response->json('entries'))->toHaveCount(10);

    // Verify all returned dates are weekdays
    foreach ($response->json('entries') as $entry) {
        $date = Carbon::parse($entry['entry_date']);
        expect($date->isWeekday())->toBeTrue();
    }
});
```

**Checklist**:
- [ ] Test 401 without authentication
- [ ] Test successful response with valid token
- [ ] Verify JSON structure matches spec
- [ ] Test data scoping (only own entries)
- [ ] Test 10 weekdays limit
- [ ] Test all dates are weekdays

---

#### Test File 2: API Endpoints - ReportController
**File**: `tests/Feature/Api/ReportControllerTest.php`

- [ ] Create test: `lando artisan make:test Api/ReportControllerTest --pest`
- [ ] Add `use RefreshDatabase;`

**Test Cases - Authorization**:
```php
test('staff token without team-plans ability gets 403 on reports', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['view:own-plan'])->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/reports/team');

    $response->assertForbidden();
});

test('manager token with team-plans ability can access reports', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $token = $manager->createToken('test', ['view:team-plans'])->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/reports/team');

    $response->assertOk();
});

test('admin token with all-plans ability can access reports', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $token = $admin->createToken('test', ['view:all-plans'])->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/reports/team');

    $response->assertOk();
});
```

**Test Cases - Data Scoping**:
```php
test('manager with team-plans sees only their team members', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    $outsideUser = User::factory()->create();

    $date = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $teamMember->id,
        'entry_date' => $date,
        'location' => Location::JWS,
    ]);

    PlanEntry::factory()->create([
        'user_id' => $outsideUser->id,
        'entry_date' => $date,
        'location' => Location::JWN,
    ]);

    $token = $manager->createToken('test', ['view:team-plans'])->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/reports/team');

    $response->assertOk();
    expect($response->json('team_rows'))->toHaveCount(1);
    expect($response->json('team_rows.0.member_id'))->toBe($teamMember->id);
});

test('admin with all-plans sees all users', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $date = now()->startOfWeek();

    PlanEntry::factory()->create(['user_id' => $user1->id, 'entry_date' => $date]);
    PlanEntry::factory()->create(['user_id' => $user2->id, 'entry_date' => $date]);

    $token = $admin->createToken('test', ['view:all-plans'])->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/reports/team');

    $response->assertOk();
    expect($response->json('team_rows'))->toHaveCount(2);
});
```

**Test Cases - All Endpoints**:
```php
test('team report returns correct structure', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $token = $manager->createToken('test', ['view:team-plans'])->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/reports/team');

    $response->assertOk();
    $response->assertJsonStructure([
        'scope',
        'days' => [
            '*' => ['date', 'day_name']
        ],
        'team_rows' => [
            '*' => [
                'member_id', 'name',
                'days' => [
                    '*' => ['date', 'state', 'location', 'location_short', 'note']
                ]
            ]
        ]
    ]);
});

test('location report returns correct structure', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $token = $manager->createToken('test', ['view:team-plans'])->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/reports/location');

    $response->assertOk();
    $response->assertJsonStructure([
        'scope',
        'location_days' => [
            '*' => [
                'date', 'day_name',
                'locations'
            ]
        ]
    ]);
});

test('coverage report returns correct structure', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $token = $manager->createToken('test', ['view:team-plans'])->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/reports/coverage');

    $response->assertOk();
    $response->assertJsonStructure([
        'scope',
        'days' => [
            '*' => ['date', 'day_name']
        ],
        'coverage_matrix' => [
            '*' => [
                'location',
                'entries' => [
                    '*' => ['date', 'count']
                ]
            ]
        ]
    ]);
});

test('service availability report returns correct structure', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $token = $manager->createToken('test', ['view:team-plans'])->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/reports/service-availability');

    $response->assertOk();
    $response->assertJsonStructure([
        'scope',
        'days' => [
            '*' => ['date', 'day_name']
        ],
        'service_availability_matrix' => [
            '*' => [
                'service',
                'entries' => [
                    '*' => ['date', 'count', 'manager_only']
                ]
            ]
        ]
    ]);
});
```

**Checklist**:
- [ ] Test 403 for staff tokens on all report endpoints
- [ ] Test 200 for manager tokens
- [ ] Test 200 for admin tokens
- [ ] Test data scoping (team vs all)
- [ ] Test JSON structure for all four report types
- [ ] Test actual data accuracy (counts, names, etc.)

---

#### Test File 3: Profile Token Management
**File**: `tests/Feature/ProfileTokenManagementTest.php`

- [ ] Create test: `lando artisan make:test ProfileTokenManagementTest --pest`
- [ ] Add `use RefreshDatabase;`

**Test Cases**:
```php
test('staff user creates token with own-plan ability only', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->set('newTokenName', 'My Token')
        ->call('createToken');

    $token = $user->tokens()->first();

    expect($token)->not->toBeNull();
    expect($token->name)->toBe('My Token');
    expect($token->abilities)->toBe(['view:own-plan']);
});

test('manager creates token with own-plan and team-plans abilities', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $this->actingAs($manager);

    Livewire::test(Profile::class)
        ->set('newTokenName', 'Manager Token')
        ->call('createToken');

    $token = $manager->tokens()->first();

    expect($token->abilities)->toBe(['view:own-plan', 'view:team-plans']);
});

test('admin creates token with all three abilities', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin);

    Livewire::test(Profile::class)
        ->set('newTokenName', 'Admin Token')
        ->call('createToken');

    $token = $admin->tokens()->first();

    expect($token->abilities)->toBe(['view:own-plan', 'view:team-plans', 'view:all-plans']);
});

test('token name is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->set('newTokenName', '')
        ->call('createToken')
        ->assertHasErrors('newTokenName');
});

test('generated token is shown after creation', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test(Profile::class)
        ->set('newTokenName', 'Test Token')
        ->call('createToken');

    expect($component->get('generatedToken'))->not->toBeNull();
    expect($component->get('generatedToken'))->toBeString();
    expect(strlen($component->get('generatedToken')))->toBeGreaterThan(40); // Sanctum tokens are long
});

test('user can revoke their own token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test', ['view:own-plan']);

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->call('revokeToken', $token->accessToken->id);

    expect($user->tokens()->count())->toBe(0);
});

test('revoked token cannot be used for API calls', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test', ['view:own-plan']);
    $plainToken = $token->plainTextToken;

    // Verify token works
    $response = $this->withToken($plainToken)->getJson('/api/v1/plan');
    $response->assertOk();

    // Revoke token
    $this->actingAs($user);
    Livewire::test(Profile::class)
        ->call('revokeToken', $token->accessToken->id);

    // Verify token no longer works
    $response = $this->withToken($plainToken)->getJson('/api/v1/plan');
    $response->assertUnauthorized();
});

test('profile displays existing tokens with abilities', function () {
    $user = User::factory()->create();
    $user->createToken('Token 1', ['view:own-plan']);
    $user->createToken('Token 2', ['view:own-plan', 'view:team-plans']);

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->assertSee('Token 1')
        ->assertSee('Token 2')
        ->assertSee('view:own-plan')
        ->assertSee('view:team-plans');
});

test('closing modal resets state', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test(Profile::class)
        ->set('newTokenName', 'Test')
        ->set('generatedToken', 'fake-token')
        ->set('showTokenModal', true)
        ->call('closeTokenModal');

    expect($component->get('showTokenModal'))->toBeFalse();
    expect($component->get('generatedToken'))->toBeNull();
    expect($component->get('newTokenName'))->toBe('');
});
```

**Checklist**:
- [ ] Test auto-assignment of abilities (staff/manager/admin)
- [ ] Test token name validation
- [ ] Test generated token is returned
- [ ] Test token revocation
- [ ] Test revoked token returns 401 on API
- [ ] Test token display in UI
- [ ] Test modal state reset

---

#### Test File 4: ManagerReportService Unit Tests
**File**: `tests/Unit/ManagerReportServiceTest.php`

- [ ] Create test: `lando artisan make:test ManagerReportServiceTest --unit --pest`
- [ ] Add `use RefreshDatabase;`

**Test Cases**:
```php
test('getScopedUserIds returns only user ID for view:own-plan', function () {
    $service = new ManagerReportService();
    $user = User::factory()->create();

    $userIds = $service->getScopedUserIds($user, 'view:own-plan');

    expect($userIds)->toBe([$user->id]);
});

test('getScopedUserIds returns team member IDs for view:team-plans', function () {
    $service = new ManagerReportService();

    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $member1 = User::factory()->create();
    $member2 = User::factory()->create();

    $team->users()->attach([$member1->id, $member2->id]);

    $userIds = $service->getScopedUserIds($manager, 'view:team-plans');

    expect($userIds)->toHaveCount(2);
    expect($userIds)->toContain($member1->id);
    expect($userIds)->toContain($member2->id);
});

test('getScopedUserIds returns all user IDs for view:all-plans', function () {
    $service = new ManagerReportService();

    $admin = User::factory()->create(['is_admin' => true]);

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    $userIds = $service->getScopedUserIds($admin, 'view:all-plans');

    expect($userIds)->toHaveCount(4); // admin + 3 users
    expect($userIds)->toContain($admin->id);
    expect($userIds)->toContain($user1->id);
    expect($userIds)->toContain($user2->id);
    expect($userIds)->toContain($user3->id);
});

test('buildDays returns exactly 10 weekdays', function () {
    $service = new ManagerReportService();

    $days = $service->buildDays();

    expect($days)->toHaveCount(10);

    foreach ($days as $day) {
        expect($day['date']->isWeekday())->toBeTrue();
    }
});

test('buildDays starts from current week Monday', function () {
    $service = new ManagerReportService();

    $days = $service->buildDays();

    $expectedStart = now()->startOfWeek();

    // First day should be Monday (or Tuesday if Monday is the start)
    expect($days[0]['date']->toDateString())->toBe($expectedStart->toDateString());
});
```

**Checklist**:
- [ ] Test `getScopedUserIds()` for all three abilities
- [ ] Test `buildDays()` returns 10 weekdays
- [ ] Test `buildDays()` starts from Monday
- [ ] Test `buildDays()` filters out weekends

---

#### Update Existing Tests
**File**: `tests/Feature/ManagerReportTest.php`

- [ ] Run tests after refactoring: `lando artisan test --filter=ManagerReport`
- [ ] Verify all existing tests pass
- [ ] If failures occur, investigate if service refactor broke something
- [ ] Update tests if method signatures changed (unlikely)

---

### 8. Format & Run Tests

- [ ] Run Pint on all modified files: `vendor/bin/pint`
- [ ] Run API tests: `lando artisan test --filter=Api/`
- [ ] Run Profile tests: `lando artisan test --filter=ProfileTokenManagement`
- [ ] Run Service tests: `lando artisan test --filter=ManagerReportService`
- [ ] Run existing ManagerReport tests: `lando artisan test --filter=ManagerReport`
- [ ] Fix any failing tests
- [ ] Ask user if they want full test suite run

---

## Route Structure with Middleware

### Complete Routes File
```php
<?php

use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Existing endpoint
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// API v1 - Protected by Sanctum
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {

    // Personal Planning Data
    // Accessible by: ALL authenticated users with valid token
    Route::get('/plan', [PlanController::class, 'myPlan']);

    // Organizational Reporting
    // Accessible by: Managers and Admins only (requires view:team-plans OR view:all-plans)
    Route::prefix('reports')
        ->middleware('ability:view:team-plans,view:all-plans')
        ->group(function () {
            Route::get('/team', [ReportController::class, 'team']);
            Route::get('/location', [ReportController::class, 'location']);
            Route::get('/coverage', [ReportController::class, 'coverage']);
            Route::get('/service-availability', [ReportController::class, 'serviceAvailability']);
        });
});
```

### How the Middleware Works

#### Layer 1: Authentication (`auth:sanctum`)
- Applied to all `/api/v1/*` routes
- Verifies Bearer token is valid and not revoked
- Returns **401 Unauthorized** if:
  - No token provided
  - Invalid token
  - Revoked token

#### Layer 2: Authorization (`ability`)
- Applied only to `/api/v1/reports/*` routes
- Verifies token has required abilities
- Uses **OR logic** (token needs ANY ONE of the listed abilities)
- Returns **403 Forbidden** if token lacks required abilities

#### Example Scenarios:

**Scenario 1: Staff user accessing personal data**
```
Request: GET /api/v1/plan
Token abilities: ['view:own-plan']

✅ Layer 1: auth:sanctum → PASS (valid token)
✅ Layer 2: (not applied to this route)
✅ Result: 200 OK - User sees their own plan
```

**Scenario 2: Staff user trying to access reports**
```
Request: GET /api/v1/reports/team
Token abilities: ['view:own-plan']

✅ Layer 1: auth:sanctum → PASS (valid token)
❌ Layer 2: ability:view:team-plans,view:all-plans → FAIL (lacks both abilities)
❌ Result: 403 Forbidden
```

**Scenario 3: Manager accessing reports**
```
Request: GET /api/v1/reports/team
Token abilities: ['view:own-plan', 'view:team-plans']

✅ Layer 1: auth:sanctum → PASS (valid token)
✅ Layer 2: ability:view:team-plans,view:all-plans → PASS (has view:team-plans)
✅ Result: 200 OK - Manager sees team data (controller scopes to their teams)
```

**Scenario 4: Admin accessing reports**
```
Request: GET /api/v1/reports/coverage
Token abilities: ['view:own-plan', 'view:team-plans', 'view:all-plans']

✅ Layer 1: auth:sanctum → PASS (valid token)
✅ Layer 2: ability:view:team-plans,view:all-plans → PASS (has both!)
✅ Result: 200 OK - Admin sees all data (controller scopes to all users)
```

---

## Data Scoping Strategy

The middleware handles **authorization** (can you access this route?), but controllers handle **data scoping** (which data can you see?).

### In ReportController

Each report method follows this pattern:

1. **Determine Token Ability**
   ```php
   $ability = $this->getTokenAbility($request);
   // Returns 'view:team-plans' or 'view:all-plans'
   ```

2. **Get Scoped User IDs**
   ```php
   $userIds = $service->getScopedUserIds($user, $ability);
   // Returns [$user's team IDs] or [all user IDs]
   ```

3. **Load Scoped Users**
   ```php
   $teamMembers = User::whereIn('id', $userIds)->orderBy('surname')->get()->all();
   ```

4. **Build Report**
   ```php
   $teamRows = $service->buildTeamRows($teamMembers, $days, $entriesByUser);
   ```

### Scoping Examples

**Manager with `view:team-plans`**:
- `getScopedUserIds()` returns: `[5, 12, 18, 23]` (their team members)
- Team report shows 4 people
- Coverage shows only locations where those 4 people are
- Service availability counts only those 4 people

**Admin with `view:all-plans`**:
- `getScopedUserIds()` returns: `[1, 2, 3, ..., 50]` (all users)
- Team report shows 50 people
- Coverage shows all locations with all people
- Service availability counts all 50 people

**Why this matters for Power BI**:
- Manager's dashboard automatically shows only their team
- Admin's dashboard shows entire organization
- No need to filter in Power BI - API handles it
- Data security enforced at API layer

---

## Power BI Integration Guide

### Prerequisites
1. API is deployed and accessible
2. User has generated an API token from Profile page
3. User has copied token (it's only shown once!)

---

### Step-by-Step: Connecting Power BI

#### 1. Generate API Token
1. Log into the time tracker application
2. Navigate to **Profile** page
3. Scroll to **API Access** section
4. Click **Generate New Token**
5. Enter a descriptive name (e.g., "Power BI - Weekly Team Dashboard")
6. Click **Generate Token**
7. **IMPORTANT**: Copy the token immediately - it won't be shown again!
8. Store token securely (password manager, secure notes, etc.)

Your token will look like: `1|AbCdEfGhIjKlMnOpQrStUvWxYz1234567890`

---

#### 2. Connect Power BI to API

**Open Power BI Desktop**:
1. Click **Get Data** → **Web**
2. Select **Advanced** (not Basic)

**Configure the Request**:
1. **URL parts**: Enter full API endpoint URL
   - Example: `https://wcap.youruniversity.ac.uk/api/v1/reports/team`
2. **HTTP request header parameters (optional)**: Add new row
   - Header name: `Authorization`
   - Header value: `Bearer YOUR_TOKEN_HERE`
   - Replace `YOUR_TOKEN_HERE` with your actual token
3. Click **OK**

**Authentication**:
- If prompted for authentication method, select **Anonymous**
- (We're using Bearer token in header, not HTTP auth)

---

#### 3. Available API Endpoints

Choose the endpoint that matches your reporting needs:

| Endpoint | Best For | Power BI Visual |
|----------|----------|----------------|
| `/api/v1/plan` | Personal record keeping | Table of your own entries |
| `/api/v1/reports/team` | "Where is everyone?" | Matrix (person × date) |
| `/api/v1/reports/location` | "Who's in the office today?" | Stacked bar chart (location × people) |
| `/api/v1/reports/coverage` | Capacity planning | Heat map (location × date) |
| `/api/v1/reports/service-availability` | Service continuity | Gauge/card with conditional formatting |

---

#### 4. Transform Data in Power Query

After loading, Power BI will show the Power Query Editor.

**For Team Report** (`/api/v1/reports/team`):
1. Expand the `days` column → Select `date` and `day_name`
2. Expand the `team_rows` column → Select all fields
3. Expand the `team_rows.days` column → Select `date`, `location`, `note`
4. Set data types:
   - `date` → Date
   - `day_name` → Text
   - `location` → Text
   - `note` → Text
5. Rename columns for clarity (e.g., `team_rows.name` → `Employee`)

**For Location Report** (`/api/v1/reports/location`):
1. Expand `location_days` → Select `date`, `day_name`, `locations`
2. Expand `locations` column (this is nested JSON)
3. Each location (jws, jwn, etc.) becomes a column
4. Expand each location to get `members` array
5. Expand `members` to get `name` and `note`

**For Coverage** (`/api/v1/reports/coverage`):
1. Expand `coverage_matrix` → `location`, `entries`
2. Expand `entries` → `date`, `count`
3. Set types: Date, Text, Whole Number
4. This gives you: Location, Date, Count (perfect for heat maps)

**For Service Availability** (`/api/v1/reports/service-availability`):
1. Expand `service_availability_matrix` → `service`, `entries`
2. Expand `entries` → `date`, `count`, `manager_only`
3. Set types: Text, Date, Whole Number, True/False
4. Use `manager_only` for conditional formatting (e.g., show red when true)

---

#### 5. Create Visuals

**Example 1: Weekly Team Grid**
- Data source: Team Report endpoint
- Visual type: Matrix
- Rows: Employee name
- Columns: Date (grouped by day)
- Values: Location
- Conditional formatting: Different colors per location

**Example 2: Daily Location Distribution**
- Data source: Location Report endpoint
- Visual type: Stacked bar chart
- Axis: Date
- Legend: Location
- Values: Count of people
- Shows how many people at each location per day

**Example 3: Coverage Heat Map**
- Data source: Coverage endpoint
- Visual type: Table or Matrix with conditional formatting
- Rows: Location
- Columns: Date
- Values: Count
- Conditional formatting: Color scale (white = 0, dark blue = high)
- Instantly see gaps (white cells = no coverage)

**Example 4: Service Status Dashboard**
- Data source: Service Availability endpoint
- Visual type: Cards with conditional formatting
- Create card for each critical service
- Show count of available staff
- Red background when `manager_only = true`
- White/blank when `count = 0`

---

#### 6. Set Up Refresh Schedule

**Publish to Power BI Service**:
1. File → Publish → Publish to Power BI
2. Select workspace

**Configure Scheduled Refresh**:
1. In Power BI Service, go to dataset settings
2. Click **Scheduled refresh**
3. Set frequency (e.g., Daily at 6:00 AM)
4. Data will auto-update from API

**Note**: Refresh uses same token, so:
- Don't revoke the token or refresh will fail
- If you revoke, create new token and update data source credentials

---

#### 7. Best Practices

**Token Management**:
- Create one token per dashboard/report
- Use descriptive names: "Power BI - Executive Dashboard", "Power BI - IT Manager Weekly"
- If sharing reports, create separate tokens for each person
- Revoke tokens when reports are decommissioned

**Data Refresh**:
- API always returns "next 10 weekdays" from current date
- Daily refresh keeps data current
- Historical data requires custom date range (future enhancement)

**Performance**:
- API responses are typically < 1 second
- Use Power BI's built-in caching
- Refresh during off-hours to avoid load

**Security**:
- Never share tokens between people
- Store tokens in Power BI Service credential manager (encrypted)
- Tokens are scoped to user's permissions (manager vs admin)
- Revoking token immediately blocks access

---

## JSON Response Examples

### Example 1: My Plan (Personal Data)

**Request**:
```http
GET /api/v1/plan
Authorization: Bearer 1|AbC...xyz
```

**Response**:
```json
{
  "user": {
    "id": 42,
    "name": "Smith, John"
  },
  "date_range": {
    "start": "2025-11-06",
    "end": "2025-11-20"
  },
  "entries": [
    {
      "id": 123,
      "entry_date": "2025-11-06",
      "location": "jws",
      "location_label": "James Watt South",
      "note": "Working on server upgrades",
      "is_available": true,
      "is_holiday": false,
      "category": null,
      "category_label": null
    },
    {
      "id": 124,
      "entry_date": "2025-11-07",
      "location": "home",
      "location_label": "Home",
      "note": "Documentation day",
      "is_available": true,
      "is_holiday": false,
      "category": null,
      "category_label": null
    },
    {
      "id": 125,
      "entry_date": "2025-11-08",
      "location": null,
      "location_label": null,
      "note": null,
      "is_available": false,
      "is_holiday": true,
      "category": "leave",
      "category_label": "Leave"
    }
    // ... 7 more entries (10 total)
  ]
}
```

**Notes**:
- Always returns exactly 10 entries (one per weekday)
- `location` is enum value (jws, jwn, etc.) or null
- `location_label` is human-readable (for Power BI visuals)
- When `is_holiday = true`, location is typically null
- `category` is future feature (mostly null for now)

---

### Example 2: Team Report

**Request**:
```http
GET /api/v1/reports/team
Authorization: Bearer 2|XyZ...abc
```

**Response**:
```json
{
  "scope": "view:team-plans",
  "days": [
    { "date": "2025-11-06", "day_name": "Wednesday" },
    { "date": "2025-11-07", "day_name": "Thursday" },
    { "date": "2025-11-08", "day_name": "Friday" },
    { "date": "2025-11-11", "day_name": "Monday" },
    { "date": "2025-11-12", "day_name": "Tuesday" },
    { "date": "2025-11-13", "day_name": "Wednesday" },
    { "date": "2025-11-14", "day_name": "Thursday" },
    { "date": "2025-11-15", "day_name": "Friday" },
    { "date": "2025-11-18", "day_name": "Monday" },
    { "date": "2025-11-19", "day_name": "Tuesday" }
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
        },
        {
          "date": "2025-11-07",
          "state": "planned",
          "location": "home",
          "location_short": "Home",
          "note": "Email migration project"
        },
        {
          "date": "2025-11-08",
          "state": "away",
          "location": null,
          "location_short": null,
          "note": "No details"
        },
        // ... 7 more days
      ]
    },
    {
      "member_id": 12,
      "name": "Brown, Michael",
      "days": [
        // ... 10 days
      ]
    }
    // ... more team members
  ]
}
```

**States Explained**:
- `planned` - User has location set (working somewhere)
- `away` - User has entry but no location (holiday, WFH not specified, etc.)
- `missing` - No entry exists for this date

**Power BI Usage**:
- Create matrix: Rows = `team_rows.name`, Columns = `days.date`, Values = `location_short`
- Conditional formatting by `state` or `location`

---

### Example 3: Location Report

**Request**:
```http
GET /api/v1/reports/location
Authorization: Bearer 3|DeF...ghi
```

**Response**:
```json
{
  "scope": "view:all-plans",
  "location_days": [
    {
      "date": "2025-11-06",
      "day_name": "Wednesday",
      "locations": {
        "jws": {
          "label": "James Watt South",
          "members": [
            { "name": "Adams, Sarah", "note": "Support desk coverage" },
            { "name": "Chen, Wei", "note": "Network monitoring" },
            { "name": "Davis, Emma", "note": "User training sessions" }
          ]
        },
        "jwn": {
          "label": "James Watt North",
          "members": [
            { "name": "Evans, Robert", "note": "Lab equipment setup" }
          ]
        },
        "rankine": {
          "label": "Rankine Building",
          "members": []
        },
        "bo": {
          "label": "Boyd Orr Building",
          "members": [
            { "name": "Foster, Linda", "note": "Server room maintenance" }
          ]
        },
        "home": {
          "label": "Home",
          "members": [
            { "name": "Garcia, Carlos", "note": "Code review day" },
            { "name": "Harris, Jessica", "note": "Documentation" }
          ]
        }
        // ... other locations (other, joseph_black, etc.)
      }
    },
    {
      "date": "2025-11-07",
      "day_name": "Thursday",
      "locations": {
        // ... same structure for Thursday
      }
    }
    // ... 8 more days
  ]
}
```

**Power BI Usage**:
- Expand `location_days` → `locations` → each location
- Expand `members` to get count
- Stacked bar chart: Axis = date, Legend = location, Values = count of members
- Answers "How many people at each location per day?"

---

### Example 4: Coverage Matrix

**Request**:
```http
GET /api/v1/reports/coverage
Authorization: Bearer 4|JkL...mno
```

**Response**:
```json
{
  "scope": "view:team-plans",
  "days": [
    { "date": "2025-11-06", "day_name": "Wednesday" },
    { "date": "2025-11-07", "day_name": "Thursday" },
    // ... 8 more days
  ],
  "coverage_matrix": [
    {
      "location": "James Watt South",
      "entries": [
        { "date": "2025-11-06", "count": 3 },
        { "date": "2025-11-07", "count": 2 },
        { "date": "2025-11-08", "count": 1 },
        { "date": "2025-11-11", "count": 4 },
        { "date": "2025-11-12", "count": 3 },
        { "date": "2025-11-13", "count": 0 },  // Gap!
        { "date": "2025-11-14", "count": 2 },
        { "date": "2025-11-15", "count": 1 },
        { "date": "2025-11-18", "count": 3 },
        { "date": "2025-11-19", "count": 2 }
      ]
    },
    {
      "location": "James Watt North",
      "entries": [
        { "date": "2025-11-06", "count": 1 },
        { "date": "2025-11-07", "count": 1 },
        { "date": "2025-11-08", "count": 0 },  // Gap!
        { "date": "2025-11-11", "count": 2 },
        // ... more days
      ]
    },
    {
      "location": "Home",
      "entries": [
        { "date": "2025-11-06", "count": 7 },
        { "date": "2025-11-07", "count": 8 },
        // ... more days
      ]
    }
    // ... other locations
  ]
}
```

**Power BI Usage**:
- Matrix visual: Rows = `location`, Columns = `date`, Values = `count`
- Conditional formatting: White (0) → Blue (high)
- Instantly spot coverage gaps (white cells)
- Great for capacity planning

---

### Example 5: Service Availability

**Request**:
```http
GET /api/v1/reports/service-availability
Authorization: Bearer 5|PqR...stu
```

**Response**:
```json
{
  "scope": "view:all-plans",
  "days": [
    { "date": "2025-11-06", "day_name": "Wednesday" },
    { "date": "2025-11-07", "day_name": "Thursday" },
    // ... 8 more days
  ],
  "service_availability_matrix": [
    {
      "service": "Active Directory",
      "entries": [
        { "date": "2025-11-06", "count": 3, "manager_only": false },
        { "date": "2025-11-07", "count": 2, "manager_only": false },
        { "date": "2025-11-08", "count": 1, "manager_only": false },
        { "date": "2025-11-11", "count": 0, "manager_only": true },  // Only manager!
        { "date": "2025-11-12", "count": 0, "manager_only": false }, // No one!
        { "date": "2025-11-13", "count": 2, "manager_only": false },
        // ... more days
      ]
    },
    {
      "service": "Email Services",
      "entries": [
        { "date": "2025-11-06", "count": 4, "manager_only": false },
        { "date": "2025-11-07", "count": 4, "manager_only": false },
        { "date": "2025-11-08", "count": 3, "manager_only": false },
        // ... more days
      ]
    },
    {
      "service": "VPN",
      "entries": [
        { "date": "2025-11-06", "count": 2, "manager_only": false },
        { "date": "2025-11-07", "count": 1, "manager_only": false },
        { "date": "2025-11-08", "count": 0, "manager_only": true },
        // ... more days
      ]
    }
    // ... more services (DNS, Firewall, VLE, etc.)
  ]
}
```

**Field Meanings**:
- `count` - Number of service members with `is_available = true` for that day
- `manager_only = true` - Zero team members available, but manager is available (minimal coverage)
- `manager_only = false, count = 0` - No coverage at all (critical gap!)

**Power BI Usage**:
- Table with conditional formatting:
  - Green: `count >= 2` (good coverage)
  - Yellow: `count = 1` (single point of failure)
  - Orange: `manager_only = true` (minimal coverage)
  - Red: `count = 0 AND manager_only = false` (no coverage!)
- Senior management can instantly see service risks

---

## Testing Strategy

### Test Coverage Goals
- **Unit Tests**: Service class logic (getScopedUserIds, buildDays, etc.)
- **Feature Tests**: Full HTTP request/response cycle for all API endpoints
- **Integration Tests**: Livewire component token management
- **Authorization Tests**: Verify middleware correctly blocks/allows requests
- **Data Scoping Tests**: Verify managers see only team, admins see all

### Test Pyramid
```
          /\
         /  \    10 Unit Tests (ManagerReportService)
        /____\
       /      \
      /  Feat  \  25 Feature Tests (API endpoints, authorization, scoping)
     /  Tests   \
    /____________\
   /              \
  /   Integration  \ 8 Tests (Profile token management UI)
 /__________________\
```

### Test Execution Order
1. **Unit tests first** - Fast, catch logic errors early
2. **Feature tests** - API contract validation
3. **Integration tests** - UI token management
4. **Regression** - Existing ManagerReport tests still pass

### CI/CD Considerations
- Run tests on every commit (GitHub Actions, GitLab CI, etc.)
- API tests can run in parallel (no shared state)
- Use in-memory SQLite for speed
- Mock external services if any (none currently)

---

## Implementation Notes

### Why Service Class Despite Team Convention?

The team convention states: "Do not abstract to service classes without checking with the user first." This is a good default to avoid over-engineering.

**However, this is a classic exception where a service class is justified**:

1. **Extreme Duplication**: Exact same logic needed in two places (Livewire + API)
2. **Complex Logic**: 7+ methods, nested loops, database queries
3. **Maintainability**: Bug fixes and enhancements need to happen in one place
4. **Not Premature**: We already have the working code in ManagerReport.php - we're extracting, not speculating
5. **Team Buy-In**: User explicitly agreed to service class approach

**Alternative considered and rejected**:
- **Trait**: Could work, but less semantic than service class for this use case
- **Duplication**: Would lead to bugs when one controller gets updated and other doesn't

### Livewire Dependency Injection Pattern

Livewire components **cannot** use `__construct()` because they are re-constructed on every network request. Instead:

**Type-hint in methods**:
```php
public function render(ManagerReportService $service)
{
    // Laravel resolves $service from container
}

public function exportAll(ManagerReportService $service)
{
    // Same instance injected
}
```

**Behind the scenes**:
- Laravel's service container resolves type-hinted classes
- Works in `mount()`, `render()`, and all action methods
- Singleton services are reused within request
- No need to manually instantiate

### Sanctum Abilities vs. Laravel Policies

**Why abilities instead of policies for this API?**

1. **Simpler**: Abilities are strings on tokens, policies require Gate definitions
2. **Token-Scoped**: Abilities can differ per token (same user, different tokens, different access)
3. **Self-Documenting**: Abilities are visible on token in database and UI
4. **Standard OAuth Pattern**: Similar to OAuth scopes (familiar to Power BI users)
5. **Route-Level**: Middleware applies abilities at route level (cleaner separation)

**When to use policies instead**:
- Complex authorization logic (e.g., "can edit if owner or admin and not archived")
- Model-level permissions (e.g., "can update this specific Post")
- Business rules that change (abilities are harder to rename once tokens issued)

For this API, simple string abilities are perfect.

### Flux Modal Flyout Pattern

**Why flyout variant?**
- Better for forms with multiple fields
- Slides in from edge (less disruptive than center modal)
- More space for long tokens and instructions
- Consistent with AdminTeams component pattern

**State Management**:
```blade
<flux:modal name="create-token" variant="flyout" wire:model="showTokenModal">
```
- `wire:model` binds modal visibility to Livewire property
- Setting `$showTokenModal = true` opens modal
- Closing modal (X, cancel, outside click) sets to `false`
- Two-state modal (form vs. token display) uses `@if($generatedToken)`

### API Versioning Strategy

**Using `/v1/` prefix**:
- Allows breaking changes in `/v2/` later without disrupting existing integrations
- Power BI dashboards continue working on `/v1/` indefinitely
- Can deprecate old versions with advance notice

**What might trigger v2?**
- Changing JSON structure (rename fields, change nesting)
- Adding required query parameters
- Changing date formats
- Removing endpoints

**For now**:
- Keep v1 simple and stable
- Document any changes in API.md
- Avoid breaking changes if possible

---

## Success Criteria

- [ ] All tests passing (43+ tests total across unit/feature/integration)
- [ ] Code formatted with Pint (zero violations)
- [ ] Staff user can generate token and successfully call `/api/v1/plan`
- [ ] Staff user gets 403 when trying `/api/v1/reports/*` endpoints
- [ ] Manager user can generate token and call all `/api/v1/reports/*` for their team
- [ ] Admin user can generate token and call all `/api/v1/reports/*` for entire org
- [ ] Manager sees only their team's data (not other teams)
- [ ] Admin sees all data (all users, all teams)
- [ ] Tokens can be revoked from Profile page
- [ ] Revoked tokens immediately return 401 on API calls
- [ ] Generated token is shown once with copy button
- [ ] Token abilities are auto-assigned based on role
- [ ] JSON responses match documented structures
- [ ] Power BI can successfully connect using Bearer token
- [ ] Power BI data loads correctly and refreshes work
- [ ] Web UI (ManagerReport Livewire component) still works after refactor
- [ ] No N+1 queries (use debugbar or telescope to verify)

---

## Future Enhancements (Parked)

### Custom Date Ranges
Add query parameters to override default 10 weekdays:
```
GET /api/v1/reports/team?start_date=2025-11-01&end_date=2025-11-30
```
- Useful for historical reporting
- Requires validation (max 90 days to prevent abuse)

### Team Filtering
Allow managers/admins to filter by specific teams:
```
GET /api/v1/reports/coverage?team_ids[]=5&team_ids[]=12
```
- Useful for managers who manage multiple teams
- Could also filter by service, location, etc.

### Pagination
For very large organizations:
```
GET /api/v1/reports/team?page=2&per_page=50
```
- Current implementation loads all team members
- Works fine for ~100 users
- Larger orgs might need pagination

### Rate Limiting
Add throttle middleware to prevent abuse:
```php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // 60 requests per minute per token
});
```

### Token Expiration
Currently tokens never expire. Could add:
- Optional expiration dates (e.g., 90 days, 1 year)
- "Last used" timestamp to identify stale tokens
- Auto-revoke unused tokens after 6 months

### Webhooks
Notify when plans change:
```
POST https://powerbi.example.com/webhook/refresh
```
- Trigger Power BI refresh immediately on data change
- More efficient than scheduled refresh

### GraphQL Endpoint
For very flexible queries:
```graphql
query {
  teamMembers {
    name
    planEntries(startDate: "2025-11-01") {
      date
      location
    }
  }
}
```
- More complex to implement
- Useful if API consumers want very specific data shapes

---

## Appendix: Quick Reference

### API Endpoints Summary

| Method | Endpoint | Auth Required | Abilities Required | Returns |
|--------|----------|---------------|-------------------|---------|
| GET | `/api/v1/plan` | ✅ | Any valid token | User's own plan entries |
| GET | `/api/v1/reports/team` | ✅ | `view:team-plans` OR `view:all-plans` | Team report (person × day) |
| GET | `/api/v1/reports/location` | ✅ | `view:team-plans` OR `view:all-plans` | Location report (day × location) |
| GET | `/api/v1/reports/coverage` | ✅ | `view:team-plans` OR `view:all-plans` | Coverage matrix (counts) |
| GET | `/api/v1/reports/service-availability` | ✅ | `view:team-plans` OR `view:all-plans` | Service availability matrix |

### Token Abilities Auto-Assignment

| User Type | Conditions | Abilities Granted |
|-----------|-----------|-------------------|
| Staff | Default | `['view:own-plan']` |
| Manager | Has `managedTeams` | `['view:own-plan', 'view:team-plans']` |
| Admin | `is_admin === true` | `['view:own-plan', 'view:team-plans', 'view:all-plans']` |

### HTTP Status Codes

| Code | Meaning | When It Happens |
|------|---------|----------------|
| 200 | OK | Successful request with data |
| 401 | Unauthorized | No token, invalid token, or revoked token |
| 403 | Forbidden | Valid token but lacks required ability |
| 422 | Unprocessable Entity | Validation error (e.g., missing token name) |
| 500 | Internal Server Error | Something went wrong server-side |

### Common Power BI Errors

| Error | Cause | Solution |
|-------|-------|----------|
| "Unable to connect" | Wrong URL | Verify endpoint URL is correct |
| "401 Unauthorized" | Token missing or invalid | Check Bearer token in header |
| "403 Forbidden" | Insufficient abilities | Generate new token (may need admin/manager role) |
| "Cannot parse JSON" | API error or maintenance | Check server logs, try again later |
| "Refresh failed" | Token revoked | Create new token, update credentials |

### Useful Commands

```bash
# Create service class
lando artisan make:class Services/ManagerReportService

# Create API controllers
lando artisan make:controller Api/PlanController
lando artisan make:controller Api/ReportController

# Create tests
lando artisan make:test Api/PlanControllerTest --pest
lando artisan make:test Api/ReportControllerTest --pest
lando artisan make:test ProfileTokenManagementTest --pest
lando artisan make:test ManagerReportServiceTest --unit --pest

# Run tests
lando artisan test --filter=Api/
lando artisan test --filter=ProfileTokenManagement
lando artisan test --filter=ManagerReportService
lando artisan test --filter=ManagerReport

# Format code
vendor/bin/pint

# List all routes (verify API routes registered)
lando artisan route:list --path=api

# Generate API token via tinker (for testing)
lando tinker
>>> $user = User::find(1);
>>> $token = $user->createToken('Test', ['view:all-plans']);
>>> $token->plainTextToken;
```

---

## Questions? Issues?

If you encounter any issues during implementation:

1. **Check test output** - Tests often reveal the exact issue
2. **Review Laravel logs** - `storage/logs/laravel.log`
3. **Use Tinker** - Test service methods directly: `lando tinker`
4. **Check Boost docs** - `mcp__laravel-boost__search-docs` for Livewire/Flux/Sanctum questions
5. **Ask the user** - They're always happy to help!

---

**Last Updated**: 2025-11-06
**Status**: Ready for implementation
**Estimated Time**: 4-6 hours for full implementation + testing
