<?php

namespace Database\Factories;

use App\Enums\BudgetHistoryField;
use App\Models\Budget;
use App\Models\BudgetHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetHistory>
 */
class BudgetHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'field' => BudgetHistoryField::HeaderChanged,
            'old_value' => null,
            'new_value' => ['title' => fake()->sentence(3)],
            'author_id' => User::factory(),
        ];
    }
}
