# Service Implementation Plan

## Overview
Implementing a complete Service management system following the exact pattern established by the Team CRUD system. Services are parallel to Teams - they have a manager and members, with full admin CRUD capabilities.

## Design Decisions
- **Terminology**: Using `manager_id` (same as Teams) for consistency
- **Navigation**: "Manage Services" link in admin section, below "Manage Teams"
- **Fields**: Minimal implementation - just `name` and `manager_id` (like Teams)
- **Route**: `/admin/services` (mirrors `/admin/teams`)

---

# Phase 1: Admin CRUD ✅ COMPLETED!

All tasks completed successfully on 2025-11-06. Full service management is now available at `/admin/services`.

**Summary**:
- 15 tests, 55 assertions - all passing ✓
- Database migrations created and structured
- Full CRUD UI with Flux components
- Member transfer functionality on delete
- Test data seeding with 6 realistic services

---

## Phase 1 Implementation Checklist

### 1. Database Layer

#### Services Table Migration
- [X] Update `2025_11_06_092254_create_services_table.php`
  - Add `name` column (string, required)
  - Add `manager_id` column (foreignId to users, nullable, nullOnDelete)
  - Keep timestamps

#### Service-User Pivot Table
- [X] Create new migration: `create_service_user_table.php`
  - Fields: `id`, `service_id`, `user_id`, `timestamps`
  - Foreign keys:
    - `service_id` → services table (cascade on delete)
    - `user_id` → users table (cascade on delete)
  - Mirror the `team_user` pivot structure exactly

#### ServiceFactory
- [X] Update `database/factories/ServiceFactory.php`
  - Add realistic fake data for `name` (e.g., "Active Directory Service", "Email Service", etc.)
  - Add `manager_id` using `User::factory()`
  - Follow the pattern from TeamFactory

---

### 2. Service Model

- [X] Update `app/Models/Service.php`
  - Add `$fillable = ['name', 'manager_id']`
  - Add `users()` relationship: `belongsToMany(User::class)`
  - Add `manager()` relationship: `belongsTo(User::class, 'manager_id')`
  - Follow exact structure of Team model

---

### 3. User Model

- [X] Update `app/Models/User.php`
  - Add `services()` relationship: `belongsToMany(Service::class)`
  - Add `managedServices()` relationship: `hasMany(Service::class, 'manager_id')`
  - Place these after the existing team relationships (maintain conventions)

---

### 4. AdminServices Livewire Component

- [X] Create `app/Livewire/AdminServices.php` (clone AdminTeams.php)

#### Properties
```php
public ?int $editingServiceId = null;
public string $serviceName = '';
public ?int $managerId = null;
public array $selectedUserIds = [];
public bool $showEditModal = false;
public bool $showDeleteModal = false;
public ?int $deletingServiceId = null;
public ?int $transferServiceId = null;
```

#### Methods to Implement
- [X] `mount()` - Authorization check (admin only, abort 403)
- [X] `render()` - Load services with manager and users relationships, order by name
- [X] `createService()` - Set editingServiceId to -1, reset form, show modal
- [X] `editService(int $serviceId)` - Load service, populate form, show modal
- [X] `save()` - Validate and save (create or update), sync members, toast notification
  - Validation rules:
    - `serviceName`: required, string, max:255, unique (dynamic for create/update)
    - `managerId`: required, integer, exists:users,id
    - `selectedUserIds`: array
    - `selectedUserIds.*`: integer, exists:users,id
  - Use `sync()` for members relationship
- [X] `cancelEdit()` - Close modal, reset all fields
- [X] `confirmDelete(int $serviceId)` - Set deletingServiceId, reset transferServiceId, show modal
- [X] `deleteService()` - Optional transfer with `syncWithoutDetaching()`, then delete
- [X] `closeDeleteModal()` - Close modal without deleting, reset state

**Key Implementation Detail**: Use `wire:submit` on the form, button should be `type="submit"`

---

### 5. AdminServices Blade View

