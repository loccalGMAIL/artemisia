<?php

namespace App\Models;

use App\Enums\BudgetHistoryField;
use Database\Factories\BudgetHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['budget_id', 'field', 'old_value', 'new_value', 'author_id'])]
class BudgetHistory extends Model
{
    /** @use HasFactory<BudgetHistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * The history is append-only: an entry is never edited or deleted (RF-53, RNF-8).
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Budget history entries cannot be modified.'));
        static::deleting(fn () => throw new LogicException('Budget history entries cannot be deleted.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field' => BudgetHistoryField::class,
            'old_value' => 'array',
            'new_value' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
