<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientContact>
 */
class ClientContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->name(),
            'role' => fake()->jobTitle(),
            'phone' => fake()->numerify('11-####-####'),
            'email' => fake()->unique()->safeEmail(),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (): array => ['is_primary' => true]);
    }
}
