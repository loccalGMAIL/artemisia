<?php

namespace Database\Factories;

use App\Models\ResumenDetalle;
use App\Models\ResumenMensual;
use App\Models\Servicio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResumenDetalle>
 */
class ResumenDetalleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resumen_mensual_id' => ResumenMensual::factory(),
            'servicio_id' => Servicio::factory(),
            'descripcion_libre' => null,
            'cantidad' => fake()->randomFloat(2, 1, 5),
            'precio_unitario' => fake()->randomFloat(2, 1000, 100000),
            'es_extra' => false,
        ];
    }
}
