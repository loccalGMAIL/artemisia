<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

/**
 * El registro de actividad es de solo lectura: nadie crea, edita ni borra
 * una entrada a mano desde el panel, solo se consulta.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_activity');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can('view_activity');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Activity $activity): bool
    {
        return false;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Activity $activity): bool
    {
        return false;
    }

    public function forceDelete(User $user, Activity $activity): bool
    {
        return false;
    }
}
