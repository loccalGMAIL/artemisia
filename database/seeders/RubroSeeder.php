<?php

namespace Database\Seeders;

use App\Models\Rubro;
use Illuminate\Database\Seeder;

class RubroSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $rubros = [
            ['nombre' => 'Redes Sociales', 'es_recurrente' => true],
            ['nombre' => 'Branding', 'es_recurrente' => true],
            ['nombre' => 'Piezas Gráficas', 'es_recurrente' => false],
        ];

        foreach ($rubros as $rubro) {
            Rubro::query()->firstOrCreate(['nombre' => $rubro['nombre']], $rubro);
        }
    }
}
