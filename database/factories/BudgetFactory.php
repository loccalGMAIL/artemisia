<?php

namespace Database\Factories;

use App\Enums\BudgetModality;
use App\Enums\BudgetStatus;
use App\Models\Budget;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'title' => fake()->sentence(3),
            'modality' => BudgetModality::Single,
            'issue_date' => today(),
            'validity_date' => today()->addDays(30),
            'status' => BudgetStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn (): array => ['modality' => BudgetModality::Monthly]);
    }

    public function sent(): static
    {
        return $this->state(fn (): array => ['status' => BudgetStatus::Sent]);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => ['status' => BudgetStatus::Accepted, 'response_date' => today()]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['status' => BudgetStatus::Rejected, 'response_date' => today()]);
    }

    public function discarded(): static
    {
        return $this->state(fn (): array => ['deleted_at' => now()]);
    }
}
