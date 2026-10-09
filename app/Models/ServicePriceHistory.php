<?php

namespace App\Models;

use Database\Factories\ServicePriceHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['service_id', 'old_price', 'new_price', 'author_id'])]
class ServicePriceHistory extends Model
{
    /** @use HasFactory<ServicePriceHistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * The price history is append-only: an entry is never edited or deleted (RNF-8).
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Price history entries cannot be modified.'));
        static::deleting(fn () => throw new LogicException('Price history entries cannot be deleted.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2',
            'new_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
