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
- [ ] Generate `PlanEntry` model using artisan
- [ ] Add `belongsTo` relationship to User
- [ ] Add `casts()` method for Location enum and dates
- [ ] Set up `$fillable` attributes
- [ ] Create factory with realistic fake data

#### Team Model
- [ ] Generate `Team` model using artisan
- [ ] Add `belongsToMany` relationship to User (via team_user pivot)
- [ ] Add `belongsTo` relationship for manager (User)
- [ ] Create factory with realistic data

### 3. Update HomePage Livewire Component

- [ ] Add public properties for storing 14 days of entry data (array structure)
- [ ] Implement `mount()` method to load existing entries from database
- [ ] Implement `save()` method to persist all 14 days at once (using Form Request)
- [ ] Implement `copyNext($dayIndex)` method to copy entry to next day
- [ ] Implement `copyRest($dayIndex)` method to copy entry to all remaining days
- [ ] Add success notification after save

### 4. Update Blade Template (home-page.blade.php)

- [ ] Add `wire:model.live` bindings to note inputs (e.g., `entries.0.note`)
- [ ] Add `wire:model.live` bindings to location selects (e.g., `entries.0.location`)
- [ ] Add `wire:click="save"` to Save button
- [ ] Add `wire:loading` state to Save button
- [ ] Add `wire:click="copyNext(index)"` to "Copy next" buttons
- [ ] Add `wire:click="copyRest(index)"` to "Copy rest" buttons
- [ ] Add `wire:key` to the day loop for proper reactivity
- [ ] Add success toast notification component (using Flux)

### 5. Create Form Request for Validation

- [ ] Create `StorePlanEntriesRequest` form request class
- [ ] Add validation rules:
  - `entries.*.note`: nullable
  - `entries.*.location`: required (must be valid Location enum value)
  - `entries.*.entry_date`: required, date format
- [ ] Add custom error messages for better UX

### 6. Write Tests

#### Feature Tests
- [ ] Test: Rendering the page shows 14 days starting from Monday
- [ ] Test: Saving new entries creates database records
- [ ] Test: Saving updates existing entries (not duplicates)
- [ ] Test: Copy next functionality copies to the next day only
- [ ] Test: Copy rest functionality copies to all remaining days
- [ ] Test: Validation requires location field
- [ ] Test: Validation allows empty note field

#### Unit Tests (if needed)
- [ ] Test: `getDays()` returns correct 14-day array starting from week start

### 7. Run Tests & Format Code

- [ ] Run relevant feature tests with `--filter`
- [ ] Run full test suite to ensure nothing broke
- [ ] Run `vendor/bin/pint` to format all modified PHP files

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

## Future Enhancements (Not in v1)
- Add Category dropdown once we have data to inform the enum values
- Team manager view to see their team's plans
- Export/reporting functionality
- Holiday auto-detection
