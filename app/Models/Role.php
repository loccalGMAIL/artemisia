<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Subclase del Role de Spatie: permite agregarle `LogsActivity` (no se puede
 * sumar un trait a una clase de un paquete de terceros) y que el
 * autodescubrimiento de policies de Laravel encuentre `App\Policies\RolePolicy`
 * a partir de este namespace. Repuntada en `config('permission.models.role')`.
 */
class Role extends SpatieRole
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('accesos');
    }
}
