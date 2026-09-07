<?php

namespace Database\Factories;

use App\Models\CostoProveedor;
use App\Models\Presupuesto;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostoProveedor>
 */
class CostoProveedorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proveedor_id' => Proveedor::factory(),
            'costeable_type' => Presupuesto::class,
            'costeable_id' => Presupuesto::factory(),
            'monto' => fake()->randomFloat(2, 1000, 50000),
            'descripcion' => fake()->sentence(),
            'fecha' => fake()->date(),
        ];
    }
}
