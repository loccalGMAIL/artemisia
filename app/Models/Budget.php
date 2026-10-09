<?php

namespace App\Models;

use App\Enums\BudgetDiscountType;
use App\Enums\BudgetModality;
use App\Enums\BudgetStatus;
use App\Exceptions\BudgetNotEditableException;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'client_id', 'title', 'modality', 'issue_date', 'validity_date', 'status', 'response_date',
    'rejection_reason', 'discount_type', 'discount_value', 'subtotal', 'discount_amount', 'total',
    'created_by',
])]
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modality' => BudgetModality::class,
            'status' => BudgetStatus::class,
            'discount_type' => BudgetDiscountType::class,
            'issue_date' => 'date',
            'validity_date' => 'date',
            'response_date' => 'date',
            'discount_value' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * Items, discount and header change only while the budget is draft or sent (RF-16, RF-48).
     *
     * @throws BudgetNotEditableException
     */
    public function assertEditable(): void
    {
        if (! $this->status->isEditable()) {
            throw new BudgetNotEditableException;
        }
    }

    /**
     * The header data as stored, for history entries.
     *
     * @return array<string, string|int>
     */
    public function headerSnapshot(): array
    {
        return [
            'client_id' => $this->client_id,
            'title' => $this->title,
            'modality' => $this->modality->value,
            'issue_date' => $this->issue_date->toDateString(),
            'validity_date' => $this->validity_date->toDateString(),
        ];
    }

    /**
     * The discount as stored, for history entries (RF-40).
     *
     * @return array<string, string|null>
     */
    public function discountSnapshot(): array
    {
        return [
            'discount_type' => $this->discount_type?->value,
            'discount_value' => $this->discount_value,
            'discount_amount' => $this->discount_amount,
        ];
    }

    /**
     * The status and the answer data as stored, for history entries (RF-50).
     *
     * @return array<string, string|null>
     */
    public function statusSnapshot(): array
    {
        return [
            'status' => $this->status->value,
            'response_date' => $this->response_date?->toDateString(),
            'rejection_reason' => $this->rejection_reason,
        ];
    }

    /**
     * A sent budget whose validity date is before today is expired: it is only flagged, its
     * status does not change (RF-55).
     */
    public function isExpired(): bool
    {
        return $this->status === BudgetStatus::Sent && $this->validity_date->lt(today());
    }

    /**
     * @param  Builder<Budget>  $query
     */
    #[Scope]
    protected function expired(Builder $query): void
    {
        $query->where('status', BudgetStatus::Sent)->whereDate('validity_date', '<', today());
    }

    /** "Importe mensual" for a monthly budget, "Total" otherwise (RF-41). */
    public function totalLabel(): string
    {
        return __('budgets.total_labels.'.$this->modality->value);
    }

    /**
     * The client, even if it was archived afterwards (RF-20).
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
     * @return HasMany<BudgetItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class)->orderBy('id');
    }

    /**
     * Change history, oldest first (RF-34).
     *
     * @return HasMany<BudgetHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(BudgetHistory::class)->orderBy('created_at')->orderBy('id');
    }
}
