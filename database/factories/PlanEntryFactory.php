<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlanEntry>
 */
class PlanEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'entry_date' => fake()->dateTimeBetween('now', '+2 weeks'),
            'note' => fake()->optional(0.8)->randomElement([
                'Support tickets',
                'Project planning',
                'Code review',
                'Team meeting',
                'Documentation',
                'Bug fixes',
                'Feature development',
            ]),
            'category' => null,
            'location' => fake()->randomElement(\App\Enums\Location::cases()),
            'is_available' => true,
            'is_holiday' => false,
            'created_by_manager' => false,
        ];
    }
}
