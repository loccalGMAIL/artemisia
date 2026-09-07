<?php

namespace Database\Seeders;

use App\Enums\UnidadServicio;
use App\Models\CategoriaCliente;
use App\Models\Rubro;
use App\Models\Servicio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ServicioYPreciosSeeder extends Seeder
{
    /**
     * Fecha de vigencia de esta lista de precios.
     */
    private const VIGENTE_DESDE = '2026-04-01';

    /**
     * Catálogo de servicios por rubro.
     *
     * Para "Redes Sociales" y "Branding" (rubros recurrentes), 'precios' trae un
     * valor por categoría de cliente. Para "Piezas Gráficas" (no recurrente),
     * 'precios' trae un único valor (categoria_cliente_id = null en la BD).
     *
     * Sólo "Planificación de estrategia" tiene los precios reales de abril 2026
     * confirmados por el negocio. El resto son valores de referencia para poder
     * probar el sistema de punta a punta — reemplazar por los precios reales del
     * Excel de abril 2026 antes de usar esto en producción.
     *
     * @var array<string, array<int, array{nombre: string, unidad: UnidadServicio, precios: array<string, float>|float}>>
     */
    private const CATALOGO = [
        'Redes Sociales' => [
            // TODO: reemplazar por los precios reales del Excel de abril 2026
            [
                'nombre' => 'Planificación de estrategia',
                'unidad' => UnidadServicio::Unidad,
                'precios' => ['Pyme' => 64600, 'Profesional' => 54900, 'Emprendedor' => 51700], // precios reales confirmados
            ],
            [
                'nombre' => 'Community management mensual',
                'unidad' => UnidadServicio::Unidad,
                'precios' => ['Pyme' => 58000, 'Profesional' => 49000, 'Emprendedor' => 45000],
            ],
            [
                'nombre' => 'Placa diseñada',
                'unidad' => UnidadServicio::Unidad,
                'precios' => ['Pyme' => 8500, 'Profesional' => 7200, 'Emprendedor' => 6500],
            ],
            [
                'nombre' => 'Reel o video corto',
                'unidad' => UnidadServicio::Unidad,
                'precios' => ['Pyme' => 15500, 'Profesional' => 13200, 'Emprendedor' => 12000],
            ],
            [
                'nombre' => 'Cobertura de evento',
                'unidad' => UnidadServicio::Hora,
                'precios' => ['Pyme' => 12000, 'Profesional' => 10500, 'Emprendedor' => 9500],
            ],
            [
                'nombre' => 'Gestión de pauta publicitaria',
                'unidad' => UnidadServicio::Unidad,
                'precios' => ['Pyme' => 22000, 'Profesional' => 18700, 'Emprendedor' => 17000],
            ],
        ],
        'Branding' => [
            [
                'nombre' => 'Diseño de logotipo',
                'unidad' => UnidadServicio::Unidad,
                'precios' => ['Pyme' => 95000, 'Profesional' => 80000, 'Emprendedor' => 72000],
            ],
            [
                'nombre' => 'Manual de marca',
                'unidad' => UnidadServicio::Unidad,
                'precios' => ['Pyme' => 68000, 'Profesional' => 58000, 'Emprendedor' => 52000],
            ],
            [
                'nombre' => 'Papelería institucional',
                'unidad' => UnidadServicio::Unidad,
                'precios' => ['Pyme' => 34000, 'Profesional' => 29000, 'Emprendedor' => 26000],
            ],
        ],
        'Piezas Gráficas' => [
            [
                'nombre' => 'Diseño de tarjeta personal',
                'unidad' => UnidadServicio::Unidad,
                'precios' => 6500,
            ],
            [
                'nombre' => 'Diseño de flyer',
                'unidad' => UnidadServicio::Unidad,
                'precios' => 9000,
            ],
            [
                'nombre' => 'Diseño de banner web',
                'unidad' => UnidadServicio::Unidad,
                'precios' => 7500,
            ],
            [
                'nombre' => 'Retoque fotográfico',
                'unidad' => UnidadServicio::Unidad,
                'precios' => 4200,
            ],
            [
                'nombre' => 'Animación básica',
                'unidad' => UnidadServicio::Segundo,
                'precios' => 350,
            ],
        ],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $vigenteDesde = Carbon::parse(self::VIGENTE_DESDE);
        $categorias = CategoriaCliente::query()->pluck('id', 'nombre');

        foreach (self::CATALOGO as $nombreRubro => $servicios) {
            $rubro = Rubro::query()->where('nombre', $nombreRubro)->firstOrFail();

            foreach ($servicios as $definicion) {
                $servicio = Servicio::query()->firstOrCreate(
                    ['rubro_id' => $rubro->id, 'nombre' => $definicion['nombre']],
                    ['unidad' => $definicion['unidad']],
                );

                if (is_array($definicion['precios'])) {
                    foreach ($definicion['precios'] as $nombreCategoria => $precio) {
                        $servicio->precios()->firstOrCreate([
                            'categoria_cliente_id' => $categorias[$nombreCategoria],
                            'vigente_desde' => $vigenteDesde,
                        ], [
                            'precio' => $precio,
                        ]);
                    }
                } else {
                    $servicio->precios()->firstOrCreate([
                        'categoria_cliente_id' => null,
                        'vigente_desde' => $vigenteDesde,
                    ], [
                        'precio' => $definicion['precios'],
                    ]);
                }
            }
        }
    }
}
