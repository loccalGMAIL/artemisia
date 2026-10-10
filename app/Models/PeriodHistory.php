<?php

namespace App\Models;

use App\Enums\PeriodHistoryField;
use Database\Factories\PeriodHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['contract_period_id', 'field', 'old_value', 'new_value', 'author_id'])]
class PeriodHistory extends Model
{
    /** @use HasFactory<PeriodHistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * The history is append-only: an entry is never edited or deleted (RF-80, RNF-6).
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Period history entries cannot be modified.'));
        static::deleting(fn () => throw new LogicException('Period history entries cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'field' => PeriodHistoryField::class,
            'old_value' => 'array',
            'new_value' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ContractPeriod, $this>
     */
    public function contractPeriod(): BelongsTo
    {
        return $this->belongsTo(ContractPeriod::class, 'contract_period_id');
    }

    /**
     * Null when the system made the change (plan D-7).
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
