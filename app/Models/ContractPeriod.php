<?php

namespace App\Models;

use Database\Factories\ContractPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
