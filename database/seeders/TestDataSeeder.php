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
            'Apps and Data',
            'Infrastructure',
            'Resilience',
            'Security',
            'Front Desk',
            'Other',
        ];

        $managers = [];
        $infrastructureTeamMembers = [];

        foreach ($teamNames as $teamName) {
            // Use admin2x as the manager of Infrastructure team
            if ($teamName === 'Infrastructure') {
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

            foreach (range(1, 10) as $i) {
                $user = User::factory()->create([
                    'username' => 'user'.strtolower($teamName).'1x'.$i,
                    'password' => Hash::make('secret'),
                ]);
                $user->teams()->attach($team);

                // Keep track of Infrastructure team members for plan entry generation
                if ($teamName === 'Infrastructure') {
                    $infrastructureTeamMembers[] = $user;
                }
            }
        }

        // Generate realistic plan entries for Infrastructure team members
        $this->generatePlanEntries($infrastructureTeamMembers);
    }

    private function generatePlanEntries(array $teamMembers): void
    {
        $startDate = now()->startOfWeek();
        $locations = [Location::HOME, Location::JWS, Location::JWN, Location::RANKINE, Location::BO];
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
                    'is_holiday' => false,
                    'created_by_manager' => false,
                ]);
            }
        }
    }
}
