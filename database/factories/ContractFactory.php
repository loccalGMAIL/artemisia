<?php

namespace Database\Factories;

use App\Enums\ContractStatus;
use App\Models\Budget;
use App\Models\Contract;
use App\Models\User;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory()->accepted()->monthly(),
            'client_id' => fn (array $attributes): int => Budget::withTrashed()->findOrFail($attributes['budget_id'])->client_id,
            'start_date' => BusinessDate::today()->toDateString(),
            'term_months' => null,
            'term_end_month' => null,
            'due_day' => 10,
            'monthly_amount' => '1000.00',
            'status' => ContractStatus::Active,
            'created_by' => User::factory(),
        ];
    }

    /** A fixed term of the given number of months, counted from the month of the start date. */
    public function fixedTerm(int $months): static
    {
        return $this->state(fn (array $attributes): array => [
            'term_months' => $months,
            'term_end_month' => CarbonImmutable::parse($attributes['start_date'])->startOfMonth()->addMonths($months - 1)->toDateString(),
        ]);
    }

    public function indefinite(): static
    {
        return $this->state(fn (): array => ['term_months' => null, 'term_end_month' => null]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => ContractStatus::Cancelled]);
    }

    public function finished(): static
    {
        return $this->state(fn (): array => ['status' => ContractStatus::Finished]);
    }

    public function startingOn(string $date): static
    {
        return $this->state(fn (): array => ['start_date' => $date]);
    }
}
