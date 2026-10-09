<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServicePriceHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePriceHistory>
 */
class ServicePriceHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'old_price' => null,
            'new_price' => fake()->randomFloat(2, 10, 5000),
            'author_id' => User::factory(),
        ];
    }
}
