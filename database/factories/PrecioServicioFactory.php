<?php

namespace Database\Factories;

use App\Models\CategoriaCliente;
use App\Models\PrecioServicio;
use App\Models\Servicio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrecioServicio>
 */
class PrecioServicioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'servicio_id' => Servicio::factory(),
            'categoria_cliente_id' => CategoriaCliente::factory(),
            'precio' => fake()->randomFloat(2, 1000, 100000),
            'vigente_desde' => fake()->date(),
        ];
    }

    public function precioUnico(): static
    {
        return $this->state(['categoria_cliente_id' => null]);
    }
}
