<?php

namespace App\Models;

use App\Enums\EstadoPresupuesto;
use App\Enums\EstadoProduccion;
use Database\Factories\PresupuestoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class Presupuesto extends Model
{
    /** @use HasFactory<PresupuestoFactory> */
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'fecha',
        'estado',
        'total',
        'notas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'estado' => EstadoPresupuesto::class,
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return HasMany<DetallePresupuesto, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePresupuesto::class);
    }

    /**
     * @return HasOne<Contrato, $this>
     */
    public function contrato(): HasOne
    {
        return $this->hasOne(Contrato::class);
    }

    /**
     * @return HasOne<Produccion, $this>
     */
    public function produccion(): HasOne
    {
        return $this->hasOne(Produccion::class);
    }

    /**
     * @return MorphMany<CostoProveedor, $this>
     */
    public function costosProveedor(): MorphMany
    {
        return $this->morphMany(CostoProveedor::class, 'costeable');
    }

    /**
     * Recalcula el total sumando los subtotales de las líneas y persiste sin
     * disparar eventos de modelo (evita recursión con los observers de las líneas).
     */
    public function recalcularTotal(): void
    {
        $this->total = $this->detalles()->sum('subtotal');
        $this->saveQuietly();
    }

    /**
     * Determina si el presupuesto corresponde a rubros recurrentes (Redes, Branding).
     * Las líneas ad-hoc (sin servicio) se ignoran. Devuelve false si no hay ninguna
     * línea con servicio, o si mezcla rubros recurrentes y no recurrentes.
     */
    public function esRecurrente(): bool
    {
        $this->loadMissing('detalles.servicio.rubro');

        $rubros = $this->detalles
            ->pluck('servicio.rubro')
            ->filter();

        if ($rubros->isEmpty()) {
            return false;
        }

        return $rubros->every(fn (Rubro $rubro): bool => $rubro->es_recurrente);
    }

    /**
     * Confirma el presupuesto y genera el Contrato o la Producción correspondiente,
     * según sus rubros. Es idempotente: si ya existe, lo devuelve sin duplicar.
     */
    public function confirmar(): Contrato|Produccion
    {
        return DB::transaction(function (): Contrato|Produccion {
            $this->loadMissing(['contrato', 'produccion']);

            if ($this->contrato) {
                return $this->contrato;
            }

            if ($this->produccion) {
                return $this->produccion;
            }

            $this->estado = EstadoPresupuesto::Confirmado;
            $this->save();

            if ($this->esRecurrente()) {
                $fechaInicio = $this->fecha->copy();

                return $this->contrato()->create([
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaInicio->copy()->addMonths(3),
                ]);
            }

            return $this->produccion()->create([
                'estado' => EstadoProduccion::Pendiente,
            ]);
        });
    }
}
