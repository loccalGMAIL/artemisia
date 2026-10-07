<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    /**
     * The 23 provinces plus the autonomous city of Buenos Aires: fixed reference data
     * (spec 003, section 9).
     */
    public function run(): void
    {
        $names = [
            'Buenos Aires', 'Ciudad Autónoma de Buenos Aires', 'Catamarca', 'Chaco', 'Chubut',
            'Córdoba', 'Corrientes', 'Entre Ríos', 'Formosa', 'Jujuy', 'La Pampa', 'La Rioja',
            'Mendoza', 'Misiones', 'Neuquén', 'Río Negro', 'Salta', 'San Juan', 'San Luis',
            'Santa Cruz', 'Santa Fe', 'Santiago del Estero', 'Tierra del Fuego', 'Tucumán',
        ];

        foreach ($names as $name) {
            Province::query()->firstOrCreate(['name' => $name]);
        }
    }
}
