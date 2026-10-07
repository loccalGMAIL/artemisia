<?php

namespace App\Actions;

use App\Enums\AccountHistoryField;
use App\Exceptions\CannotChangeOwnRoleException;
use App\Exceptions\LastActiveAdminException;
use App\Models\AccountHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChangeAccountRoleAction
{
    /**
     * @throws CannotChangeOwnRoleException
     * @throws LastActiveAdminException
     */
    public function handle(User $target, string $role, User $actor): User
    {
        if ($target->is($actor)) {
            throw new CannotChangeOwnRoleException;
        }

        return DB::transaction(function () use ($target, $role, $actor): User {
            $oldRole = $target->roles()->pluck('name')->first();

            if ($oldRole === 'admin' && $role !== 'admin' && $target->is_active && ! $this->otherActiveAdminExists($target)) {
                throw new LastActiveAdminException;
            }

            $target->syncRoles([$role]);

            AccountHistory::query()->create([
                'user_id' => $target->id,
                'field' => AccountHistoryField::RoleChanged,
                'old_value' => ['role' => $oldRole],
                'new_value' => ['role' => $role],
                'author_id' => $actor->id,
            ]);

            return $target->load('roles');
        });
    }

    private function otherActiveAdminExists(User $target): bool
    {
        return User::query()
            ->role('admin')
            ->where('is_active', true)
            ->whereKeyNot($target->getKey())
            ->lockForUpdate()
            ->exists();
    }
}
