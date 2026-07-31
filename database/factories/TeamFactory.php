<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'personal_team' => false,
        ];
    }

    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'personal_team' => true,
        ]);
    }

    /**
     * Attach the owner to the team_user pivot after creation.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Team $team): void {
            if (! $team->members()->whereKey($team->owner_id)->exists()) {
                $team->members()->attach($team->owner_id, ['role' => 'owner']);
            }
        });
    }
}
