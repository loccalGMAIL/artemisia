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
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email_used' => fake()->safeEmail(),
            'portal' => AccessPortal::Staff,
            'method' => AccessMethod::Password,
            'outcome' => AccessOutcome::Success,
            'rejection_reason' => null,
        ];
    }
}
