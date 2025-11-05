<?php

namespace App\Livewire;

use App\Enums\Location;
use App\Models\User;
use Livewire\Component;

class ManagerReport extends Component
{
    public bool $showLocation = true;

    public function mount(): void
    {
        $user = auth()->user();

        // Check if user is a manager
        if ($user->managedTeams->isEmpty()) {
            abort(403, 'You do not manage any teams.');
        }
    }

    public function render()
    {
        $days = $this->getDays();
        $teamMembers = $this->getTeamMembers();

        // Get all plan entries for team members for the date range
        $startDate = $days[0]->format('Y-m-d');
        $endDate = $days[9]->format('Y-m-d');

        $teamMemberIds = $teamMembers->pluck('id')->toArray();

        $planEntries = \App\Models\PlanEntry::query()
            ->whereIn('user_id', $teamMemberIds)
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->get()
            ->groupBy('user_id');

        // Organize data for "By Location" tab
        $daysByLocation = [];
        foreach ($days as $day) {
            $dateKey = $day->format('Y-m-d');
            $daysByLocation[$dateKey] = [];

            foreach ($teamMembers as $member) {
                $entry = $planEntries->get($member->id)?->first(function ($entry) use ($dateKey) {
                    return $entry->entry_date->format('Y-m-d') === $dateKey;
                });

                if ($entry) {
                    $location = $entry->location->value;
                    if (! isset($daysByLocation[$dateKey][$location])) {
                        $daysByLocation[$dateKey][$location] = [];
                    }
                    $daysByLocation[$dateKey][$location][] = [
                        'member' => $member,
                        'note' => $entry->note,
                    ];
                }
            }
        }

        // Calculate coverage grid: location x day counts
        $coverage = [];
        foreach (Location::cases() as $location) {
            $coverage[$location->value] = [];
            foreach ($days as $day) {
                $dateKey = $day->format('Y-m-d');
                $coverage[$location->value][$dateKey] = count($daysByLocation[$dateKey][$location->value] ?? []);
            }
        }

        return view('livewire.manager-report', [
            'days' => $days,
            'teamMembers' => $teamMembers,
            'planEntries' => $planEntries,
            'daysByLocation' => $daysByLocation,
            'locations' => Location::cases(),
            'coverage' => $coverage,
        ]);
    }

    private function getDays(): array
    {
        $start = now()->startOfWeek();

        return collect(range(0, 13))
            ->map(fn ($offset) => $start->copy()->addDays($offset))
            ->filter(fn ($day) => $day->isWeekday())
            ->values()
            ->toArray();
    }

    private function getTeamMembers()
    {
        $user = auth()->user();

        // Get all users from teams managed by this user
        return $user->managedTeams()
            ->with('users')
            ->get()
            ->flatMap(fn ($team) => $team->users)
            ->unique('id')
            ->sortBy('surname');
    }
}
