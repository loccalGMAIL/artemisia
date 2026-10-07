<?php

namespace App\Actions;

use App\Exceptions\InvalidPasswordLinkException;
use App\Models\User;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class SetPasswordAction
{
    /**
     * @throws ValidationException
     * @throws InvalidPasswordLinkException
     */
    public function handle(string $token, string $email, string $password): void
    {
        Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::min(8)]],
        )->validate();

        $status = PasswordBroker::reset(
            ['email' => $email, 'password' => $password, 'token' => $token],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw new InvalidPasswordLinkException;
        }
    }
}
