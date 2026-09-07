<?php

namespace Database\Factories;

use App\Enums\EstadoContrato;
use App\Models\Contrato;
use App\Models\Presupuesto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Contrato>
 */
class ContratoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fechaInicio = Carbon::parse(fake()->date());

        return [
            'presupuesto_id' => Presupuesto::factory(),
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaInicio->copy()->addMonths(3),
            'estado' => EstadoContrato::Activo,
        ];
    }
}