- [X] Create `resources/views/livewire/admin-services.blade.php` (clone admin-teams.blade.php)

#### Page Header
- Heading: "Service Management"
- Subheading: "Create and manage services, assign members, and set managers."
- Primary button: "Create New Service"

#### Services Table
- [X] Use `flux:table` component
- Columns: Service Name, Manager (full_name), Members (count badge), Actions
- Actions: Edit button (pencil icon), Delete button (trash icon, danger variant)
- Empty state message when no services exist

#### Edit/Create Modal
- [X] `flux:modal` with `variant="flyout"` and `wire:model="showEditModal"`
- [X] Form with `wire:submit="save"`
- [X] Service Name field: `flux:input` with `wire:model="serviceName"`
- [X] Manager field: `flux:select` with `variant="combobox"`, `wire:model="managerId"`
- [X] Members field: `flux:pillbox` with `multiple`, `wire:model="selectedUserIds"`
- [X] Submit button: `type="submit"`, dynamic text (Create/Save)
- [X] Cancel button: `wire:click="cancelEdit"`

#### Delete Modal
- [X] `flux:modal` with `variant="flyout"` and `wire:model="showDeleteModal"`
- [X] Warning text about deletion
- [X] Optional transfer field: `flux:select` with `variant="combobox"`, `wire:model="transferServiceId"`
- [X] Delete button: `variant="danger"`, `wire:click="deleteService"`
- [X] Cancel button: `wire:click="closeDeleteModal"`

---

### 6. Routing

- [X] Update `routes/web.php`
  - Add route: `Route::get('/admin/services', \App\Livewire\AdminServices::class)->name('admin.services');`
  - Place it near the admin.teams route

---

### 7. Navigation

- [X] Update `resources/views/components/layouts/app.blade.php`
  - Add "Manage Services" link in the `@admin` section
  - Place it below "Manage Teams"
  - Choose appropriate icon (e.g., `wrench-screwdriver`, `server-stack`, or `cog`)
  - Use: `href="{{ route('admin.services') }}"` with `wire:navigate`

---

### 8. Test Coverage

- [X] Create `tests/Feature/AdminServicesTest.php` (clone AdminTeamsTest.php)

#### Test Scenarios (15 comprehensive tests)
1. [X] Admin can view service management page
2. [X] Non-admin cannot access service management page (assertForbidden)
3. [X] Admin can see all services in the list
4. [X] Admin can create a new service with members
5. [X] Service name must be unique when creating
6. [X] Admin can edit an existing service
7. [X] Service name must be unique when updating (but can keep same name)
8. [X] Admin can update service members (test sync behavior)
9. [X] Admin can delete a service without transferring members
10. [X] Admin can delete a service and transfer members to another service
11. [X] Validation requires service name
12. [X] Validation requires manager
13. [X] Admin can cancel editing (state reset)
14. [X] Admin can close delete modal (state reset)
15. [X] Service list shows correct member counts

**Testing patterns to follow**:
- Use `RefreshDatabase` trait
- Use Pest's `actingAs()` helper
- Test both happy and failure paths
- Verify pivot table relationships
- Test edge cases (like keeping same name on update)
- Use descriptive variable names (e.g., `$managerUser`, `$memberOne`)
- Assert database state with `assertDatabaseHas`/`assertDatabaseMissing`

---

### 9. Update TestDataSeeder

- [X] Update `database/seeders/TestDataSeeder.php`
  - Add realistic service data (e.g., "Active Directory", "Email Service", "Backup Service")
  - Assign managers to services
  - Attach members to services
  - Follow the pattern used for teams

---

### 10. Run & Verify

- [X] Run migrations: `lando artisan migrate`
- [X] Run filtered tests: `lando artisan test --filter=AdminServices`
- [X] Verify all 15 tests pass
- [X] Run code formatter: `vendor/bin/pint --dirty`
- [X] Manual verification in browser:
  - [X] Visit `/admin/services`
  - [X] Create a new service
  - [X] Edit a service
  - [X] Delete a service with transfer
  - [X] Delete a service without transfer
  - [X] Verify validation errors display correctly

