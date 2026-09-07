<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Subclase del Activity de Spatie: vive en App\Models para que el
 * autodescubrimiento de policies de Laravel encuentre
 * `App\Policies\ActivityPolicy`. Repuntada en `config('activitylog.activity_model')`.
 */
class Activity extends SpatieActivity {}
