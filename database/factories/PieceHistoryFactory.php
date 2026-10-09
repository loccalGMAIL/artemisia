<?php

namespace Database\Factories;

use App\Enums\PieceHistoryField;
use App\Models\Piece;
use App\Models\PieceHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PieceHistory>
 */
class PieceHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'piece_id' => Piece::factory(),
            'field' => PieceHistoryField::Created,
            'old_value' => null,
            'new_value' => ['name' => fake()->words(3, true)],
            'author_id' => User::factory(),
        ];
    }
}
