<?php

namespace Database\Seeders;

use App\Models\WorkCategory;
use Illuminate\Database\Seeder;

class WorkCategorySeeder extends Seeder
{
    /**
     * Work categories are fixed reference data, loaded at install time (spec 002, section 9).
     */
    public function run(): void
    {
        foreach (['Branding', 'Redes', 'Papelería'] as $name) {
            WorkCategory::query()->firstOrCreate(['name' => $name]);
        }
    }
}
