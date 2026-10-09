<?php

namespace App\Models;

use App\Enums\PieceApprovalResolution;
use Database\Factories\PieceApprovalSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'piece_id', 'file_path', 'file_extension', 'submitted_by', 'submitted_at', 'resolution',
    'resolved_by', 'resolved_at', 'rejection_reason',
])]
class PieceApprovalSubmission extends Model
{
    /** @use HasFactory<PieceApprovalSubmissionFactory> */
    use HasFactory;

    /**
     * A submission is resolved once with an update, but it is never deleted (RF-30, RNF-3).
     */
    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Piece approval submissions cannot be deleted.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolution' => PieceApprovalResolution::class,
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->resolution === null;
    }

    /**
     * @return BelongsTo<Piece, $this>
     */
    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
