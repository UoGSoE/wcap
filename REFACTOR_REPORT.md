# Manager Report Refactor Plan

## Goals
- Remove complex `@if` chains and inline `@php` blocks from `resources/views/livewire/manager-report.blade.php`.
- Deliver complete, presentation-ready data from `App\Livewire\ManagerReport` so the Blade template only iterates and makes obvious decisions.
- Keep the Livewire component readable for junior developers by using plain PHP arrays and `foreach` loops instead of collection helpers.

## Component Changes

### 1. Preload Days and Plan Entries
- Build an array of weekdays for the two-week window:
  ```php
  private function buildDays(): array
  {
      $start = now()->startOfWeek();
      $days = [];

      for ($offset = 0; $offset < 14; $offset++) {
          $day = $start->copy()->addDays($offset);

          if ($day->isWeekday()) {
              $days[] = [
                  'date' => $day,
                  'key' => $day->toDateString(),
              ];
          }
      }

      return $days;
  }
  ```
- Query all plan entries for the selected users and index them by `user_id => date => entry`:
  ```php
  private function buildEntriesByUser(array $teamMembers, array $days): array
  {
      $start = $days[0]['date']->toDateString();
      $end = end($days)['date']->toDateString();
      $userIds = array_map(fn ($member) => $member->id, $teamMembers);

      $entries = PlanEntry::query()
          ->whereIn('user_id', $userIds)
          ->whereBetween('entry_date', [$start, $end])
          ->get();

      $indexed = [];

      foreach ($entries as $entry) {
          $userId = $entry->user_id;
          $dateKey = $entry->entry_date->toDateString();

          if (! isset($indexed[$userId])) {
              $indexed[$userId] = [];
          }

          $indexed[$userId][$dateKey] = $entry;
      }

      return $indexed;
  }
  ```

### 2. Team Rows View Model
- Produce one array per team member with simple day states:
  ```php
  private function buildTeamRows(array $teamMembers, array $days, array $entriesByUser): array
  {
      $rows = [];

      foreach ($teamMembers as $member) {
          $row = [
              'member_id' => $member->id,
              'name' => "{$member->surname}, {$member->forenames}",
              'days' => [],
          ];

          foreach ($days as $day) {
              $dateKey = $day['key'];
              $entry = $entriesByUser[$member->id][$dateKey] ?? null;

              $row['days'][] = [
                  'date' => $day['date'],
                  'state' => $entry === null ? 'missing'
                      : ($entry->location === null ? 'away' : 'planned'),
                  'location_short' => $entry?->location?->shortLabel(),
                  'note' => $entry?->note ?? 'No details',
              ];
          }

          $rows[] = $row;
      }

      return $rows;
  }
  ```
- Blade loops the array and switches on `state` for the correct badge/text.

### 3. Location Days View Model
- Pre-fill every location for every day so the template always shows a card with content:
  ```php
  private function buildLocationDays(array $days, array $teamMembers, array $entriesByUser): array
  {
      $result = [];
      $locations = Location::cases();

      foreach ($days as $day) {
          $dateKey = $day['key'];
          $locationData = [];

          foreach ($locations as $location) {
              $locationData[$location->value] = [
                  'label' => $location->label(),
                  'members' => [],
              ];
          }

          foreach ($teamMembers as $member) {
              $entry = $entriesByUser[$member->id][$dateKey] ?? null;

              if ($entry && $entry->location !== null) {
                  $locationData[$entry->location->value]['members'][] = [
                      'name' => "{$member->surname}, {$member->forenames}",
                      'note' => $entry->note,
                  ];
              }
          }

          $result[] = [
              'date' => $day['date'],
              'locations' => $locationData,
          ];
      }

      return $result;
  }
  ```
- Blade renders each card using:
  - `{{ $location['label'] }}`
  - Count via `count($location['members'])`
  - List staff or show `No staff` when empty.

### 4. Coverage Matrix
- Reuse the location data to count staff per location/day:
  ```php
  private function buildCoverageMatrix(array $days, array $locationDays): array
  {
      $matrix = [];

      foreach (Location::cases() as $location) {
          $row = [
              'label' => $location->label(),
              'entries' => [],
          ];

          foreach ($locationDays as $index => $dayData) {
              $members = $dayData['locations'][$location->value]['members'];

              $row['entries'][] = [
                  'date' => $days[$index]['date'],
                  'count' => count($members),
              ];
          }

          $matrix[] = $row;
      }

      return $matrix;
  }
  ```

### 5. Updated `render()` Skeleton
```php
public function render()
{
    $days = $this->buildDays();
    $teamMembers = array_values($this->getTeamMembers()->all());
    $availableTeams = $this->getAvailableTeams();

    $entriesByUser = $this->buildEntriesByUser($teamMembers, $days);
    $teamRows = $this->buildTeamRows($teamMembers, $days, $entriesByUser);
    $locationDays = $this->buildLocationDays($days, $teamMembers, $entriesByUser);
    $coverageMatrix = $this->buildCoverageMatrix($days, $locationDays);

    return view('livewire.manager-report', [
        'days' => $days,
        'teamRows' => $teamRows,
        'locationDays' => $locationDays,
        'coverageMatrix' => $coverageMatrix,
        'locations' => Location::cases(),
        'availableTeams' => $availableTeams,
        'showLocation' => $this->showLocation,
    ]);
}
```

## Blade Updates
- Team tab: iterate `$teamRows`; use one `@if` on `state` inside each cell.
- Location tab: loop `$locationDays`, render card for every location, show `No staff` when list is empty.
- Coverage tab: loop `$coverageMatrix`; no inline PHP needed.

## Benefits
- Junior-friendly: data is prepared with plain arrays and `foreach`, no collection magic.
- Blade becomes a simple declarative view with predictable content—no more empty Flux cards.
- Future enhancements (sorting, filtering) happen in the component without reworking the template.
