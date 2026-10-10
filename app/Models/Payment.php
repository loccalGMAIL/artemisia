<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

#[Fillable([
    'contract_period_id', 'paid_on', 'amount', 'method', 'reference', 'created_by',
    'voided_at', 'voided_by', 'void_justification',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * A payment is never erased for good: a mistake is corrected by voiding it (RF-80).
     */
    protected static function booted(): void
    {
        static::forceDeleting(fn () => throw new LogicException('Payments cannot be deleted for good.'));
    }

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'voided_at' => 'datetime',
        ];
    }

    /**
     * The payments that count towards what has been paid: the ones not voided (RF-68).
     *
     * @param  Builder<Payment>  $query
     */
    #[Scope]
    protected function valid(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    /**
     * @param  Builder<Payment>  $query
     */
    #[Scope]
    protected function voided(Builder $query): void
    {
        $query->whereNotNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * @return BelongsTo<ContractPeriod, $this>
     */
    public function contractPeriod(): BelongsTo
    {
        return $this->belongsTo(ContractPeriod::class, 'contract_period_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
