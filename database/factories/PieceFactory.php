<?php

namespace Database\Factories;

use App\Enums\PieceStatus;
use App\Models\Budget;
use App\Models\Piece;
use App\Models\User;
use App\Models\WorkCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Piece>
 */
class PieceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory()->accepted(),
            'budget_item_id' => null,
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'work_category_id' => WorkCategory::factory(),
            'status' => PieceStatus::Pending,
            'created_by' => User::factory(),
        ];
    }

    public function loose(): static
    {
        return $this->state(fn (): array => ['budget_item_id' => null]);
    }

    public function status(PieceStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function assignedTo(User $assignee): static
    {
        return $this->state(fn (): array => ['assignee_id' => $assignee->id]);
    }

    public function dueOn(string $date): static
    {
        return $this->state(fn (): array => ['due_date' => $date]);
    }

    public function discarded(): static
    {
        return $this->state(fn (): array => ['deleted_at' => now()]);
    }
}
