<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'staff_number' => 'NP-'.fake()->unique()->numberBetween(100, 999),
            'role' => Role::Support,
            'designation' => 'Applications Support Analyst',
            'team' => 'Applications Support',
            'is_active' => true,
            'password' => static::$password ??= Hash::make('Password-2026'),
            'remember_token' => Str::random(10),
        ];
    }

    public function administrator(): static
    {
        return $this->state(fn () => ['role' => Role::Administrator, 'designation' => 'Platform Administrator']);
    }

    public function supervisor(): static
    {
        return $this->state(fn () => ['role' => Role::Supervisor, 'designation' => 'Support Supervisor']);
    }

    public function support(): static
    {
        return $this->state(fn () => ['role' => Role::Support]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false, 'deactivated_at' => now()]);
    }
}
