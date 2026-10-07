<?php

namespace App\Models;

use Database\Factories\ClientContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['client_id', 'name', 'role', 'phone', 'email', 'is_primary'])]
class ClientContact extends Model
{
    /** @use HasFactory<ClientContactFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * The contact as stored, for history entries.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'phone' => $this->phone,
            'email' => $this->email,
            'is_primary' => (bool) $this->is_primary,
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
