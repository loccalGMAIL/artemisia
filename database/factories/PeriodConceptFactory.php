<?php

namespace Database\Factories;

use App\Enums\ConceptType;
use App\Models\ContractPeriod;
use App\Models\PeriodConcept;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PeriodConcept>
 */
class PeriodConceptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_period_id' => ContractPeriod::factory(),
            'type' => ConceptType::Extra,
            'description' => fake()->sentence(3),
            'amount' => '250.00',
            'created_by' => User::factory(),
        ];
    }

    public function discount(): static
    {
        return $this->state(fn (): array => [
            'type' => ConceptType::Discount,
            'description' => 'Bonificación acordada con el cliente',
        ]);
    }
}
