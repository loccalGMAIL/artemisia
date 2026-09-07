<?php

namespace App\Models;

use App\Enums\EstadoContrato;
use Carbon\CarbonPeriod;
use Database\Factories\ContratoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Contrato extends Model
{
    /** @use HasFactory<ContratoFactory> */
    use HasFactory;

    protected $fillable = [
        'presupuesto_id',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'estado' => EstadoContrato::class,
        ];
    }

    /**
     * @return BelongsTo<Presupuesto, $this>
     */
    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class);
    }

    /**
     * @return HasMany<ResumenMensual, $this>
     */
    public function resumenesMensuales(): HasMany
    {
        return $this->hasMany(ResumenMensual::class);
    }

    /**
     * Periodos mensuales cubiertos por el contrato, en formato 'YYYY-MM'.
     *
     * @return array<int, string>
     */
    public function periodos(): array
    {
        $periodo = CarbonPeriod::create(
            $this->fecha_inicio->copy()->startOfMonth(),
            '1 month',
            $this->fecha_fin->copy()->startOfMonth(),
        )->excludeEndDate();

        return collect($periodo)
            ->map(fn (Carbon $fecha): string => $fecha->format('Y-m'))
            ->all();
    }

    /**
     * Crea los resúmenes mensuales del contrato, precargados con las líneas del
     * presupuesto origen como plan base. Es idempotente: los periodos ya existentes
     * no se duplican ni se vuelven a precargar.
     */
    public function generarResumenesMensuales(): void
    {
        DB::transaction(function (): void {
            $this->loadMissing('presupuesto.detalles');

            foreach ($this->periodos() as $periodo) {
                $resumen = $this->resumenesMensuales()->firstOrNew([
                    'periodo' => $periodo,
                ]);

                if ($resumen->exists) {
                    continue;
                }

                $resumen->save();

                foreach ($this->presupuesto->detalles as $detalle) {
                    $resumen->detalles()->create([
                        'servicio_id' => $detalle->servicio_id,
                        'descripcion_libre' => $detalle->descripcion_libre,
                        'cantidad' => $detalle->cantidad,
                        'precio_unitario' => $detalle->precio_unitario,
                        'es_extra' => false,
                    ]);
                }
            }
        });
    }
}
