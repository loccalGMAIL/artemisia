<?php

namespace Database\Factories;

use App\Enums\AccessMethod;
use App\Enums\AccessOutcome;
use App\Enums\AccessPortal;
use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessLog>
 */
class AccessLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email_used' => fake()->safeEmail(),
            'portal' => fake()->randomElement(AccessPortal::cases()),
            'method' => fake()->randomElement(AccessMethod::cases()),
            'outcome' => AccessOutcome::Success,
            'rejection_reason' => null,
        ];
    }
}
