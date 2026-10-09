<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetItem>
 */
class BudgetItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'service_id' => Service::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'unit_price' => fake()->randomFloat(2, 10, 5000),
            'quantity' => fake()->numberBetween(1, 10),
        ];
    }
}
