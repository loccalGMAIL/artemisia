<?php

namespace Database\Factories;

use App\Models\CategoriaCliente;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'categoria_cliente_id' => CategoriaCliente::factory(),
            'nombre' => fake()->company(),
            'razon_social' => fake()->company().' S.A.',
            'cuit' => fake()->numerify('##-########-#'),
            'email' => fake()->companyEmail(),
            'telefono' => fake()->phoneNumber(),
            'direccion' => fake()->address(),
            'notas' => null,
            'activo' => true,
        ];
    }
}