---

## Technical Notes

### Key Patterns to Follow
1. **Form Submission**: Use `wire:submit` on the form, not `wire:click` with loading states
2. **Unique Validation**: Dynamic rule - `'unique:services,name'` for create, `'unique:services,name,'.$this->editingServiceId` for update
3. **Relationship Sync**: Use `sync()` for updating members, `syncWithoutDetaching()` for member transfer on delete
4. **Modal State**: Separate modals for edit/create vs delete operations
5. **Authorization**: Check in `mount()` method with `abort(403)` if not admin
6. **User Ordering**: Always order users by `surname`, then `forenames` (not by non-existent `name` field)
7. **Full Name Display**: Use `$user->full_name` accessor throughout

### Flux Component Notes
- `flux:select` with `variant="combobox"` is inherently searchable (no `searchable` prop needed)
- `flux:pillbox` with `multiple` for multi-select
- `flux:modal` with `variant="flyout"` for side panels
- Modal visibility controlled by `wire:model` bound to boolean properties
- Flux handles validation error display automatically

---

## What We Built in Phase 1

Services are organizational units similar to Teams, but typically representing IT services (like Active Directory, Email, Backups, etc.) rather than organizational teams. They have:
- A unique name
- A designated manager
- Multiple members who work on that service
- Full CRUD operations for admins
- Member transfer capability when deleting

This implementation provides a complete parallel to the Team management system with consistent patterns, making the codebase maintainable and predictable.

---

# Phase 2: Service Availability Tab in Manager Report ✅ COMPLETED!

All tasks completed successfully on 2025-11-06. Service availability tracking is now available at `/manager/report` under the "Service Availability" tab.

**Summary**:
- 8 tests, 25 assertions - all passing ✓
- Backend matrix calculation with availability filtering
- Visual grid UI matching Coverage tab design
- Test data seeder updated with 20 realistic services

## Overview
Add a fourth tab to the ManagerReport component that shows service coverage based on member availability. Similar to the Coverage tab (which shows locations × days), this will show services × days with availability counts.

