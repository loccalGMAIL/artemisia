<?php

namespace Database\Factories;

use App\Enums\UnidadServicio;
use App\Models\Rubro;
use App\Models\Servicio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Servicio>
 */
class ServicioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rubro_id' => Rubro::factory(),
            'nombre' => fake()->words(3, true),
            'descripcion' => fake()->sentence(),
            'unidad' => UnidadServicio::Unidad,
            'activo' => true,
        ];
    }
}
