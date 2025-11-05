<?php

namespace Database\Seeders;

use App\Enums\Location;
use App\Models\PlanEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'username' => 'admin2x',
            'email' => 'admin2x@example.com',
            'password' => Hash::make('secret'),
        ]);

        $teamNames = [
            'Service Operations / Applications & Data',
            'Service Operations / Infrastructure',
            'Service Operations / Research Computing',
            'Service Delivery / Help Desk',
            'Service Delivery / Fulfilment',
            'Service Resilience',
            'Principle Engineer EduTech',
            'Principle Engineer Linux',
        ];

        $managers = [];
        $allUsers = [];

        foreach ($teamNames as $teamName) {
            $minTeamMembers = str_starts_with($teamName, 'Principle Engineer') ? 1 : 2;
            $maxTeamMembers = str_starts_with($teamName, 'Principle Engineer') ? 1 : 5;
            // Use admin2x as the manager of Infrastructure team
            if (str_starts_with($teamName, 'Service Operations')) {
                $manager = $admin;
            } else {
                $manager = User::factory()->create([
                    'username' => strtolower($teamName).'2x',
                    'email' => 'manager.'.strtolower($teamName).'2x@example.com',
                    'password' => Hash::make('secret'),
                ]);
            }

            $team = Team::factory()->create([
                'name' => $teamName,
                'manager_id' => $manager->id,
            ]);
            $managers[$teamName] = $manager;

            foreach (range($minTeamMembers, $maxTeamMembers) as $i) {
                $user = User::factory()->create([
                    'username' => 'user'.strtolower($teamName).'1x'.$i,
                    'password' => Hash::make('secret'),
                ]);
                $user->teams()->attach($team);

                // Track all users for plan entry generation
                $allUsers[] = $user;
            }
        }

        // Generate realistic plan entries for all users
        $this->generatePlanEntries($allUsers);

        // Mark some users as unavailable for random days
        $this->markSomeUsersUnavailable($allUsers);
    }

    private function generatePlanEntries(array $teamMembers): void
    {
        $startDate = now()->startOfWeek();
        $locations = Location::cases();
        $notes = [
            'Support tickets',
            'Server maintenance',
            'Project planning',
            'Team meetings',
            'Infrastructure upgrades',
            'Network monitoring',
            'Security patches',
            'Documentation',
            'User training',
            'System backups',
            'Performance tuning',
            'Troubleshooting',
        ];

        // Create entries for 10 weekdays (2 weeks)
        foreach ($teamMembers as $index => $member) {
            // Give each member a preferred location pattern
            $primaryLocation = $locations[$index % count($locations)];
            $secondaryLocation = $locations[($index + 1) % count($locations)];

            for ($dayOffset = 0; $dayOffset < 14; $dayOffset++) {
                $date = $startDate->copy()->addDays($dayOffset);

                // Skip weekends
                if ($date->isWeekend()) {
                    continue;
                }

                // Vary the location (80% primary, 20% secondary)
                $location = (rand(1, 10) <= 8) ? $primaryLocation : $secondaryLocation;

                // Pick a random note
                $note = $notes[array_rand($notes)];

                PlanEntry::create([
                    'user_id' => $member->id,
                    'entry_date' => $date,
                    'location' => $location,
                    'note' => $note,
                    'category' => null,
                    'is_available' => true,
                    'is_holiday' => false,
                    'created_by_manager' => false,
                ]);
            }
        }
    }

    private function markSomeUsersUnavailable(array $allUsers): void
    {
        $unavailableReasons = [
            'Annual leave',
            'Holiday',
            'Training course',
            'Conference',
            'Sick leave',
        ];

        // Randomly select 5 users to have some unavailable time
        $selectedUsers = collect($allUsers)->random(min(5, count($allUsers)));

        foreach ($selectedUsers as $user) {
            // Each user gets 1-5 random days marked as unavailable
            $daysOff = rand(1, 5);

            $userEntries = PlanEntry::where('user_id', $user->id)
                ->get()
                ->random(min($daysOff, PlanEntry::where('user_id', $user->id)->count()));

            foreach ($userEntries as $entry) {
                $entry->update([
                    'is_available' => false,
                    'location' => null,
                    'note' => $unavailableReasons[array_rand($unavailableReasons)],
                ]);
            }
        }
    }
}
