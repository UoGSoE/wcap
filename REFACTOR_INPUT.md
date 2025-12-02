# Refactor: Manager-Entered Plans

## Summary
Change the app so managers enter plan data on behalf of their team members, instead of staff entering their own data.

## Progress: ✅ Complete

All code written and tested. **164 tests pass (628 assertions)**. All UI issues resolved.

---

## Session Notes (Latest)

### What We Fixed

1. **"My Plan" Self-Team Feature** - Managers can now edit their own plan entries
   - Added virtual "My Plan" team with manual property assignment
   - Defaults to self-team on mount
   - `editingMyOwnPlan()` helper handles type coercion (strings from URL vs ints)
   - `created_by_manager = false` when editing own plan, `true` for team members

2. **Type Coercion Bug** - Fixed `#[Url]` property fighting with Flux select
   - Livewire's `#[Url]` examples don't use type hints (URL params are always strings)
   - Changed `public ?int $selectedTeamId = 0;` → `public $selectedTeamId = 0;`
   - Page refresh now shows correct state

3. **Virtual Team ID Bug** - Fixed Flux combobox showing `0` instead of "My Plan"
   - Root cause: `Team::make(['id' => 0, 'name' => 'My Plan'])` silently ignored `id` because it's not in `$fillable`
   - Fix: Manual property assignment bypasses mass assignment protection
   ```php
   // ❌ BAD: id silently ignored
   $selfTeam = Team::make(['id' => 0, 'name' => 'My Plan']);

   // ✅ GOOD: Manual assignment works
   $selfTeam = new Team();
   $selfTeam->id = 0;
   $selfTeam->name = 'My Plan';
   ```

4. **Test Updates** - Updated 5 existing tests, added 6 new tests for self-team feature

### Key Lessons Learned

#### 🔗 Livewire `#[Url]` and Type Declarations

> Livewire's `#[Url]` attribute examples in the docs **never use type declarations**.
> URL query params are always strings, so type hints cause hydration issues.
> Trust your tests and helper methods to handle type safety instead.

#### 🏗️ Mass Assignment and `$fillable`

> `$fillable` blocks mass assignment even on `make()`, not just `create()`.
> When creating virtual/in-memory models with specific IDs, you must set properties manually.
> This is by design - `id` shouldn't be mass-assignable for security reasons.

---

---

## What Was Built

### New Files Created

| File | Status | Purpose |
|------|--------|---------|
| `app/Livewire/PlanEntryEditor.php` | ✅ Created | Shared sub-component with all entry editing logic |
| `resources/views/livewire/plan-entry-editor.blade.php` | ✅ Created | Form UI for the entry editor |
| `app/Livewire/ManageTeamEntries.php` | ✅ Created | Manager's team/member selection wrapper |
| `resources/views/livewire/manage-team-entries.blade.php` | ✅ Created | Team selector + member tabs + embeds editor |

### Files Modified

| File | Status | Change |
|------|--------|--------|
| `routes/web.php` | ✅ Done | Added `/manager/entries` route |
| `app/Livewire/HomePage.php` | ✅ Done | Simplified to thin wrapper - redirects managers, embeds sub-component |
| `resources/views/livewire/home-page.blade.php` | ✅ Done | Shows read-only callout, embeds PlanEntryEditor |
| `resources/views/livewire/manager-report.blade.php` | ✅ Done | Added "Edit Plans" button in header |
| `resources/views/components/layouts/app.blade.php` | ✅ Done | Added "Edit Team Plans" sidebar link for managers |

---

## Architecture: Shared Sub-component

To avoid code duplication, we extracted a `PlanEntryEditor` sub-component:

```blade
{{-- HomePage (staff view - read-only) --}}
<livewire:plan-entry-editor :user="$user" :read-only="true" />

{{-- ManageTeamEntries (manager editing) --}}
<livewire:plan-entry-editor
    :user="$selectedUser"
    :read-only="false"
    :created-by-manager="true"
    :key="$selectedUserId"
/>
```

The `:key` forces re-mount when selected user changes.

---

## Next Steps (For Next Session)

