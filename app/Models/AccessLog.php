<?php

namespace App\Models;

use App\Enums\AccessMethod;
use App\Enums\AccessOutcome;
use App\Enums\AccessPortal;
use Database\Factories\AccessLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'email_used', 'portal', 'method', 'outcome', 'rejection_reason'])]
class AccessLog extends Model
{
    /** @use HasFactory<AccessLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** Access logs are kept for 24 months (RNF-5). */
    public const RETENTION_MONTHS = 24;

    /**
     * @param  Builder<AccessLog>  $query
     */
    #[Scope]
    protected function expired(Builder $query): void
    {
        $query->where('created_at', '<', now()->subMonths(self::RETENTION_MONTHS));
    }

    /**
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
