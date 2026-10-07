<?php

namespace App\Actions;

use App\Enums\AccessMethod;
use App\Enums\AccessOutcome;
use App\Enums\AccessPortal;
use App\Enums\AccessRejection;
use App\Models\User;
use App\Services\AccessLogger;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class AttemptLoginAction
{
    public function __construct(private readonly AccessLogger $accessLogger) {}

    /**
     * Evaluates, in this order and stopping at the first failure (RF-19):
     * credentials or Google identity match an account, the account is active,
     * and its role corresponds to the requested portal. Every attempt is logged.
     *
     * @param  array{email: string, password: string}|SocialiteUser  $input
     */
    public function handle(array|SocialiteUser $input, AccessPortal $portal, AccessMethod $method): AuthResult
    {
        $email = $this->normalizedEmail($input);

        $result = $this->evaluate($email, $input, $portal, $method);

        $this->accessLogger->log(
            $result->user,
            $email,
            $portal,
            $method,
            $result->successful ? AccessOutcome::Success : AccessOutcome::Rejected,
            $result->rejection,
        );

        return $result;
    }

    /**
     * @param  array{email: string, password: string}|SocialiteUser  $input
     */
    private function evaluate(string $email, array|SocialiteUser $input, AccessPortal $portal, AccessMethod $method): AuthResult
    {
        $user = $this->resolveAccount($email, $input, $method);

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
    private function normalizedEmail(array|SocialiteUser $input): string
    {
        $email = $input instanceof SocialiteUser ? $input->getEmail() : ($input['email'] ?? null);

        return is_string($email) ? mb_strtolower(trim($email)) : '';
    }

    /**
     * @param  array{email: string, password: string}|SocialiteUser  $input
     */
    private function resolveAccount(string $email, array|SocialiteUser $input, AccessMethod $method): ?User
    {
        if ($email === '') {
            return null;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

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
