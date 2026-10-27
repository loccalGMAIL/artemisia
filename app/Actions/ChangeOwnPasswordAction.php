<?php

namespace App\Actions;

use App\Enums\AccountHistoryField;
use App\Exceptions\AccountHasNoPasswordException;
use App\Models\AccountHistory;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ChangeOwnPasswordAction
{
    /**
     * The account proves it knows its current password. The history records the fact, never a value.
     *
     * @throws AccountHasNoPasswordException
     * @throws ValidationException
     */
    public function handle(User $account, string $currentPassword, string $newPassword): void
    {
        if ($account->password === null) {
            throw new AccountHasNoPasswordException;
        }

        Validator::make(
            ['currentPassword' => $currentPassword, 'password' => $newPassword],
            [
                'currentPassword' => ['required', $this->matches($account)],
                'password' => ['required', Password::min(8)],
            ],
        )->validate();

        DB::transaction(function () use ($account, $newPassword): void {
            $account->forceFill(['password' => $newPassword])->save();

            AccountHistory::query()->create([
                'user_id' => $account->id,
                'field' => AccountHistoryField::PasswordChanged,
                'old_value' => null,
                'new_value' => ['changed' => true],
                'author_id' => $account->id,
            ]);
        });
    }

    private function matches(User $account): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($account): void {
            if (! is_string($value) || ! Hash::check($value, (string) $account->password)) {
                $fail(__('auth.current_password_incorrect'));
            }
        };
    }
}
