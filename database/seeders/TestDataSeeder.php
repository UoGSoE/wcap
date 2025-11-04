<?php

namespace Database\Seeders;

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
    }
}
