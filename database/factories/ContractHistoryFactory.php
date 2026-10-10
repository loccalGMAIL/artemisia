<?php

namespace Database\Factories;

use App\Enums\ContractHistoryField;
use App\Models\Contract;
use App\Models\ContractHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractHistory>
 */
class ContractHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'field' => ContractHistoryField::Created,
            'old_value' => null,
            'new_value' => ['status' => 'active'],
            'author_id' => User::factory(),
        ];
    }

    /** An entry made by the system, with no author. */
    public function system(): static
    {
        return $this->state(fn (): array => ['author_id' => null]);
    }
}
