<?php

namespace App\Services;

use App\Models\Location;
use App\Models\PlanEntry;
use App\Models\Service;
use App\Models\Team;
use App\Models\User;

class ManagerReportService
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private bool $showLocation = true,
        private bool $showAllUsers = false,
        private array $selectedTeams = [],
    ) {
        //
    }

    /**
     * Configure the service with specific options.
     */
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

    public function buildReportPayload(): array
    {
        $days = $this->buildDays();
        $teamMembers = $this->getTeamMembersArray();
        $availableTeams = $this->getAvailableTeams();
        $locations = Location::orderBy('name')->get();
        $physicalLocations = Location::physical()->orderBy('name')->get();

        // Collect all user IDs we'll need entries for (team members + service members/managers)
        $teamMemberIds = array_map(fn ($m) => $m->id, $teamMembers);
        $allUserIds = collect($teamMemberIds);

        $services = null;
        if (config('wcap.services_enabled')) {
            $services = Service::with(['users', 'manager'])->orderBy('name')->get();
            $serviceUserIds = $services->flatMap(fn ($s) => $s->users->pluck('id'))
                ->merge($services->map(fn ($s) => $s->manager?->id)->filter());
            $allUserIds = $allUserIds->merge($serviceUserIds);
        }

        // Load all entries once
        $start = $days[0]['key'];
        $end = end($days)['key'];
        $allEntries = PlanEntry::query()
            ->with('location')
            ->whereIn('user_id', $allUserIds->unique()->values()->toArray())
            ->whereBetween('entry_date', [$start, $end])
            ->get();

        $entriesByUser = $this->indexEntriesByUser($allEntries, $teamMemberIds);
        $teamRows = $this->buildTeamRows($teamMembers, $days, $entriesByUser);
        $locationDays = $this->buildLocationDays($days, $teamMembers, $entriesByUser, $locations);
        $coverageMatrix = $this->buildCoverageMatrix($days, $locationDays, $physicalLocations);

        $payload = [
            'days' => $days,
            'teamRows' => $teamRows,
            'locationDays' => $locationDays,
            'coverageMatrix' => $coverageMatrix,
            'locations' => $locations,
            'availableTeams' => $availableTeams,
        ];

        if (config('wcap.services_enabled')) {
            $payload['serviceAvailabilityMatrix'] = $this->buildServiceAvailabilityMatrixFromEntries($days, $services, $allEntries);
        }

        return $payload;
    }

    public function buildDays(): array
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

    public function buildEntriesByUser(array $teamMembers, array $days): array
    {
        $start = $days[0]['key'];
        $end = end($days)['key'];
        $userIds = array_map(fn ($member) => $member->id, $teamMembers);

        $entries = PlanEntry::query()
            ->with('location')
            ->whereIn('user_id', $userIds)
            ->whereBetween('entry_date', [$start, $end])
            ->get();

        return $this->indexEntriesByUser($entries, $userIds);
    }

    /**
     * Index a collection of entries by user ID and date.
     */
    private function indexEntriesByUser($entries, array $userIds): array
    {
        $indexed = [];

        foreach ($entries as $entry) {
            if (! in_array($entry->user_id, $userIds)) {
                continue;
            }

            $userId = $entry->user_id;
            $dateKey = $entry->entry_date->toDateString();

            if (! isset($indexed[$userId])) {
                $indexed[$userId] = [];
            }

            $indexed[$userId][$dateKey] = $entry;
        }

        return $indexed;
    }

    public function buildTeamRows(array $teamMembers, array $days, array $entriesByUser): array
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

                $state = 'missing';
                if ($entry !== null) {
                    $state = $entry->location_id === null ? 'away' : 'planned';
                }

                $row['days'][] = [
                    'date' => $day['date'],
                    'state' => $state,
                    'location' => $entry?->location,
                    'location_short' => $entry?->location?->shortLabel(),
                    'note' => $entry?->note ?? 'No details',
                ];
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function buildLocationDays(array $days, array $teamMembers, array $entriesByUser, $locations): array
    {
        $result = [];

        foreach ($days as $day) {
            $dateKey = $day['key'];
            $locationData = [];

            foreach ($locations as $location) {
                $locationData[$location->id] = [
                    'label' => $location->label(),
                    'is_physical' => $location->is_physical,
                    'members' => [],
                ];
            }

            foreach ($teamMembers as $member) {
                $entry = $entriesByUser[$member->id][$dateKey] ?? null;

                if ($entry && $entry->location_id !== null) {
                    $locationData[$entry->location_id]['members'][] = [
                        'name' => "{$member->surname}, {$member->forenames}",
                        'note' => $entry->note,
                    ];
                }
            }

            // Add show_danger flag after members are populated
            foreach ($locationData as $locationId => $locData) {
                $locationData[$locationId]['show_danger'] = empty($locData['members']) && $locData['is_physical'];
            }

            $result[] = [
                'date' => $day['date'],
                'locations' => $locationData,
            ];
        }

        return $result;
    }

    public function buildCoverageMatrix(array $days, array $locationDays, $locations): array
    {
        $matrix = [];

        foreach ($locations as $location) {
            $row = [
                'label' => $location->label(),
                'is_physical' => $location->is_physical,
                'entries' => [],
            ];

            foreach ($locationDays as $index => $dayData) {
                $members = $dayData['locations'][$location->id]['members'] ?? [];

                $row['entries'][] = [
                    'date' => $days[$index]['date'],
                    'count' => count($members),
                ];
            }

            $matrix[] = $row;
        }

        return $matrix;
    }

    public function buildServiceAvailabilityMatrix(array $days): array
    {
        $services = Service::with(['users', 'manager'])->orderBy('name')->get();

        $matrix = [];
        $start = $days[0]['key'];
        $end = end($days)['key'];

        // Collect all user IDs (service members + managers) to load entries in one query
        $allUserIds = $services->flatMap(fn ($s) => $s->users->pluck('id'))
            ->merge($services->map(fn ($s) => $s->manager?->id)->filter())
            ->unique()
            ->values()
            ->toArray();

        $allEntries = PlanEntry::query()
            ->whereIn('user_id', $allUserIds)
            ->whereBetween('entry_date', [$start, $end])
            ->get()
            ->groupBy('user_id');

        foreach ($services as $service) {
            $row = [
                'label' => $service->name,
                'entries' => [],
            ];

            $serviceMemberIds = $service->users->pluck('id')->toArray();

            // Filter entries for this service's members from pre-loaded data
            $serviceEntries = collect($serviceMemberIds)
                ->flatMap(fn ($id) => $allEntries->get($id, collect()))
                ->groupBy(fn ($e) => $e->entry_date->toDateString());

            $managerEntries = collect();
            if ($service->manager) {
                $managerEntries = $allEntries->get($service->manager->id, collect())
                    ->groupBy(fn ($e) => $e->entry_date->toDateString());
            }

            foreach ($days as $day) {
                $dateKey = $day['key'];
                $dayEntries = $serviceEntries->get($dateKey, collect());

                $availableCount = $dayEntries->filter(fn ($e) => $e->isAvailable())->count();

                $managerOnly = false;
                if ($availableCount === 0 && $service->manager) {
                    $managerDayEntries = $managerEntries->get($dateKey, collect());
                    $managerAvailable = $managerDayEntries->first(fn ($e) => $e->isAvailable());
                    $managerOnly = $managerAvailable !== null;
                }

                $row['entries'][] = [
                    'date' => $day['date'],
                    'count' => $availableCount,
                    'manager_only' => $managerOnly,
                ];
            }

            $matrix[] = $row;
        }

        return $matrix;
    }

    /**
     * Build service availability matrix using pre-loaded entries.
     */
    public function buildServiceAvailabilityMatrixFromEntries(array $days, $services, $allEntries): array
    {
        $matrix = [];
        $entriesByUser = $allEntries->groupBy('user_id');

        foreach ($services as $service) {
            $row = [
                'label' => $service->name,
                'entries' => [],
            ];

            $serviceMemberIds = $service->users->pluck('id')->toArray();

            // Filter entries for this service's members from pre-loaded data
            $serviceEntries = collect($serviceMemberIds)
                ->flatMap(fn ($id) => $entriesByUser->get($id, collect()))
                ->groupBy(fn ($e) => $e->entry_date->toDateString());

            $managerEntries = collect();
            if ($service->manager) {
                $managerEntries = $entriesByUser->get($service->manager->id, collect())
                    ->groupBy(fn ($e) => $e->entry_date->toDateString());
            }

            foreach ($days as $day) {
                $dateKey = $day['key'];
                $dayEntries = $serviceEntries->get($dateKey, collect());

                $availableCount = $dayEntries->filter(fn ($e) => $e->isAvailable())->count();

                $managerOnly = false;
                if ($availableCount === 0 && $service->manager) {
                    $managerDayEntries = $managerEntries->get($dateKey, collect());
                    $managerAvailable = $managerDayEntries->first(fn ($e) => $e->isAvailable());
                    $managerOnly = $managerAvailable !== null;
                }

                $row['entries'][] = [
                    'date' => $day['date'],
                    'count' => $availableCount,
                    'manager_only' => $managerOnly,
                ];
            }

            $matrix[] = $row;
        }

        return $matrix;
    }

    private function getTeamMembersArray(): array
    {
        return array_values($this->getTeamMembers()->all());
    }

    private function getTeamMembers()
    {
        $user = auth()->user();

        // If specific teams are selected, show only users from those teams
        if (! empty($this->selectedTeams)) {
            return Team::whereIn('id', $this->selectedTeams)
                ->with('users')
                ->get()
                ->flatMap(fn ($team) => $team->users)
                ->unique('id')
                ->sortBy('surname');
        }

        // If admin and toggle is on, show all users
        if ($user->isAdmin() && $this->showAllUsers) {
            return User::orderBy('surname')->get();
        }

        // Get all users from teams managed by this user
        return $user->managedTeams()
            ->with('users')
            ->get()
            ->flatMap(fn ($team) => $team->users)
            ->unique('id')
            ->sortBy('surname');
    }

    private function getAvailableTeams()
    {
        $user = auth()->user();

        // If admin and showing all users, show all teams
        if ($user->isAdmin() && $this->showAllUsers) {
            return Team::orderBy('name')->get();
        }

        // Otherwise, show only teams managed by this user
        return $user->managedTeams()->orderBy('name')->get();
    }

    /**
     * Get scoped user IDs based on token ability (for API use).
     * Returns array of user IDs that the given user can access based on their token ability.
     *
     * @param  User  $user  The authenticated user
     * @param  string  $tokenAbility  The token ability ('view:own-plan', 'view:team-plans', or 'view:all-plans')
     * @return array Array of user IDs
     */
    public function getScopedUserIds(User $user, string $tokenAbility): array
    {
        return match ($tokenAbility) {
            'view:own-plan' => [$user->id],
            'view:team-plans' => $user->managedTeams()
                ->with('users')
                ->get()
                ->flatMap(fn ($team) => $team->users)
                ->unique('id')
                ->pluck('id')
                ->toArray(),
            'view:all-plans' => User::pluck('id')->toArray(),
            default => [],
        };
    }
}
