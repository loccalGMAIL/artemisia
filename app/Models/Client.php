<?php

namespace App\Models;

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'person_type', 'first_name', 'last_name', 'company_name', 'document', 'status',
    'street', 'street_number', 'city', 'province_id', 'postal_code', 'created_by',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'person_type' => ClientType::class,
            'status' => ClientStatus::class,
        ];
    }

    /**
     * Full name of an individual, or the company name (RF-5). Computed, not stored (plan D-2).
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->person_type === ClientType::Company
            ? (string) $this->company_name
            : trim($this->first_name.' '.$this->last_name));
    }

    /**
     * Phone of the primary contact, or of another contact if the primary has none (RF-21).
     * Null when no contact has a phone (RF-22).
     */
    public function contactPhone(): ?string
    {
        return $this->contacts()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->value('phone');
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ClientContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    /**
     * @return HasMany<ClientHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(ClientHistory::class);
    }

    /**
     * Portal accounts linked to this client.
     *
     * @return HasMany<User, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
