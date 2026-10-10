<?php

namespace App\Models;

use App\Enums\ContractHistoryField;
use Database\Factories\ContractHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['contract_id', 'field', 'old_value', 'new_value', 'author_id'])]
class ContractHistory extends Model
{
    /** @use HasFactory<ContractHistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * The history is append-only: an entry is never edited or deleted (RF-34, RNF-6).
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Contract history entries cannot be modified.'));
        static::deleting(fn () => throw new LogicException('Contract history entries cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'field' => ContractHistoryField::class,
            'old_value' => 'array',
            'new_value' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class)->withTrashed();
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
