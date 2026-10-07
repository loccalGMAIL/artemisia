<?php

namespace Database\Factories;

use App\Enums\ClientHistoryField;
use App\Models\Client;
use App\Models\ClientHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientHistory>
 */
class ClientHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'field' => ClientHistoryField::Identification,
            'old_value' => null,
            'new_value' => ['created' => true],
            'author_id' => User::factory(),
        ];
    }
}
