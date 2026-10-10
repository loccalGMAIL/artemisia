<?php

namespace Database\Factories;

use App\Enums\PieceApprovalResolution;
use App\Models\Piece;
use App\Models\PieceApprovalSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PieceApprovalSubmission>
 */
class PieceApprovalSubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'piece_id' => Piece::factory(),
            'file_path' => 'piece-submissions/'.fake()->uuid().'.pdf',
            'file_extension' => 'pdf',
            'submitted_by' => User::factory(),
            'submitted_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'resolution' => PieceApprovalResolution::Approved,
            'resolved_by' => User::factory(),
            'resolved_at' => now(),
        ]);
    }

    public function rejected(?string $reason = null): static
    {
        return $this->state(fn (): array => [
            'resolution' => PieceApprovalResolution::Rejected,
            'resolved_by' => User::factory(),
            'resolved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }
}
