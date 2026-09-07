<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Config estándar de auditoría para los modelos del negocio: solo registra
 * los atributos declarados en `$fillable`, solo cuando cambian de verdad, y
 * nunca una entrada vacía (p. ej. un `save()` que no modificó nada).
 */
trait RegistraActividad
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('modelo');
    }
}
