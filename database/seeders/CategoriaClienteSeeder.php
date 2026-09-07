<?php

namespace Database\Seeders;

use App\Models\CategoriaCliente;
use Illuminate\Database\Seeder;

class CategoriaClienteSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (['Pyme', 'Profesional', 'Emprendedor'] as $nombre) {
            CategoriaCliente::query()->firstOrCreate(['nombre' => $nombre]);
        }
    }
}
