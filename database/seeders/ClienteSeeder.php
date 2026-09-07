<?php

namespace Database\Seeders;

use App\Models\CategoriaCliente;
use App\Models\Cliente;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    /**
     * Clientes reales de la agencia. La categoría de "La Huevería" es la
     * confirmada por el negocio (Pyme); el resto son asignaciones tentativas
     * a ajustar según corresponda.
     *
     * @var array<int, array{nombre: string, categoria: string}>
     */
    private const CLIENTES = [
        ['nombre' => 'La Huevería', 'categoria' => 'Pyme'],
        ['nombre' => 'Simplemente Clara', 'categoria' => 'Emprendedor'],
        ['nombre' => 'Encanto', 'categoria' => 'Profesional'],
        ['nombre' => 'La Cueva', 'categoria' => 'Pyme'],
        ['nombre' => 'BW Sportive', 'categoria' => 'Pyme'],
        ['nombre' => 'Olímpica Gimnasios', 'categoria' => 'Profesional'],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $categorias = CategoriaCliente::query()->pluck('id', 'nombre');

        foreach (self::CLIENTES as $cliente) {
            Cliente::query()->firstOrCreate(
                ['nombre' => $cliente['nombre']],
                ['categoria_cliente_id' => $categorias[$cliente['categoria']]],
            );
        }
    }
}
