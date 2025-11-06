<?php

namespace App\Livewire;

use App\Enums\Location;
use App\Models\Service;
use App\Models\Team;
use App\Models\User;
use Livewire\Component;

class ManagerReport extends Component
{
    public bool $showLocation = true;

    public bool $showAllUsers = false;

    public array $selectedTeams = [];

    public function mount(): void
    {
        $user = auth()->user();

        // Check if user is a manager
        if ($user->managedTeams->isEmpty()) {
            abort(403, 'You do not manage any teams.');
        }

        if ($user->isAdmin()) {
            $this->showAllUsers = true;
        }
    }

    public function render()
    {
        $days = $this->buildDays();
        $teamMembers = $this->getTeamMembersArray();
        $availableTeams = $this->getAvailableTeams();

        $entriesByUser = $this->buildEntriesByUser($teamMembers, $days);
        $teamRows = $this->buildTeamRows($teamMembers, $days, $entriesByUser);
        $locationDays = $this->buildLocationDays($days, $teamMembers, $entriesByUser);
        $coverageMatrix = $this->buildCoverageMatrix($days, $locationDays);
        $serviceAvailabilityMatrix = $this->buildServiceAvailabilityMatrix($days);

        return view('livewire.manager-report', [
            'days' => $days,
            'teamRows' => $teamRows,
            'locationDays' => $locationDays,
            'coverageMatrix' => $coverageMatrix,
            'serviceAvailabilityMatrix' => $serviceAvailabilityMatrix,
            'locations' => Location::cases(),
            'availableTeams' => $availableTeams,
        ]);
    }

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

    private function buildEntriesByUser(array $teamMembers, array $days): array
    {
        $start = $days[0]['key'];
        $end = end($days)['key'];
        $userIds = array_map(fn ($member) => $member->id, $teamMembers);

        $entries = \App\Models\PlanEntry::query()
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

    private function buildServiceAvailabilityMatrix(array $days): array
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
}
