<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
            'short_label' => fake()->unique()->lexify('???'),
            'slug' => fake()->unique()->slug(2),
            'is_physical' => true,
        ];
    }

    public function nonPhysical(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_physical' => false,
        ]);
    }
}
