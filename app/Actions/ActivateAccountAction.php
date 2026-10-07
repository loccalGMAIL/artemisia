<?php

namespace App\Actions;

use App\Enums\AccountHistoryField;
use App\Models\AccountHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ActivateAccountAction
{
    public function handle(User $target, User $actor): User
    {
        return DB::transaction(function () use ($target, $actor): User {
            $target->update(['is_active' => true]);

            AccountHistory::query()->create([
                'user_id' => $target->id,
                'field' => AccountHistoryField::Activated,
                'old_value' => ['is_active' => false],
                'new_value' => ['is_active' => true],
                'author_id' => $actor->id,
            ]);

            return $target;
        });
    }
}
