# Refactor: Manager-Entered Plans

## Summary
Change the app so managers enter plan data on behalf of their team members, instead of staff entering their own data.

## Progress: ✅ Implementation Complete (Untested)

All code has been written. Tests need to be run and potentially updated.

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

### 1. Run Existing Tests
```bash
lando php artisan test
```
Some HomePage tests will likely fail since the component structure changed significantly.

### 2. Update HomePage Tests
The existing `tests/Feature/HomePageTest.php` needs updating:
- Tests for manager redirect
- Tests for staff read-only view
- Remove/update tests that call `save()`, `copyNext()`, `copyRest()` directly on HomePage (those methods are now in PlanEntryEditor)

### 3. Write New Tests
Create `tests/Feature/ManageTeamEntriesTest.php`:
- Non-manager cannot access page (403)
- Manager sees their teams and members
- Manager can save entries for team member
- Entries saved with `created_by_manager = true`
- Manager cannot edit user outside their teams

Create `tests/Feature/PlanEntryEditorTest.php`:
- Entry loading with user defaults
- Save functionality
- Copy buttons work
- Read-only mode prevents saves

### 4. Manual Testing
Visit these URLs to verify:
- `/` as staff → should see read-only plan
- `/` as manager → should redirect to `/manager/report`
- `/manager/report` → should have "Edit Plans" button
- `/manager/entries` → should show team selector and member tabs

### 5. Run Pint
```bash
vendor/bin/pint --dirty
```

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
