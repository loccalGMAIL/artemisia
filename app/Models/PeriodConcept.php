<?php

namespace App\Models;

use App\Enums\ConceptType;
use Database\Factories\PeriodConceptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contract_period_id', 'type', 'description', 'amount', 'created_by'])]
class PeriodConcept extends Model
{
    /** @use HasFactory<PeriodConceptFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ConceptType::class,
            'amount' => 'decimal:2',
        ];
    }

    /**
     * The concept as the history keeps it (RF-48, RF-52).
     *
     * @return array{id: int, type: string, description: string, amount: string}
     */
    public function snapshot(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'description' => $this->description,
            'amount' => $this->amount,
        ];
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
}
