<?php

namespace App\Models;

use App\Enums\AccessMethod;
use App\Enums\AccessOutcome;
use App\Enums\AccessPortal;
use Database\Factories\AccessLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'email_used', 'portal', 'method', 'outcome', 'rejection_reason'])]
class AccessLog extends Model
{
    /** @use HasFactory<AccessLogFactory> */
    use HasFactory;

    /**
     * Tabla de solo alta: nunca se actualiza una fila ya escrita.
     */
    const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'portal' => AccessPortal::class,
            'method' => AccessMethod::class,
            'outcome' => AccessOutcome::class,
        ];
    }

    /**
     * La cuenta que intentó ingresar, si el email corresponde a alguna.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
