<?php

namespace App\Models;

use App\Enums\PieceHistoryField;
use Database\Factories\PieceHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['piece_id', 'field', 'old_value', 'new_value', 'author_id'])]
class PieceHistory extends Model
{
    /** @use HasFactory<PieceHistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * The history is append-only: an entry is never edited or deleted (RF-40, RNF-3).
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Piece history entries cannot be modified.'));
        static::deleting(fn () => throw new LogicException('Piece history entries cannot be deleted.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field' => PieceHistoryField::class,
            'old_value' => 'array',
            'new_value' => 'array',
        ];
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
