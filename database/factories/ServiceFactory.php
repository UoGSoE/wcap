<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Active Directory Service',
                'Email Service',
                'Backup Service',
                'Network Infrastructure Service',
                'Database Service',
                'Web Hosting Service',
            ]),
            'manager_id' => User::factory(),
        ];
    }
}
