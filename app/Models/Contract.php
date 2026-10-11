<?php

namespace App\Models;

use App\Enums\ContractStatus;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

#[Fillable([
    'budget_id', 'client_id', 'start_date', 'term_months', 'term_end_month', 'due_day',
    'monthly_amount', 'status', 'created_by',
])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory, SoftDeletes;

    /**
     * A contract is never erased for good (RF-34).
     */
    protected static function booted(): void
    {
        static::forceDeleting(fn () => throw new LogicException('Contracts cannot be deleted for good.'));
    }

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'start_date' => 'date',
            'term_end_month' => 'date',
            'term_months' => 'integer',
            'due_day' => 'integer',
            'monthly_amount' => 'decimal:2',
        ];
    }

    /**
     * The contract as it is created, for its first history entry (RF-33).
     *
     * @return array<string, int|string|null>
     */
    public function creationSnapshot(): array
    {
        return [
            'status' => ContractStatus::Active->value,
            'monthly_amount' => $this->monthly_amount,
            'due_day' => $this->due_day,
            'term_months' => $this->term_months,
            'start_date' => $this->start_date->toDateString(),
        ];
    }

    /**
     * The state of the contract, for the history entry of a change of state (RF-33).
     *
     * @return array{status: string}
     */
    public function statusSnapshot(): array
    {
        return ['status' => $this->status->value];
    }

    /** A contract without an end date (RF-8). */
    public function isIndefinite(): bool
    {
        return $this->term_months === null;
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class)->withTrashed();
    }

    /**
     * The client stays reachable after it is archived (RF-35).
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ContractPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(ContractPeriod::class)->orderBy('month');
    }

    /**
     * Chronological, as the staff reads it (RF-84).
     *
     * @return HasMany<ContractHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(ContractHistory::class)->orderBy('created_at')->orderBy('id');
    }
}
