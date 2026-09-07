<?php

namespace App\Models;

use App\Enums\EstadoProduccion;
use App\Models\Concerns\RegistraActividad;
use Database\Factories\ProduccionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Produccion extends Model
{
    /** @use HasFactory<ProduccionFactory> */
    use HasFactory, RegistraActividad;

    protected $table = 'producciones';

    protected $fillable = [
        'presupuesto_id',
        'estado',
        'fecha_entrega',
        'notas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoProduccion::class,
            'fecha_entrega' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Presupuesto, $this>
     */
    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class);
    }
}
