<?php

namespace App\Models;

use App\Enums\PieceStatus;
use App\Exceptions\PieceDeliveredException;
use Database\Factories\PieceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'budget_id', 'budget_item_id', 'name', 'description', 'work_category_id', 'status',
    'assignee_id', 'due_date', 'created_by',
])]
class Piece extends Model
{
    /** @use HasFactory<PieceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PieceStatus::class,
            'due_date' => 'date',
        ];
    }

    /**
     * Pieces delegated to the given account (RF-13, RF-43).
     *
     * @param  Builder<Piece>  $query
     */
    #[Scope]
    protected function delegatedTo(Builder $query, User $assignee): void
    {
        $query->where('assignee_id', $assignee->id);
    }

    /**
     * Pieces whose committed date has passed and that are not delivered yet (RF-17, RF-44).
     *
     * @param  Builder<Piece>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->whereDate('due_date', '<', today())
            ->where('status', '!=', PieceStatus::Delivered);
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->lt(today())
            && $this->status !== PieceStatus::Delivered;
    }

    /**
     * A delivered piece admits no change of state, file or owner (RF-25).
     *
     * @throws PieceDeliveredException
     */
    public function assertNotDelivered(): void
    {
        if ($this->status === PieceStatus::Delivered) {
            throw new PieceDeliveredException;
        }
    }

    /**
     * The piece as it is created, for its first history entry (RF-38).
     *
     * @return array<string, int|string|null>
     */
    public function creationSnapshot(): array
    {
        return [
            'name' => $this->name,
            'status' => PieceStatus::Pending->value,
            'budget_item_id' => $this->budget_item_id,
            'work_category_id' => $this->work_category_id,
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
     * The item the piece came from. Null on loose pieces, and also when the item was removed
     * from the budget afterwards (RF-8, plan D-7).
     *
     * @return BelongsTo<BudgetItem, $this>
     */
    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class);
    }

    /**
     * @return BelongsTo<WorkCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(WorkCategory::class, 'work_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PieceApprovalSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(PieceApprovalSubmission::class);
    }

    /**
     * @return HasMany<PieceHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(PieceHistory::class);
    }
}
