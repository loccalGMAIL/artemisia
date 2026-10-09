<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'work_category_id', 'list_price', 'is_active'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'list_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Services that can be added to a budget: a deactivated one is not offered (RF-8).
     *
     * @param  Builder<Service>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return BelongsTo<WorkCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(WorkCategory::class, 'work_category_id');
    }

    /**
     * Price history, oldest first (RF-10).
     *
     * @return HasMany<ServicePriceHistory, $this>
     */
    public function priceHistories(): HasMany
    {
        return $this->hasMany(ServicePriceHistory::class)->orderBy('created_at')->orderBy('id');
    }
}
