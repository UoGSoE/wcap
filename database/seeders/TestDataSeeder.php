<?php

namespace Database\Seeders;

use App\Enums\Location;
use App\Models\PlanEntry;
use App\Models\Service;
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
        $allUsers[] = $admin;

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
                $allUsers[] = $manager;
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

        // Create services with members
        $serviceNames = [
            'Active Directory Service',
            'Email Service',
            'Backup Service',
            'Network Infrastructure Service',
            'Database Service',
            'Web Hosting Service',
            'DNS Service',
            'DHCP Service',
            'VPN Service',
            'Firewall Management',
            'Storage Management',
            'Virtualization Platform',
            'Research Computing',
            'VLE (Moodle)',
            'Student Portal',
            'Staff Portal',
            'Printing Services',
            'Telephony Service',
            'Wireless Network',
            'CCTV & Security Systems',
        ];

        foreach ($serviceNames as $serviceName) {
            // Use admin2x as manager for some services so they always have test data
            if (in_array($serviceName, ['Active Directory Service', 'Network Infrastructure Service'])) {
                $serviceManager = $admin;
            } else {
                // Pick a random service manager from existing users
                $serviceManager = collect($allUsers)->random();
            }

            $service = Service::factory()->create([
                'name' => $serviceName,
                'manager_id' => $serviceManager->id,
            ]);

            // Attach 1-3 random users from existing team members to each service
            $serviceMembers = collect($allUsers)->random(rand(1, min(3, count($allUsers))));
            foreach ($serviceMembers as $member) {
                $service->users()->attach($member->id);
            }
        }

        // Generate realistic plan entries for all users
        $this->generatePlanEntries($allUsers);

        // Mark some users as unavailable for random days
        $this->markSomeUsersUnavailable($allUsers);

        // Create demo service with varied coverage scenarios
        $this->createDemoServiceWithVariedCoverage();

        // Ensure at least 2 days have zero coverage at Boyd-Orr for demo purposes
        $this->ensureLocationCoverageGaps();
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

    private function createDemoServiceWithVariedCoverage(): void
    {
        $serviceManager = User::factory()->create([
            'username' => 'demo.service.manager',
            'email' => 'demo.service.manager@example.com',
            'password' => Hash::make('secret'),
            'surname' => 'Manager',
            'forenames' => 'Demo Service',
        ]);

        $serviceMember = User::factory()->create([
            'username' => 'demo.service.member',
            'email' => 'demo.service.member@example.com',
            'password' => Hash::make('secret'),
            'surname' => 'Member',
            'forenames' => 'Demo Service',
        ]);

        $demoService = Service::factory()->create([
            'name' => 'DEMO: Coverage Scenarios',
            'manager_id' => $serviceManager->id,
        ]);

        $demoService->users()->attach($serviceMember->id);

        $startDate = now()->startOfWeek();
        $weekdayIndex = 0;

        for ($offset = 0; $offset < 14 && $weekdayIndex < 10; $offset++) {
            $day = $startDate->copy()->addDays($offset);

            if ($day->isWeekend()) {
                continue;
            }

            if ($weekdayIndex < 4) {
                PlanEntry::create([
                    'user_id' => $serviceMember->id,
                    'entry_date' => $day,
                    'location' => Location::JWS,
                    'note' => 'Normal coverage - member available',
                    'category' => null,
                    'is_available' => true,
                    'is_holiday' => false,
                    'created_by_manager' => false,
                ]);

                PlanEntry::create([
                    'user_id' => $serviceManager->id,
                    'entry_date' => $day,
                    'location' => Location::OTHER,
                    'note' => 'Also available (not needed)',
                    'category' => null,
                    'is_available' => true,
                    'is_holiday' => false,
                    'created_by_manager' => false,
                ]);
            } elseif ($weekdayIndex < 7) {
                PlanEntry::create([
                    'user_id' => $serviceMember->id,
                    'entry_date' => $day,
                    'location' => null,
                    'note' => 'On leave',
                    'category' => null,
                    'is_available' => false,
                    'is_holiday' => false,
                    'created_by_manager' => false,
                ]);

                PlanEntry::create([
                    'user_id' => $serviceManager->id,
                    'entry_date' => $day,
                    'location' => Location::JWN,
                    'note' => 'Manager-only coverage',
                    'category' => null,
                    'is_available' => true,
                    'is_holiday' => false,
                    'created_by_manager' => false,
                ]);
            } else {
                PlanEntry::create([
                    'user_id' => $serviceMember->id,
                    'entry_date' => $day,
                    'location' => null,
                    'note' => 'Unavailable',
                    'category' => null,
                    'is_available' => false,
                    'is_holiday' => false,
                    'created_by_manager' => false,
                ]);

                PlanEntry::create([
                    'user_id' => $serviceManager->id,
                    'entry_date' => $day,
                    'location' => null,
                    'note' => 'Also unavailable',
                    'category' => null,
                    'is_available' => false,
                    'is_holiday' => false,
                    'created_by_manager' => false,
                ]);
            }

            $weekdayIndex++;
        }
    }

    private function ensureLocationCoverageGaps(): void
    {
        $startDate = now()->startOfWeek();
        $targetLocation = Location::BO;
        $gapDayIndices = [2, 7]; // Wednesday of week 1, Wednesday of week 2

        $alternativeLocations = [
            Location::OTHER,
            Location::JWS,
            Location::JWN,
            Location::RANKINE,
        ];

        foreach ($gapDayIndices as $weekdayIndex) {
            $offset = 0;
            $currentWeekdayIndex = 0;

            while ($currentWeekdayIndex < $weekdayIndex) {
                $day = $startDate->copy()->addDays($offset);
                $offset++;

                if ($day->isWeekend()) {
                    continue;
                }

                $currentWeekdayIndex++;
            }

            $targetDay = $startDate->copy()->addDays($offset);

            $entries = PlanEntry::where('entry_date', $targetDay)
                ->where('location', $targetLocation)
                ->where('is_available', true)
                ->get();

            foreach ($entries as $entry) {
                $entry->update([
                    'location' => $alternativeLocations[array_rand($alternativeLocations)],
                ]);
            }
        }
    }
}