## Design Decisions
- **Service Display**: Show all services in the system (not filtered by manager's teams)
- **Admin Toggle Impact**: When admin toggles "Show All Users", service list is NOT affected (always shows all services)
- **Count Scope**: Count ALL service members' availability, not just team members shown in the report
- **Availability Logic**: Only count PlanEntries where `is_available === true`
- **Visual Design**: Same as Coverage tab - gray cells with count when people available, blank when no one available

## Implementation Checklist

### 1. Update ManagerReport Component

- [X] Import Service model at top of file
  ```php
  use App\Models\Service;
  ```

- [X] Add new method `buildServiceAvailabilityMatrix()` after `buildCoverageMatrix()`
  - Load all services (ordered by name)
  - For each service, get its members
  - For each day, load plan entries for service members
  - Count entries where `is_available === true`
  - Return matrix structure: `[['label' => 'Service Name', 'entries' => [['date' => Carbon, 'count' => N], ...]], ...]`

- [X] Update `render()` method
  - Call `buildServiceAvailabilityMatrix($days)`
  - Pass `$serviceAvailabilityMatrix` to view

### 2. Update Manager Report Blade View

- [X] Add fourth tab to tabs list (line ~35)
  ```blade
  <flux:tab name="service-availability">Service Availability</flux:tab>
  ```

- [X] Add new tab panel after coverage tab (clone coverage structure)
  - Heading: "Service availability at a glance"
  - Description: "Shows how many people on each service are available each day. Gray cells indicate at least one person available."
  - CSS Grid with `grid-template-columns: 200px repeat(10, 1fr)` (wider first column for service names)
  - Header row with date labels
  - Service rows with availability counts
  - Gray background `bg-zinc-300 dark:bg-zinc-700` when count > 0
  - Show count number in cell when count > 0
  - Leave blank when count = 0

### 3. Testing

- [X] Create `tests/Feature/ManagerReportServiceAvailabilityTest.php`
  - Test: Service availability tab displays all services
  - Test: Only counts entries where is_available = true
  - Test: Shows 0 (blank) when no one available
  - Test: Shows correct counts with multiple available people
  - Test: Works with admin toggle (services always show all, not affected by toggle)

- [X] Run tests: `lando artisan test --filter=ManagerReportServiceAvailability`
- [X] Verify all tests pass
- [X] Run pint: `vendor/bin/pint --dirty`

### 4. Manual Verification

- [X] Visit `/manager/report` as a manager
- [X] Click "Service Availability" tab
- [X] Verify all services are listed
- [X] Verify counts reflect only available people
- [X] Verify gray cells appear when people available
- [X] Verify blank cells when no one available
- [X] Test with admin "Show All Users" toggle (services should not change)

## Technical Implementation Notes

### buildServiceAvailabilityMatrix() Method Structure

```php
private function buildServiceAvailabilityMatrix(array $days): array
{
    // Get all services (not filtered by teams)
    $services = Service::with('users')->orderBy('name')->get();

    $matrix = [];
    $start = $days[0]['date']->startOfDay();
    $end = $days[count($days) - 1]['date']->endOfDay();

    foreach ($services as $service) {
        $row = [
            'label' => $service->name,
            'entries' => [],
        ];

        // Get service member IDs
        $serviceMemberIds = $service->users->pluck('id')->toArray();

        // Load plan entries for service members in date range
        $serviceEntries = PlanEntry::whereIn('user_id', $serviceMemberIds)
            ->whereBetween('entry_date', [$start, $end])
            ->get()
            ->groupBy(fn($e) => $e->entry_date->format('Y-m-d'));

        // For each day, count available members
        foreach ($days as $day) {
            $dateKey = $day['date']->format('Y-m-d');
            $dayEntries = $serviceEntries->get($dateKey, collect());

            // Count only entries where is_available is true
            $availableCount = $dayEntries->filter(fn($e) => $e->is_available === true)->count();

            $row['entries'][] = [
                'date' => $day['date'],
                'count' => $availableCount,
            ];
        }

        $matrix[] = $row;
    }

    return $matrix;
}
```

### Key Differences from Coverage Tab

1. **Data Source**: Services (not Locations enum)
2. **Filtering**: Availability flag (`is_available === true`) vs presence of location
3. **Scope**: All service members vs filtered team members
4. **Column Width**: 200px (vs 150px) for longer service names

### Benefits for Managers

- **At-a-glance view**: Quickly see which services might be understaffed
- **Proactive planning**: Identify coverage gaps before they become problems
- **Resource allocation**: See where backup coverage is needed
- **Capacity planning**: Understand service capacity across the two-week window

---

## What We Built in Phase 2

A manager tool to visualize service staffing levels across a two-week period. The grid layout makes it easy to spot:
- Services with no coverage on specific days
- Services consistently understaffed
- Days where multiple services have reduced availability
- Patterns in service availability over time

This helps managers ensure critical services have adequate coverage and identify where cross-training or backup assignments might be needed.

### Files Modified
- **app/Livewire/ManagerReport.php** - Added Service import and buildServiceAvailabilityMatrix() method
- **resources/views/livewire/manager-report.blade.php** - Added fourth tab with grid layout
- **tests/Feature/ManagerReportServiceAvailabilityTest.php** - New test file with 8 comprehensive tests
- **database/seeders/TestDataSeeder.php** - Updated to create 20 services with 1-3 members each

### Key Achievements
✅ Clean separation of concerns - service data independent of team filters
✅ Reusable matrix pattern matching existing Coverage tab
✅ Comprehensive test coverage (8 tests, 25 assertions)
✅ Realistic test data with 20 university IT services
✅ Simple, readable code following project conventions
