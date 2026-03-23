<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
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
                'IT Support Team',
                'Development Team',
                'Infrastructure Team',
                'Security Team',
            ]),
            'manager_id' => User::factory(),
            'parent_team_id' => null,
        ];
    }

    public function childOf(Team $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_team_id' => $parent->id,
        ]);
    }
}
