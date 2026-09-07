<?php

namespace App\Models;

use App\Enums\EstadoPago;
use App\Models\Concerns\RegistraActividad;
use Database\Factories\ResumenMensualFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResumenMensual extends Model
{
    /** @use HasFactory<ResumenMensualFactory> */
    use HasFactory, RegistraActividad;

    protected $table = 'resumenes_mensuales';

    protected $fillable = [
        'contrato_id',
        'periodo',
        'monto_base',
        'monto_extras',
        'monto_total',
        'estado_pago',
        'fecha_pago',
        'enviado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto_base' => 'decimal:2',
            'monto_extras' => 'decimal:2',
            'monto_total' => 'decimal:2',
            'estado_pago' => EstadoPago::class,
            'fecha_pago' => 'date',
            'enviado' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Contrato, $this>
     */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    /**
     * @return HasMany<ResumenDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(ResumenDetalle::class);
    }

    /**
     * Recalcula base, extras y total separando las líneas por su bandera es_extra.
     */
    public function recalcularMontos(): void
    {
        $this->monto_base = $this->detalles()->where('es_extra', false)->sum('subtotal');
        $this->monto_extras = $this->detalles()->where('es_extra', true)->sum('subtotal');
        $this->monto_total = $this->monto_base + $this->monto_extras;
        $this->saveQuietly();
    }
}
