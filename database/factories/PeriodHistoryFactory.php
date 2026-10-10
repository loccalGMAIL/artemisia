<?php

namespace Database\Factories;

use App\Enums\PeriodHistoryField;
use App\Models\ContractPeriod;
use App\Models\PeriodHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PeriodHistory>
 */
class PeriodHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_period_id' => ContractPeriod::factory(),
            'field' => PeriodHistoryField::Generated,
            'old_value' => null,
            'new_value' => ['fee' => '1000.00'],
            'author_id' => User::factory(),
        ];
    }

    /** An entry made by the system, with no author. */
    public function system(): static
    {
        return $this->state(fn (): array => ['author_id' => null]);
    }
}
