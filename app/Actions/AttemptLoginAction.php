<?php

namespace App\Actions;

use App\Enums\AccessMethod;
use App\Enums\AccessPortal;
use App\Enums\AccessRejection;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class AttemptLoginAction
{
    /**
     * Evaluates, in this order and stopping at the first failure (RF-19):
     * credentials or Google identity match an account, the account is active,
     * and its role corresponds to the requested portal.
     *
     * @param  array{email: string, password: string}|SocialiteUser  $input
     */
    public function handle(array|SocialiteUser $input, AccessPortal $portal, AccessMethod $method): AuthResult
    {
        $user = $this->resolveAccount($input, $method);

        if ($user === null) {
            return AuthResult::rejected(
                $method === AccessMethod::Google
                    ? AccessRejection::AccountNotEnabled
                    : AccessRejection::InvalidCredentials,
            );
        }

        if (! $user->is_active) {
            return AuthResult::rejected(AccessRejection::AccountInactive, $user);
        }

        if (! $portal->allows($user)) {
            return AuthResult::rejected(AccessRejection::WrongPortal, $user);
        }

        return AuthResult::accepted($user);
    }

    /**
     * @param  array{email: string, password: string}|SocialiteUser  $input
     */
    private function resolveAccount(array|SocialiteUser $input, AccessMethod $method): ?User
    {
        $email = $input instanceof SocialiteUser ? $input->getEmail() : ($input['email'] ?? null);

        if (! is_string($email) || trim($email) === '') {
            return null;
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->first();

        if ($user === null) {
            return null;
        }

        if ($method === AccessMethod::Password && ! $this->passwordMatches($user, $input)) {
            return null;
        }

        return $user;
    }

    /**
     * @param  array{email: string, password: string}|SocialiteUser  $input
     */
    private function passwordMatches(User $user, array|SocialiteUser $input): bool
    {
        return is_array($input)
            && $user->password !== null
            && Hash::check((string) ($input['password'] ?? ''), $user->password);
    }
}
