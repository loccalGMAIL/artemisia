<?php

namespace Database\Factories;

use App\Models\CategoriaCliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoriaCliente>
 */
class CategoriaClienteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->randomElement(['Pyme', 'Profesional', 'Emprendedor']),
            'descripcion' => fake()->sentence(),
            'activo' => true,
        ];
    }
}
