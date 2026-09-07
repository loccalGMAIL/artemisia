<?php

namespace Database\Factories;

use App\Enums\EstadoPresupuesto;
use App\Models\Cliente;
use App\Models\Presupuesto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Presupuesto>
 */
class PresupuestoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'fecha' => fake()->date(),
            'estado' => EstadoPresupuesto::Borrador,
            'total' => 0,
            'notas' => null,
        ];
    }
}
