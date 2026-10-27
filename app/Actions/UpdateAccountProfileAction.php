<?php

namespace App\Actions;

use App\Models\User;

class UpdateAccountProfileAction
{
    /**
     * @param  array{name: string, email?: string}  $data
     */
    public function handle(User $target, array $data, User $actor): User
    {
        return $target;
    }
}
