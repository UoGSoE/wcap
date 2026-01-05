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
            'location_id' => \App\Models\Location::factory(),
            'is_available' => true,
            'is_holiday' => false,
            'created_by_manager' => false,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
            'location_id' => null,
        ]);
    }
}
