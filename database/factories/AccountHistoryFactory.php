<?php

namespace Database\Factories;

use App\Enums\AccountHistoryField;
use App\Models\AccountHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountHistory>
 */
class AccountHistoryFactory extends Factory
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
            'field' => fake()->randomElement(AccountHistoryField::cases()),
            'old_value' => null,
            'new_value' => ['is_active' => true],
            'author_id' => User::factory(),
        ];
    }
}
