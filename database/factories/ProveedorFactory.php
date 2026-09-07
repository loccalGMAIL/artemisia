<?php

namespace Database\Factories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->company(),
            'cuit' => fake()->numerify('##-########-#'),
            'email' => fake()->companyEmail(),
            'telefono' => fake()->phoneNumber(),
            'notas' => null,
            'activo' => true,
        ];
    }
}
