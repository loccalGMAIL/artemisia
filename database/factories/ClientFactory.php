<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
use App\Models\User;
use App\Support\Cuit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_type' => ClientType::Individual,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company_name' => null,
            'document' => (string) fake()->unique()->numberBetween(10_000_000, 99_999_999),
            'status' => ClientStatus::Active,
            'created_by' => User::factory(),
        ];
    }

    public function company(): static
    {
        return $this->state(fn (): array => [
            'person_type' => ClientType::Company,
            'first_name' => null,
            'last_name' => null,
            'company_name' => fake()->company(),
            'document' => $this->validCuit(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => ClientStatus::Inactive]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => ClientStatus::Inactive,
            'deleted_at' => now(),
        ]);
    }

    private function validCuit(): string
    {
        do {
            $firstTen = '30'.str_pad((string) fake()->unique()->numberBetween(0, 99_999_999), 8, '0', STR_PAD_LEFT);
            $digit = Cuit::checkDigit($firstTen);
        } while ($digit === null);

        return $firstTen.$digit;
    }
}
