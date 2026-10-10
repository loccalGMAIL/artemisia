<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Support\BusinessDate;
use App\Support\Money;
use Database\Factories\ContractPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'contract_id', 'client_id', 'month', 'due_date', 'fee', 'total', 'paid_amount',
    'invoice_number', 'invoice_date',
])]
class ContractPeriod extends Model
{
    /** @use HasFactory<ContractPeriodFactory> */
    use HasFactory;

    /**
     * A period is never deleted: its concepts, payments and history hang from it.
     */
    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Contract periods cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'due_date' => 'date',
            'invoice_date' => 'date',
            'fee' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    /**
     * What is still owed: the total minus what has been paid (RF-69).
     *
     * @return Attribute<string, never>
     */
    protected function balance(): Attribute
    {
        return Attribute::get(fn (): string => Money::fromCents(Money::toCents($this->total) - Money::toCents($this->paid_amount)));
    }

    /**
     * The collection status, worked out from the total and what has been paid, never stored
     * (RF-70 to RF-73). A period with no balance is paid, even with a total of zero and no
     * payments.
     *
     * @return Attribute<PaymentStatus, never>
     */
    protected function paymentStatus(): Attribute
    {
        return Attribute::get(fn (): PaymentStatus => $this->resolvePaymentStatus())->withoutObjectCaching();
    }

    private function resolvePaymentStatus(): PaymentStatus
    {
        if (Money::toCents($this->balance) <= 0) {
            return PaymentStatus::Paid;
        }

        return Money::toCents($this->paid_amount) > 0 ? PaymentStatus::Partial : PaymentStatus::Pending;
    }

    /**
     * Past its due date with a balance left. It is a signal next to the payment status, not a
     * status of its own (RF-74).
     */
    public function isOverdue(): bool
    {
        return $this->due_date->toDateString() < BusinessDate::today()->toDateString()
            && Money::toCents($this->balance) > 0;
    }

    /**
     * @param  Builder<ContractPeriod>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereColumn('total', '>', 'paid_amount')->where('paid_amount', '<=', 0);
    }

    /**
     * @param  Builder<ContractPeriod>  $query
     */
    #[Scope]
    protected function partial(Builder $query): void
    {
        $query->whereColumn('total', '>', 'paid_amount')->where('paid_amount', '>', 0);
    }

    /**
     * @param  Builder<ContractPeriod>  $query
     */
    #[Scope]
    protected function paid(Builder $query): void
    {
        $query->whereColumn('paid_amount', '>=', 'total');
    }

    /**
     * @param  Builder<ContractPeriod>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->whereColumn('total', '>', 'paid_amount')->where('due_date', '<', BusinessDate::today()->toDateString());
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return HasMany<PeriodConcept, $this>
     */
    public function concepts(): HasMany
    {
        return $this->hasMany(PeriodConcept::class)->orderBy('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_on')->orderBy('id');
    }

    /**
     * Chronological, as the staff reads it (RF-84).
     *
     * @return HasMany<PeriodHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(PeriodHistory::class)->orderBy('created_at')->orderBy('id');
    }
}
