<?php

namespace App\Models;

use App\Models\Concerns\RegistraActividad;
use Database\Factories\PrecioServicioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrecioServicio extends Model
{
    /** @use HasFactory<PrecioServicioFactory> */
    use HasFactory, RegistraActividad;

    protected $table = 'precios_servicio';

    protected $fillable = [
        'servicio_id',
        'categoria_cliente_id',
        'precio',
        'vigente_desde',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'vigente_desde' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Servicio, $this>
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    /**
     * @return BelongsTo<CategoriaCliente, $this>
     */
    public function categoriaCliente(): BelongsTo
    {
        return $this->belongsTo(CategoriaCliente::class);
    }
}
