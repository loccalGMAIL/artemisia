<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractPeriod;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractPeriod>
 */
class ContractPeriodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'client_id' => fn (array $attributes): int => Contract::withTrashed()->findOrFail($attributes['contract_id'])->client_id,
            'month' => BusinessDate::currentMonth()->toDateString(),
            'due_date' => fn (array $attributes): string => CarbonImmutable::parse($attributes['month'])->addDays(9)->toDateString(),
            'fee' => '1000.00',
            'total' => '1000.00',
            'paid_amount' => '0.00',
            'invoice_number' => null,
            'invoice_date' => null,
        ];
    }

    /** A period whose due date has already passed. */
    public function overdue(): static
    {
        return $this->state(fn (): array => ['due_date' => BusinessDate::today()->subDay()->toDateString()]);
    }

    public function invoiced(string $number = '0001-00000123', ?string $date = null): static
    {
        return $this->state(fn (): array => [
            'invoice_number' => $number,
            'invoice_date' => $date ?? BusinessDate::today()->toDateString(),
        ]);
    }
}
