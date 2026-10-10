<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\ContractPeriod;
use App\Models\Payment;
use App\Models\User;
use App\Support\BusinessDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_period_id' => ContractPeriod::factory(),
            'paid_on' => BusinessDate::today()->toDateString(),
            'amount' => '100.00',
            'method' => PaymentMethod::Cash,
            'reference' => null,
            'created_by' => User::factory(),
        ];
    }

    public function voided(string $justification = 'Cargado por error'): static
    {
        return $this->state(fn (): array => [
            'voided_at' => now(),
            'voided_by' => User::factory(),
            'void_justification' => $justification,
        ]);
    }
}
