<?php

namespace App\Models;

use Database\Factories\DetallePresupuestoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DetallePresupuesto extends Model
{
    /** @use HasFactory<DetallePresupuestoFactory> */
    use HasFactory;

    protected $fillable = [
        'presupuesto_id',
        'servicio_id',
        'descripcion_libre',
        'cantidad',
        'precio_unitario',
        'subtotal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $detalle): void {
            $detalle->subtotal = $detalle->cantidad * $detalle->precio_unitario;
        });

        // Se busca el presupuesto de nuevo en lugar de usar la relación ya
        // cargada en $detalle: esta pudo quedar en caché desde antes de que
        // una línea hermana actualizara el total, y guardarla de nuevo con un
        // valor que coincide por casualidad con su copia vieja no dispara la
        // escritura (Eloquent la ve como "no sucia").
        static::saved(fn (self $detalle) => $detalle->presupuesto()->first()?->recalcularTotal());
        static::deleted(fn (self $detalle) => $detalle->presupuesto()->first()?->recalcularTotal());
    }

    /**
     * @return BelongsTo<Presupuesto, $this>
     */
    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class);
    }

    /**
     * @return BelongsTo<Servicio, $this>
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    /**
     * @return MorphMany<CostoProveedor, $this>
     */
    public function costosProveedor(): MorphMany
    {
        return $this->morphMany(CostoProveedor::class, 'costeable');
    }
}
