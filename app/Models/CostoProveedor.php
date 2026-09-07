<?php

namespace App\Models;

use Database\Factories\CostoProveedorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CostoProveedor extends Model
{
    /** @use HasFactory<CostoProveedorFactory> */
    use HasFactory;

    protected $table = 'costos_proveedor';

    protected $fillable = [
        'proveedor_id',
        'costeable_type',
        'costeable_id',
        'monto',
        'descripcion',
        'fecha',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Proveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function costeable(): MorphTo
    {
        return $this->morphTo();
    }
}
