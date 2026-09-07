<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Subclase del Permission de Spatie, ver `App\Models\Role` para el motivo.
 * Repuntada en `config('permission.models.permission')`.
 */
class Permission extends SpatiePermission
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
