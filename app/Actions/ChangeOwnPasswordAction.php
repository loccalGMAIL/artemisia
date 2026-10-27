<?php

namespace App\Actions;

use App\Models\User;

class ChangeOwnPasswordAction
{
    public function handle(User $account, string $currentPassword, string $newPassword): void {}
}
