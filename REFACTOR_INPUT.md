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
