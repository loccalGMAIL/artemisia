<?php

namespace Database\Factories;

use App\Enums\EstadoPago;
use App\Models\Contrato;
use App\Models\ResumenMensual;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResumenMensual>
 */
class ResumenMensualFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contrato_id' => Contrato::factory(),
            'periodo' => fake()->date('Y-m'),
            'monto_base' => 0,
            'monto_extras' => 0,
            'monto_total' => 0,
            'estado_pago' => EstadoPago::Pendiente,
            'fecha_pago' => null,
            'enviado' => false,
        ];
    }
}
