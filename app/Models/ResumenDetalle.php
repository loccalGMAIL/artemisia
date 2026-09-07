<?php

namespace App\Models;

use Database\Factories\ResumenDetalleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumenDetalle extends Model
{
    /** @use HasFactory<ResumenDetalleFactory> */
    use HasFactory;

    protected $table = 'resumen_detalle';

    protected $fillable = [
        'resumen_mensual_id',
        'servicio_id',
        'descripcion_libre',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'es_extra',
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
            'es_extra' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $detalle): void {
            $detalle->subtotal = $detalle->cantidad * $detalle->precio_unitario;
        });

        // Ídem DetallePresupuesto: se re-consulta el resumen en lugar de usar
        // la relación ya cargada, que puede haber quedado obsoleta por una
        // línea hermana y hacer que Eloquent omita el guardado por "no sucio".
        static::saved(fn (self $detalle) => $detalle->resumenMensual()->first()?->recalcularMontos());
        static::deleted(fn (self $detalle) => $detalle->resumenMensual()->first()?->recalcularMontos());
    }

    /**
     * @return BelongsTo<ResumenMensual, $this>
     */
    public function resumenMensual(): BelongsTo
    {
        return $this->belongsTo(ResumenMensual::class);
    }

    /**
     * @return BelongsTo<Servicio, $this>
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }
}
