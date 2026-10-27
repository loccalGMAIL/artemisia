<?php

namespace App\Actions;

use App\Enums\AccountHistoryField;
use App\Exceptions\AccountHasNoPasswordException;
use App\Models\AccountHistory;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
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

        $this->keepCurrentSessionOpen($account);
    }

    /**
     * Every session remembers the password hash it was opened with and the session middleware cuts
     * the ones that no longer match, which closes the other sessions (RF-10). The session that made
     * the change takes the new hash so it is the one that stays.
     */
    private function keepCurrentSessionOpen(User $account): void
    {
        $guardName = Filament::getAuthGuard();
        $guard = Auth::guard($guardName);

        if (! $guard instanceof SessionGuard || $guard->id() !== $account->getAuthIdentifier()) {
            return;
        }

        session()->put("password_hash_{$guardName}", $guard->hashPasswordForCookie($account->password));
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
