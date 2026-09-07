<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesYPermisosSeeder::class);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            CategoriaClienteSeeder::class,
            RubroSeeder::class,
            ServicioYPreciosSeeder::class,
            ClienteSeeder::class,
        ]);

        // Usuario demo para probar el portal de clientes end-to-end.
        $laHueveria = Cliente::query()->where('nombre', 'La Huevería')->first();

        if ($laHueveria) {
            User::factory()->cliente($laHueveria)->create([
                'name' => 'La Huevería',
                'email' => 'cliente@lahueveria.test',
            ]);
        }
    }
}