### ✅ DONE: Tests
- All 164 tests pass (628 assertions)
- `ManageTeamEntriesTest.php` - 18 tests covering team selection, authorization, self-team
- `PlanEntryEditorTest.php` - 19 tests covering save, copy, validation, read-only mode
- `HomePageTest.php` - Updated for new component structure

### ✅ DONE: UI Issues Fixed
- Flux combobox now correctly shows "My Plan" on initial page load
- Root cause was `$fillable` blocking `id` on `Team::make()` - fixed with manual property assignment

---

## User Flows (Recap)

### Staff (non-managers)
1. Log in → land on `/` (HomePage)
2. See their own 14-day plan **read-only**
3. Cannot edit anything - just view

### Managers
1. Log in → redirected to `/manager/report`
2. Click "Edit Plans" button → goes to `/manager/entries`
3. Select team (if multiple) → select team member via tabs → edit their plan
4. Save, then select next member or go back to report

---

## Key Design Decisions

1. **Sub-component architecture** - Avoids duplicating entry editing logic
2. **Keep existing HomePage methods in PlanEntryEditor** - Easy to re-enable staff editing if director changes mind
3. **`created_by_manager` flag** - Track which entries were manager-created
4. **`:key` on sub-component** - Forces re-mount when selected user changes

## No Database Changes Required

The `created_by_manager` column already exists in `plan_entries` table.

---

---

# Phase 2: CSV/XLSX Import & API for Manager Plan Entries

## Summary

Add functionality for managers to bulk-import plan entries for their team members via CSV/XLSX file upload. Includes both a Livewire UI (modal on ManageTeamEntries page) and an API endpoint.

## Progress: 🚧 In Progress

---

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| CSV format | One row per entry | Flexible for partial updates, clear structure |
| User identification | Email address | Unique, reliable lookup |
| API authorization | Reuse `view:team-plans` | Simpler, existing tokens work immediately |
| Error handling | Reject entire file | All-or-nothing prevents missed failures |
| UI location | Modal on ManageTeamEntries | Contextually appropriate, no new routes needed |

---

## CSV Format Specification

```csv
email,date,location,note,is_available
john.smith@example.com,2025-12-02,jws,Working on project,true
jane.doe@example.com,2025-12-02,jwn,,true
john.smith@example.com,2025-12-03,home,Remote day,true
jane.doe@example.com,2025-12-03,,,false
```

**Columns:**
| Column | Required | Notes |
|--------|----------|-------|
| `email` | Yes | Must match existing user in manager's scope |
| `date` | Yes | Y-m-d format (e.g., 2025-12-02) |
| `location` | Conditional | Required when is_available=true. Values from Location enum |
| `note` | No | Free text, can be empty |
| `is_available` | No | Default: true. Accepts: true/false, 1/0, yes/no |

**Valid Location Values** (from Location enum):
`home`, `jws`, `jwn`, `rankine`, `boyd-orr`, `other`, `joseph-black`, `alwyn-william`, `gilbert-scott`, `kelvin`, `maths`

---

## Implementation Checklist

### 1. Create Laravel Excel Import Class

