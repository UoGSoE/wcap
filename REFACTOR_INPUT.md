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

# Phase 2: Excel Import for Manager Plan Entries

## Summary

Managers can bulk-import plan entries for their team members via Excel file upload (.xlsx).

## Progress: ✅ Complete

**177 tests pass (667 assertions)**

---

## 🎯 LESSON REINFORCED: SIMPLICITY WINS

The original spec for this feature was **470 lines** of over-engineered complexity:
- Custom exceptions
- Form Request classes
- API endpoints with Sanctum abilities
- Laravel Excel with multiple concerns
- Modal integration with event dispatching

**What we actually built: ~150 lines of simple, working code.**

The user's guidance throughout:
- "What... _on earth_ is that code about?"
- "Can I ask where the [validation rules] come from?" → "I made them up."
- "Why are we converting it to a string?" → Because I over-thought it.

**The fix was always simpler than the original attempt.**

---

## What We Actually Built

### Files Created

| File | Lines | Purpose |
|------|-------|---------|
| `app/Services/PlanEntryRowValidator.php` | ~30 | Returns Laravel Validator - that's it |
| `app/Livewire/ImportPlanEntries.php` | ~80 | Full-page component: upload → preview → confirm |
| `resources/views/livewire/import-plan-entries.blade.php` | ~100 | Flux file-upload + preview tables |
| `tests/Feature/ImportPlanEntriesTest.php` | ~200 | 13 tests for validator, importer, component |

### Files Modified

| File | Change |
|------|--------|
| `app/Services/PlanEntryImport.php` | Uses shared validator class |
| `routes/web.php` | Added `/manager/import` route |
| `resources/views/livewire/manage-team-entries.blade.php` | Added "Import" button |

---

## Design Decisions (Simplified)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| File format | .xlsx only | That's what the Excel library supports |
| Max file size | 1MB | A few hundred rows is tiny |
| Date format | DD/MM/YYYY | UK users think in this format |
| Availability | Y/N | Simple, clear for users |
| Error handling | Import valid rows, show errors | More forgiving than all-or-nothing |
| UI | Full page, not modal | Better UX for preview/confirm flow |
| Authorization | Web layer only | Service class just does what it's told |

---

## Excel Format

```
email              | date       | location | note           | is_available
john@example.com   | 02/12/2025 | jws      | Project work   | Y
jane@example.com   | 02/12/2025 | jwn      |                | N
```

- Row 0 (header) is automatically skipped if validation fails
- Valid location values: `jws`, `jwn`, `rankine`, `boyd-orr`, `other`, `joseph-black`, `alwyn-william`, `gilbert-scott`, `kelvin`, `maths`

---

## User Flow

1. Manager clicks "Import" on Edit Team Plans page
2. Uploads .xlsx file (drag-drop or click)
3. Sees preview: valid rows (table) + error rows (with messages)
4. Clicks "Import X Valid Entries" or "Cancel"
5. Toast notification on success, form resets

---

## Key Lessons Learned

### 🚫 Don't Invent Requirements

I proposed validation rules like `mimes:csv,xlsx,xls|max:10240` without checking:
- The Excel library only handles .xlsx
- 10MB is absurd for a few hundred rows
- The user knows their constraints better than I do

**Ask first. Or better: wait to be told.**

### 🚫 Don't Convert Types Unnecessarily

Original bug: converting a Carbon date to string for `updateOrCreate`:
```php
// ❌ BAD: String comparison fails in SQLite
$entryDate = Carbon::createFromFormat('d/m/Y', $date)->format('Y-m-d');

// ✅ GOOD: Let Laravel handle it via model casts
$entryDate = Carbon::createFromFormat('d/m/Y', $date);
```

The model casts `entry_date` to a date. Pass a date. Simple.

### 🚫 Don't Extract Code Prematurely

First attempt at the validator extraction was 140 lines with:
- Nested array return types
- Multiple methods
- Display fields mixed with data
- "Convenience" wrapper methods

**What the user actually wanted:**
```php
class PlanEntryRowValidator
{
    public function validate(array $row): \Illuminate\Validation\Validator
    {
        return Validator::make([...], [...]);
    }
}
```

Return the validator. Let the caller decide what to do with it. ~30 lines.

### ✅ Simple Header Detection

No need for clever `looksLikeHeader()` logic:
```php
if ($result->fails()) {
    if ($index === 0) {
        continue;  // First row failed = probably header, skip it
    }
    // ... collect error
}
```

---

## Test Coverage

13 tests covering:
- Authorization (non-manager blocked)
- Page rendering
- Validator: valid row, unknown email, bad date, bad location, bad availability
- All location enum values accepted
- Import creates entries
- Import skips header
- Import returns errors for invalid rows
- Import updates existing entries (same user+date)

---

## What We Didn't Build (And Don't Need)

- ❌ API endpoint - not requested
- ❌ Custom exceptions - Laravel's validation is fine
- ❌ Form Request class - inline validation is simpler for Livewire
- ❌ Modal integration - full page is clearer
- ❌ Event dispatching - just reset the form
- ❌ CSV support - just use Excel
- ❌ Authorization in service class - handled by web layer

---

## Future: If API Is Needed

If an API endpoint is requested later, it would be straightforward:
1. Create controller that accepts file upload
2. Use the same `PlanEntryImport` service class
3. Return JSON with results

But don't build it until it's asked for. **YAGNI.**
