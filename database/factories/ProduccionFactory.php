<?php

namespace Database\Factories;

use App\Enums\EstadoProduccion;
use App\Models\Presupuesto;
use App\Models\Produccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Produccion>
 */
class ProduccionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'presupuesto_id' => Presupuesto::factory(),
            'estado' => EstadoProduccion::Pendiente,
            'fecha_entrega' => null,
            'notas' => null,
        ];
    }
}
