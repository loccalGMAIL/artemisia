<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\BudgetItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['budget_id', 'service_id', 'name', 'description', 'unit_price', 'quantity'])]
class BudgetItem extends Model
{
    /** @use HasFactory<BudgetItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    /**
     * Amount of the line: copied price times quantity (RF-25), exact to the cent.
     *
     * @return Attribute<string, never>
     */
    protected function amount(): Attribute
    {
        return Attribute::get(fn (): string => Money::fromCents($this->amountInCents()));
    }

    /**
     * The item as stored, for history entries (RF-32, RF-33).
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'name' => $this->name,
            'description' => $this->description,
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'amount' => $this->amount,
        ];
    }

    public function amountInCents(): int
    {
        return Money::toCents($this->unit_price) * (int) $this->quantity;
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
