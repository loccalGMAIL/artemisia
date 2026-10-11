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
     * A registered payment is never changed, except to void it once, and is never erased: a
     * mistake is corrected by voiding the payment and registering a new one (RF-78, RF-80).
     */
    protected static function booted(): void
    {
        static::updating(function (Payment $payment): void {
            if (! $payment->isBeingVoided()) {
                throw new LogicException('A registered payment cannot be modified, only voided.');
            }
        });

        static::deleting(fn () => throw new LogicException('Payments cannot be deleted.'));
    }

    private function isBeingVoided(): bool
    {
        $changed = array_keys($this->getDirty());

        return $this->getOriginal('voided_at') === null
            && $this->voided_at !== null
            && array_diff($changed, ['voided_at', 'voided_by', 'void_justification', 'updated_at']) === [];
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

    /**
     * The payment as the history keeps it (RF-79).
     *
     * @return array{id: int, amount: string, paid_on: string, method: string, reference: string|null}
     */
    public function snapshot(): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'paid_on' => $this->paid_on->toDateString(),
            'method' => $this->method->value,
            'reference' => $this->reference,
        ];
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
