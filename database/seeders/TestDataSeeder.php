<?php

namespace Database\Seeders;

use App\Enums\AvailabilityStatus;
use App\Models\Location;
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
        // Seed locations first (migrating from enum values)
        $this->seedLocations();

        $admin = User::factory()->admin()->create([
            'username' => 'admin2x',
            'email' => 'admin2x@example.com',
            'password' => Hash::make('secret'),
        ]);

        // Create API token for admin user (for Power BI demo)
        $token = $admin->createToken('Power BI - Executive Dashboard', [
            'view:own-plan',
            'view:team-plans',
            'view:all-plans',
        ]);

        // Output token for easy reference (only visible during seeding)
        echo "\n===========================================\n";
        echo "API Token for admin2x:\n";
        echo $token->plainTextToken."\n";
        echo "===========================================\n\n";

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

        // Get physical locations for assigning default locations to users
        $physicalLocations = Location::where('is_physical', true)->get();

        $managers = [];
        $allUsers = [];
        $userIndex = 0;

        // Assign admin a default location
        $admin->update(['default_location_id' => $physicalLocations[$userIndex % count($physicalLocations)]->id]);
        $allUsers[] = $admin;
        $userIndex++;

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
                    'default_location_id' => $physicalLocations[$userIndex % count($physicalLocations)]->id,
                ]);
                $allUsers[] = $manager;
                $userIndex++;
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
                    'default_location_id' => $physicalLocations[$userIndex % count($physicalLocations)]->id,
                ]);
                $user->teams()->attach($team);

                // Track all users for plan entry generation
                $allUsers[] = $user;
                $userIndex++;
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

        // Ensure at least one day has zero coverage at "Other" to demo non-physical location handling
        $this->ensureOtherLocationGap();
    }

    private function generatePlanEntries(array $teamMembers): void
    {
        $startDate = now()->startOfWeek();
        $physicalLocations = Location::where('is_physical', true)->get();
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
        foreach ($teamMembers as $member) {
            // Use the member's default location as their primary (home) location
            $primaryLocation = $member->defaultLocation;

            // Pick a random other physical location as secondary (for visiting other offices)
            $otherLocations = $physicalLocations->where('id', '!=', $primaryLocation?->id);
            $secondaryLocation = $otherLocations->isNotEmpty() ? $otherLocations->random() : $primaryLocation;

            for ($dayOffset = 0; $dayOffset < 14; $dayOffset++) {
                $date = $startDate->copy()->addDays($dayOffset);

                // Skip weekends
                if ($date->isWeekend()) {
                    continue;
                }

                // 80% at home location, 20% visiting another office
                $location = (rand(1, 10) <= 8) ? $primaryLocation : $secondaryLocation;

                // Pick a random note
                $note = $notes[array_rand($notes)];

                PlanEntry::create([
                    'user_id' => $member->id,
                    'entry_date' => $date,
                    'location_id' => $location->id,
                    'note' => $note,
                    'category' => null,
                    'availability_status' => AvailabilityStatus::ONSITE,
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
                    'availability_status' => AvailabilityStatus::NOT_AVAILABLE,
                    'location_id' => null,
                    'note' => $unavailableReasons[array_rand($unavailableReasons)],
                ]);
            }
        }
    }

    private function createDemoServiceWithVariedCoverage(): void
    {
        $jwsLocation = Location::where('slug', 'jws')->first();
        $jwnLocation = Location::where('slug', 'jwn')->first();

        $serviceManager = User::factory()->create([
            'username' => 'demo.service.manager',
            'email' => 'demo.service.manager@example.com',
            'password' => Hash::make('secret'),
            'surname' => 'Manager',
            'forenames' => 'Demo Service',
            'default_location_id' => $jwnLocation->id,
        ]);

        $serviceMember = User::factory()->create([
            'username' => 'demo.service.member',
            'email' => 'demo.service.member@example.com',
            'password' => Hash::make('secret'),
            'surname' => 'Member',
            'forenames' => 'Demo Service',
            'default_location_id' => $jwsLocation->id,
        ]);

        $demoService = Service::factory()->create([
            'name' => 'DEMO: Coverage Scenarios',
            'manager_id' => $serviceManager->id,
        ]);

        $demoService->users()->attach($serviceMember->id);

        $startDate = now()->startOfWeek();
        $weekdayIndex = 0;

        $otherLocation = Location::where('slug', 'other')->first();

        for ($offset = 0; $offset < 14 && $weekdayIndex < 10; $offset++) {
            $day = $startDate->copy()->addDays($offset);

            if ($day->isWeekend()) {
                continue;
            }

            if ($weekdayIndex < 4) {
                PlanEntry::create([
                    'user_id' => $serviceMember->id,
                    'entry_date' => $day,
                    'location_id' => $jwsLocation->id,
                    'note' => 'Normal coverage - member available',
                    'category' => null,
                    'availability_status' => AvailabilityStatus::ONSITE,
                    'is_holiday' => false,
                    'created_by_manager' => false,
                ]);

                PlanEntry::create([
                    'user_id' => $serviceManager->id,
                    'entry_date' => $day,
                    'location_id' => $otherLocation->id,
                    'note' => 'Also available (not needed)',
                    'category' => null,
                    'availability_status' => AvailabilityStatus::ONSITE,
                    'is_holiday' => false,
                    'created_by_manager' => false,
                ]);
            } elseif ($weekdayIndex < 7) {
                PlanEntry::create([
                    'user_id' => $serviceMember->id,
                    'entry_date' => $day,
                    'location_id' => null,
                    'note' => 'On leave',
                    'category' => null,
                    'availability_status' => AvailabilityStatus::NOT_AVAILABLE,
                    'is_holiday' => false,
                    'created_by_manager' => false,
                ]);

                PlanEntry::create([
                    'user_id' => $serviceManager->id,
                    'entry_date' => $day,
                    'location_id' => $jwnLocation->id,
                    'note' => 'Manager-only coverage',
                    'category' => null,
                    'availability_status' => AvailabilityStatus::ONSITE,
                    'is_holiday' => false,
                    'created_by_manager' => false,
                ]);
            } else {
                PlanEntry::create([
                    'user_id' => $serviceMember->id,
                    'entry_date' => $day,
                    'location_id' => null,
                    'note' => 'Unavailable',
                    'category' => null,
                    'availability_status' => AvailabilityStatus::NOT_AVAILABLE,
                    'is_holiday' => false,
                    'created_by_manager' => false,
                ]);

                PlanEntry::create([
                    'user_id' => $serviceManager->id,
                    'entry_date' => $day,
                    'location_id' => null,
                    'note' => 'Also unavailable',
                    'category' => null,
                    'availability_status' => AvailabilityStatus::NOT_AVAILABLE,
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
        $targetLocation = Location::where('slug', 'boyd-orr')->first();
        $gapDayIndices = [2, 7]; // Wednesday of week 1, Wednesday of week 2

        $alternativeLocations = Location::whereIn('slug', ['other', 'jws', 'jwn', 'rankine'])->get();

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
                ->where('location_id', $targetLocation->id)
                ->whereIn('availability_status', [AvailabilityStatus::ONSITE, AvailabilityStatus::REMOTE])
                ->get();

            foreach ($entries as $entry) {
                $entry->update([
                    'location_id' => $alternativeLocations->random()->id,
                ]);
            }
        }
    }

    private function ensureOtherLocationGap(): void
    {
        $startDate = now()->startOfWeek();
        $otherLocation = Location::where('slug', 'other')->first();
        $physicalLocations = Location::where('is_physical', true)->get();

        // Target Thursday of week 1 (weekday index 3)
        $targetWeekdayIndex = 3;
        $offset = 0;
        $currentWeekdayIndex = 0;

        while ($currentWeekdayIndex < $targetWeekdayIndex) {
            $day = $startDate->copy()->addDays($offset);
            $offset++;

            if ($day->isWeekend()) {
                continue;
            }

            $currentWeekdayIndex++;
        }

        $targetDay = $startDate->copy()->addDays($offset);

        // Move anyone at "Other" on this day to a physical location
        $entries = PlanEntry::where('entry_date', $targetDay)
            ->where('location_id', $otherLocation->id)
            ->whereIn('availability_status', [AvailabilityStatus::ONSITE, AvailabilityStatus::REMOTE])
            ->get();

        foreach ($entries as $entry) {
            $entry->update([
                'location_id' => $physicalLocations->random()->id,
            ]);
        }
    }

    private function seedLocations(): void
    {
        $locations = [
            ['name' => 'JWS', 'short_label' => 'JWS', 'slug' => 'jws', 'is_physical' => true],
            ['name' => 'JWN', 'short_label' => 'JWN', 'slug' => 'jwn', 'is_physical' => true],
            ['name' => 'Rankine', 'short_label' => 'Rank', 'slug' => 'rankine', 'is_physical' => true],
            ['name' => 'Boyd-Orr', 'short_label' => 'BO', 'slug' => 'boyd-orr', 'is_physical' => true],
            ['name' => 'Other', 'short_label' => 'Other', 'slug' => 'other', 'is_physical' => false],
            ['name' => 'Remote', 'short_label' => 'Remote', 'slug' => 'remote', 'is_physical' => false],
            ['name' => 'Joseph Black', 'short_label' => 'JB', 'slug' => 'joseph-black', 'is_physical' => true],
            ['name' => 'Alwyn William', 'short_label' => 'AW', 'slug' => 'alwyn-william', 'is_physical' => true],
            ['name' => 'Gilbert Scott', 'short_label' => 'GS', 'slug' => 'gilbert-scott', 'is_physical' => true],
            ['name' => 'Kelvin', 'short_label' => 'Kelv', 'slug' => 'kelvin', 'is_physical' => true],
            ['name' => 'Maths', 'short_label' => 'Maths', 'slug' => 'maths', 'is_physical' => true],
        ];

        foreach ($locations as $location) {
            Location::create($location);
        }
    }
}
