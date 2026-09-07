<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesYPermisosSeeder extends Seeder
{
    /**
     * Modelos del negocio administrados por CRUD completo en el panel staff.
     *
     * @var list<string>
     */
    private const MODELOS_ADMIN = [
        'categoria_cliente',
        'cliente',
        'contrato',
        'costo_proveedor',
        'detalle_presupuesto',
        'precio_servicio',
        'presupuesto',
        'produccion',
        'proveedor',
        'resumen_detalle',
        'resumen_mensual',
        'rubro',
        'servicio',
        'user',
        'role',
    ];

    /**
     * Modelos que el portal de clientes puede consultar (solo lectura).
     *
     * @var list<string>
     */
    private const MODELOS_CLIENTE = [
        'contrato',
        'produccion',
        'resumen_mensual',
    ];

    /**
     * Acciones estándar de policy por modelo de negocio. `delete_any` es la
     * que Filament consulta para las acciones de borrado masivo
     * (`DeleteBulkAction`) — sin permiso propio, una policy sin el método
     * `deleteAny()` deja pasar el borrado masivo igual.
     *
     * @var list<string>
     */
    private const ACCIONES = ['view_any', 'view', 'create', 'update', 'delete', 'delete_any'];

    /**
     * Crea (o actualiza) los roles `staff` y `cliente` y sincroniza sus
     * permisos. Es idempotente: puede correrse de nuevo sin duplicar nada ni
     * dejar permisos huérfanos en cada rol.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisosAdmin = [];

        foreach (self::MODELOS_ADMIN as $modelo) {
            foreach (self::ACCIONES as $accion) {
                $permisosAdmin[] = Permission::findOrCreate("{$accion}_{$modelo}", 'web')->name;
            }
        }

        $permisosCliente = [];

        foreach (self::MODELOS_CLIENTE as $modelo) {
            $permisosCliente[] = Permission::findOrCreate("view_any_{$modelo}", 'web')->name;
            $permisosCliente[] = Permission::findOrCreate("view_{$modelo}", 'web')->name;
        }

        // El log de actividad (etapa 3) no sigue el patrón CRUD estándar: solo se consulta.
        $permisosAdmin[] = Permission::findOrCreate('view_any_activity', 'web')->name;
        $permisosAdmin[] = Permission::findOrCreate('view_activity', 'web')->name;

        Role::findOrCreate('staff', 'web')->syncPermissions($permisosAdmin);
        Role::findOrCreate('cliente', 'web')->syncPermissions($permisosCliente);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
