<?php

namespace App\Models;

use App\Enums\AccountHistoryField;
use Database\Factories\AccountHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'field', 'old_value', 'new_value', 'author_id'])]
class AccountHistory extends Model
{
    /** @use HasFactory<AccountHistoryFactory> */
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
            'field' => AccountHistoryField::class,
            'old_value' => 'array',
            'new_value' => 'array',
        ];
    }

    /**
     * La cuenta sobre la que se registra el hecho.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * La cuenta que realizó el cambio.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
