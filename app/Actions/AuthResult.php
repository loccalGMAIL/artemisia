<?php

namespace App\Actions;

use App\Enums\AccessRejection;
use App\Models\User;

final readonly class AuthResult
{
    private function __construct(
        public bool $successful,
        public ?User $user,
        public ?AccessRejection $rejection,
    ) {}

    public static function accepted(User $user): self
    {
        return new self(true, $user, null);
    }

    public static function rejected(AccessRejection $rejection, ?User $user = null): self
    {
        return new self(false, $user, $rejection);
    }

    public function message(): ?string
    {
        return $this->rejection?->message();
    }
}
