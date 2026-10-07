<?php

namespace App\Actions;

use App\Enums\AccountHistoryField;
use App\Exceptions\DuplicateAccountEmailException;
use App\Models\AccountHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class CreateAccountAction
{
    /**
     * @param  array{name: string, email: string, role: string}  $data
     *
     * @throws DuplicateAccountEmailException
     */
    public function handle(array $data, User $actor): User
    {
        $email = mb_strtolower(trim($data['email']));

        $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existing !== null) {
            throw new DuplicateAccountEmailException($existing);
        }

        $user = DB::transaction(function () use ($data, $email, $actor): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $email,
                'is_active' => true,
            ]);

            $user->syncRoles([$data['role']]);

            AccountHistory::query()->create([
                'user_id' => $user->id,
                'field' => AccountHistoryField::Created,
                'old_value' => null,
                'new_value' => ['role' => $data['role'], 'is_active' => true],
                'author_id' => $actor->id,
            ]);

            return $user;
        });

        Password::sendResetLink(['email' => $user->email]);

        return $user;
    }
}