- [ ] Create `app/Imports/` directory (doesn't exist yet)
- [ ] Create `app/Imports/TeamPlanEntriesImport.php`
  - [ ] Implement `ToCollection` concern (collect all rows before processing)
  - [ ] Implement `WithHeadingRow` concern (use column headers)
  - [ ] Accept `User $manager` in constructor for authorization checks
  - [ ] Phase 1: Validate ALL rows first (collect errors)
    - [ ] Lookup user by email - error if not found
    - [ ] Check manager can manage this user - error if not in scope
    - [ ] Validate date format (Y-m-d)
    - [ ] Validate location is valid enum value (if provided)
    - [ ] Validate location required when is_available=true
  - [ ] Phase 2: If ANY errors, throw exception with all error messages
  - [ ] Phase 3: If no errors, import all entries using `updateOrCreate`
  - [ ] Set `created_by_manager = true` on all imported entries
- [ ] Create custom `ImportValidationException` for structured errors

### 2. Create Form Request for File Validation

- [ ] Create `app/Http/Requests/ImportTeamPlanEntriesRequest.php`
  - [ ] Validate file is required
  - [ ] Validate MIME types: csv, xlsx, xls
  - [ ] Validate max file size (e.g., 10MB)
  - [ ] Add authorization check (user must be manager or admin)

### 3. Create API Controller

- [ ] Create `app/Http/Controllers/Api/TeamPlanController.php`
  - [ ] `import(ImportTeamPlanEntriesRequest $request)` method
  - [ ] Check user has `view:team-plans` or `view:all-plans` ability
  - [ ] Run the import via `TeamPlanEntriesImport`
  - [ ] Return JSON response with:
    - [ ] Success: `{ success: true, imported_count: N }`
    - [ ] Error: `{ success: false, errors: [...] }`

### 4. Add API Route

- [ ] Update `routes/api.php`
  - [ ] Add `POST /api/v1/team-plan-entries/import`
  - [ ] Apply `auth:sanctum` middleware
  - [ ] Apply `abilities:view:team-plans,view:all-plans` middleware

### 5. Create Livewire Upload Component

- [ ] Create `app/Livewire/ImportTeamPlanEntries.php`
  - [ ] Use `WithFileUploads` trait
  - [ ] `$file` property with validation rules
  - [ ] `$errors` array for displaying validation issues
  - [ ] `$importedCount` for success feedback
  - [ ] `$showFormatGuide` toggle for help accordion
  - [ ] `import()` method:
    - [ ] Validate file
    - [ ] Run `TeamPlanEntriesImport`
    - [ ] Handle success (toast, dispatch event)
    - [ ] Handle errors (display in UI)
  - [ ] Dispatch `plan-entries-imported` event on success

### 6. Create Livewire View

- [ ] Create `resources/views/livewire/import-team-plan-entries.blade.php`
  - [ ] File upload area (Flux component or standard input)
  - [ ] Upload button with loading state
  - [ ] Error display area (list all validation errors)
  - [ ] Success message with count
  - [ ] Collapsible CSV format guide/help section
  - [ ] Example CSV download link (optional, nice-to-have)

### 7. Integrate into ManageTeamEntries

- [ ] Update `app/Livewire/ManageTeamEntries.php`
  - [ ] Add `public bool $showImportModal = false` property
  - [ ] Add `#[On('plan-entries-imported')]` listener to close modal and refresh
- [ ] Update `resources/views/livewire/manage-team-entries.blade.php`
  - [ ] Add "Import CSV" button in header area
  - [ ] Add modal/flyout containing the import component

### 8. Write Tests

#### Import Class Tests
- [ ] Test: Unknown email rejects entire file
- [ ] Test: User not in manager's teams rejects entire file
- [ ] Test: Invalid date format rejects file
- [ ] Test: Invalid location value rejects file
- [ ] Test: Missing location when is_available=true rejects file
- [ ] Test: Empty location allowed when is_available=false
- [ ] Test: Valid CSV creates entries with created_by_manager=true
- [ ] Test: Import updates existing entries (same user+date = updateOrCreate)
- [ ] Test: Admin can import for any user
- [ ] Test: Manager can only import for own team members

#### Livewire Component Tests
- [ ] Test: Non-manager sees error / cannot access
- [ ] Test: Manager can upload valid CSV
- [ ] Test: Invalid file type rejected
- [ ] Test: Validation errors displayed in UI
- [ ] Test: Success message shows count
- [ ] Test: Modal closes and parent refreshes on success

#### API Endpoint Tests
- [ ] Test: Unauthenticated request returns 401
- [ ] Test: User without manager ability returns 403
- [ ] Test: Manager can import via API
- [ ] Test: Returns structured error response for invalid data
- [ ] Test: Returns success response with count

### 9. Final Steps

- [ ] Run `lando php artisan test --filter=Import` to verify all tests pass
- [ ] Run `vendor/bin/pint --dirty` to format code
- [ ] Manual testing in browser
- [ ] Update PROJECT_PLAN.md with implementation notes

---

## Files to Create

| File | Purpose |
|------|---------|
| `app/Imports/TeamPlanEntriesImport.php` | Laravel Excel import with validation |
| `app/Exceptions/ImportValidationException.php` | Custom exception for import errors |
| `app/Http/Requests/ImportTeamPlanEntriesRequest.php` | File validation |
| `app/Http/Controllers/Api/TeamPlanController.php` | API endpoint |
| `app/Livewire/ImportTeamPlanEntries.php` | Upload component |
| `resources/views/livewire/import-team-plan-entries.blade.php` | Upload form view |
| `tests/Feature/ImportTeamPlanEntriesTest.php` | Feature tests |

## Files to Modify

| File | Changes |
|------|---------|
| `routes/api.php` | Add `POST /api/v1/team-plan-entries/import` |
| `app/Livewire/ManageTeamEntries.php` | Add `$showImportModal` property + event listener |
| `resources/views/livewire/manage-team-entries.blade.php` | Add import button + modal |

---

## Key Code Patterns

### Import Class Structure

```php
class TeamPlanEntriesImport implements ToCollection, WithHeadingRow
{
    private array $errors = [];
    private int $importedCount = 0;

    public function __construct(private User $manager) {}

    public function collection(Collection $rows): void
    {
        $validatedEntries = [];

        // Phase 1: Validate all rows
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // Header is row 1
            $result = $this->validateRow($row->toArray(), $rowNum);

            if ($result['valid']) {
                $validatedEntries[] = $result['entry'];
            }
        }

        // Phase 2: Reject all if any errors
        if (count($this->errors) > 0) {
            throw new ImportValidationException($this->errors);
        }

        // Phase 3: Import all
        foreach ($validatedEntries as $entry) {
            PlanEntry::updateOrCreate(
                ['user_id' => $entry['user_id'], 'entry_date' => $entry['entry_date']],
                $entry
            );
            $this->importedCount++;
        }
    }

    private function validateRow(array $row, int $rowNum): array
    {
        // Email lookup
        $user = User::where('email', $row['email'])->first();
        if (!$user) {
            $this->errors[] = "Row {$rowNum}: Unknown email '{$row['email']}'";
            return ['valid' => false];
        }

        // Authorization check
        if (!$this->canManageUser($user)) {
            $this->errors[] = "Row {$rowNum}: '{$row['email']}' is not in your teams";
            return ['valid' => false];
        }

        // Date validation
        // Location validation
        // is_available logic
        // ...

        return ['valid' => true, 'entry' => [...]];
    }

    private function canManageUser(User $user): bool
    {
        if ($this->manager->isAdmin()) {
            return true;
        }

        return $this->manager->managedTeams()
            ->whereHas('users', fn($q) => $q->where('users.id', $user->id))
            ->exists();
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }
}
```

### Boolean Parsing Helper

```php
private function parseBoolean(mixed $value, bool $default = true): bool
{
    if ($value === null || $value === '') {
        return $default;
    }

    $value = strtolower(trim((string) $value));

    return match ($value) {
        'true', '1', 'yes', 'y' => true,
        'false', '0', 'no', 'n' => false,
        default => $default,
    };
}
```

---

## Potential Gotchas to Watch For

### 1. Excel Date Cells
Excel stores dates as serial numbers (days since 1900). Laravel Excel usually handles this, but test with real .xlsx files to ensure dates parse correctly.

### 2. CSV Encoding
CSV files might have BOM (byte order mark) or non-UTF8 encoding. May need to handle this gracefully.

### 3. Empty Rows
Excel files often have trailing empty rows. Skip rows where email is empty.

### 4. Case Sensitivity
- Email comparison should be case-insensitive
- Location enum values - check how the enum handles case

### 5. Livewire File Upload Temporary Storage
Livewire stores uploaded files temporarily. Ensure we process and clean up properly.

### 6. Large Files
Consider adding a row limit (e.g., 500 rows) to prevent timeouts on very large imports.

### 7. Concurrent Imports
If two managers import at the same time for overlapping users, `updateOrCreate` should handle this gracefully, but worth considering.

---

## Reference Files to Read Before Implementation

- `app/Livewire/PlanEntryEditor.php` - Existing save logic pattern
- `app/Exports/ManagerReportExport.php` - Laravel Excel pattern in this codebase
- `app/Http/Controllers/Api/PlanController.php` - API structure pattern
- `app/Livewire/ManageTeamEntries.php` - Integration point for modal
- `app/Enums/Location.php` - Valid location values
- `tests/Feature/Api/ApiEndpointsTest.php` - API test pattern with Sanctum
