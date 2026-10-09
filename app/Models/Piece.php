<?php

namespace App\Models;

use App\Enums\PieceStatus;
use Database\Factories\PieceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
