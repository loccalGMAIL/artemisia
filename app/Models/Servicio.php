<?php

namespace App\Models;

use App\Enums\UnidadServicio;
use Carbon\CarbonInterface;
use Database\Factories\ServicioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Servicio extends Model
{
    /** @use HasFactory<ServicioFactory> */
    use HasFactory;

    protected $fillable = [
        'rubro_id',
        'nombre',
        'descripcion',
        'unidad',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unidad' => UnidadServicio::class,
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Rubro, $this>
     */
    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class);
    }

    /**
     * @return HasMany<PrecioServicio, $this>
     */
    public function precios(): HasMany
    {
        return $this->hasMany(PrecioServicio::class);
    }

    /**
     * Resuelve el precio vigente para una categoría de cliente en una fecha dada.
     *
     * Prioriza el precio específico de la categoría; si no existe ninguno,
     * cae al precio único (categoria_cliente_id nulo), como en Piezas Gráficas.
     */
    public function precioVigente(?int $categoriaClienteId, ?CarbonInterface $fecha = null): ?string
    {
        $fecha ??= Carbon::today();

        if ($categoriaClienteId !== null) {
            $precio = $this->precios()
                ->where('categoria_cliente_id', $categoriaClienteId)
                ->where('vigente_desde', '<=', $fecha)
                ->orderByDesc('vigente_desde')
                ->first();

            if ($precio) {
                return $precio->precio;
            }
        }

        $precioUnico = $this->precios()
            ->whereNull('categoria_cliente_id')
            ->where('vigente_desde', '<=', $fecha)
            ->orderByDesc('vigente_desde')
            ->first();

        return $precioUnico?->precio;
    }
}
