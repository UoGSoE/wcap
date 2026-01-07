<?php

namespace Database\Factories;

use App\Enums\AvailabilityStatus;
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
            'availability_status' => AvailabilityStatus::ONSITE,
            'is_holiday' => false,
            'created_by_manager' => false,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'availability_status' => AvailabilityStatus::NOT_AVAILABLE,
            'location_id' => null,
        ]);
    }

    public function remote(): static
    {
        return $this->state(fn (array $attributes) => [
            'availability_status' => AvailabilityStatus::REMOTE,
        ]);
    }

    public function onsite(): static
    {
        return $this->state(fn (array $attributes) => [
            'availability_status' => AvailabilityStatus::ONSITE,
        ]);
    }
}
