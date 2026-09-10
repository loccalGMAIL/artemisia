<?php

namespace App\Actions;

use App\Enums\AccountHistoryField;
use App\Models\AccountHistory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

class CreateAccountAction
{
    /**
     * Da de alta una cuenta activa, le asigna su único rol, envía el enlace
     * de definición de contraseña y registra el alta en el historial.
     *
     * @param  array{name: string, email: string, role: string}  $data
     */
    public function handle(array $data): User
    {
        $account = new User([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);
        $account->is_active = true;
        $account->save();

        $account->syncRoles([$data['role']]);

        Password::sendResetLink(['email' => $account->email]);

        AccountHistory::create([
            'user_id' => $account->id,
            'field' => AccountHistoryField::Created,
            'old_value' => null,
            'new_value' => ['role' => $data['role'], 'is_active' => $account->is_active],
            'author_id' => Auth::id(),
        ]);

        return $account;
    }
}
