<?php

namespace Database\Factories;

use App\Models\DetallePresupuesto;
use App\Models\Presupuesto;
use App\Models\Servicio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DetallePresupuesto>
 */
class DetallePresupuestoFactory extends Factory
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
            'servicio_id' => Servicio::factory(),
            'descripcion_libre' => null,
            'cantidad' => fake()->randomFloat(2, 1, 5),
            'precio_unitario' => fake()->randomFloat(2, 1000, 100000),
        ];
    }
}
