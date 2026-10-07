<?php

namespace App\Actions;

use App\Enums\AccountHistoryField;
use App\Exceptions\CannotDeactivateOwnAccountException;
use App\Exceptions\LastActiveAdminException;
use App\Models\AccountHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class DeactivateAccountAction
{
    /**
     * @throws CannotDeactivateOwnAccountException
     * @throws LastActiveAdminException
     */
    public function handle(User $target, User $actor): User
    {
        if ($target->is($actor)) {
            throw new CannotDeactivateOwnAccountException;
        }

        return DB::transaction(function () use ($target, $actor): User {
            if ($target->isLastActiveAdmin()) {
                throw new LastActiveAdminException;
            }

            $target->update(['is_active' => false]);

            Password::deleteToken($target);

            AccountHistory::query()->create([
                'user_id' => $target->id,
                'field' => AccountHistoryField::Deactivated,
                'old_value' => ['is_active' => true],
                'new_value' => ['is_active' => false],
                'author_id' => $actor->id,
            ]);

            return $target;
        });
    }
}
