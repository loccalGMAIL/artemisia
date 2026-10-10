<?php

namespace App\Actions;

use App\Enums\AccessMethod;
use App\Enums\AccessOutcome;
use App\Enums\AccessPortal;
use App\Enums\AccessRejection;
use App\Models\User;
use App\Services\AccessLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class AttemptLoginAction
{
    private const MAX_FAILED_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function __construct(private readonly AccessLogger $accessLogger) {}

    /**
     * Evaluates, in this order and stopping at the first failure (RF-19):
     * credentials or Google identity match an account, the account is active,
     * and its role corresponds to the requested portal. Every attempt is logged.
     *
     * @param  array{email?: string|null, password?: string|null}|SocialiteUser  $input
     */
    public function handle(array|SocialiteUser $input, AccessPortal $portal, AccessMethod $method): AuthResult
    {
        $email = $this->normalizedEmail($input);

        $result = $this->evaluateWithThrottle($email, $input, $portal, $method);

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
     * Password attempts are limited per email: after MAX_FAILED_ATTEMPTS invalid
     * credentials within DECAY_SECONDS, every further attempt is rejected (RNF-2).
     *
     * @param  array{email?: string|null, password?: string|null}|SocialiteUser  $input
     */
    private function evaluateWithThrottle(string $email, array|SocialiteUser $input, AccessPortal $portal, AccessMethod $method): AuthResult
    {
        if ($method !== AccessMethod::Password) {
            return $this->evaluate($email, $input, $portal, $method);
        }

        $key = 'login-attempts:'.$email;

        if (RateLimiter::tooManyAttempts($key, self::MAX_FAILED_ATTEMPTS)) {
            return AuthResult::rejected(AccessRejection::TooManyAttempts);
        }

        $result = $this->evaluate($email, $input, $portal, $method);

        if ($result->rejection === AccessRejection::InvalidCredentials) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
        }

        return $result;
    }

    /**
     * @param  array{email?: string|null, password?: string|null}|SocialiteUser  $input
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
     * @param  array{email?: string|null, password?: string|null}|SocialiteUser  $input
     */
    private function normalizedEmail(array|SocialiteUser $input): string
    {
        $email = $input instanceof SocialiteUser ? $input->getEmail() : ($input['email'] ?? null);

        return is_string($email) ? mb_strtolower(trim($email)) : '';
    }

    /**
     * @param  array{email?: string|null, password?: string|null}|SocialiteUser  $input
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
     * @param  array{email?: string|null, password?: string|null}|SocialiteUser  $input
     */
    private function passwordMatches(User $user, array|SocialiteUser $input): bool
    {
        return is_array($input)
            && $user->password !== null
            && Hash::check((string) ($input['password'] ?? ''), $user->password);
    }
}
