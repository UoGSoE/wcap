<?php

namespace Database\Seeders;

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
        foreach ($teamNames as $teamName) {
            $manager = User::factory()->create([
                'username' => strtolower($teamName).'2x',
                'email' => 'manager.'.strtolower($teamName).'2x@example.com',
                'password' => Hash::make('secret'),
            ]);
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
            }
        }
    }
}
