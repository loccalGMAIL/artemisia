<?php

namespace App\Models;

use App\Enums\ClientHistoryField;
use Database\Factories\ClientHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['client_id', 'field', 'old_value', 'new_value', 'author_id'])]
class ClientHistory extends Model
{
    /** @use HasFactory<ClientHistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * The history is append-only: an entry is never edited or deleted (RF-34, RNF-7).
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Client history entries cannot be modified.'));
        static::deleting(fn () => throw new LogicException('Client history entries cannot be deleted.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field' => ClientHistoryField::class,
            'old_value' => 'array',
            'new_value' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
