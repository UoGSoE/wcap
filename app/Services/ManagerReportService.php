<?php

namespace App\Services;

use App\Enums\Location;
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

        $entriesByUser = $this->buildEntriesByUser($teamMembers, $days);
        $teamRows = $this->buildTeamRows($teamMembers, $days, $entriesByUser);
        $locationDays = $this->buildLocationDays($days, $teamMembers, $entriesByUser);
        $coverageMatrix = $this->buildCoverageMatrix($days, $locationDays);
        $serviceAvailabilityMatrix = $this->buildServiceAvailabilityMatrix($days);

        return [
            'days' => $days,
            'teamRows' => $teamRows,
            'locationDays' => $locationDays,
            'coverageMatrix' => $coverageMatrix,
            'serviceAvailabilityMatrix' => $serviceAvailabilityMatrix,
            'locations' => Location::cases(),
            'availableTeams' => $availableTeams,
        ];
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
                    $state = $entry->location === null ? 'away' : 'planned';
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

    public function buildLocationDays(array $days, array $teamMembers, array $entriesByUser): array
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

    public function buildCoverageMatrix(array $days, array $locationDays): array
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

    public function buildServiceAvailabilityMatrix(array $days): array
    {
        $services = Service::with(['users', 'manager'])->orderBy('name')->get();

        $matrix = [];
        $start = $days[0]['key'];
        $end = end($days)['key'];

        foreach ($services as $service) {
            $row = [
                'label' => $service->name,
                'entries' => [],
            ];

            $serviceMemberIds = $service->users->pluck('id')->toArray();

            $serviceEntries = \App\Models\PlanEntry::query()
                ->whereIn('user_id', $serviceMemberIds)
                ->whereBetween('entry_date', [$start, $end])
                ->get()
                ->groupBy(fn ($e) => $e->entry_date->toDateString());

            $managerEntries = collect();
            if ($service->manager) {
                $managerEntries = \App\Models\PlanEntry::query()
                    ->where('user_id', $service->manager->id)
                    ->whereBetween('entry_date', [$start, $end])
                    ->get()
                    ->groupBy(fn ($e) => $e->entry_date->toDateString());
            }

            foreach ($days as $day) {
                $dateKey = $day['key'];
                $dayEntries = $serviceEntries->get($dateKey, collect());

                $availableCount = $dayEntries->filter(fn ($e) => $e->is_available === true)->count();

                $managerOnly = false;
                if ($availableCount === 0 && $service->manager) {
                    $managerDayEntries = $managerEntries->get($dateKey, collect());
                    $managerAvailable = $managerDayEntries->first(fn ($e) => $e->is_available === true);
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
