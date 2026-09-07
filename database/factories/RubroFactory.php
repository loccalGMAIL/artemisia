<?php

namespace Database\Factories;

use App\Models\Rubro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rubro>
 */
class RubroFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->randomElement(['Redes Sociales', 'Branding', 'Piezas Gráficas']),
            'es_recurrente' => fake()->boolean(),
            'activo' => true,
        ];
    }

    public function recurrente(): static
    {
        return $this->state(['es_recurrente' => true]);
    }

    public function noRecurrente(): static
    {
        return $this->state(['es_recurrente' => false]);
    }
}
