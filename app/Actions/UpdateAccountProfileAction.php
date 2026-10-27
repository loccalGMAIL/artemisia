<?php

namespace App\Actions;

use App\Enums\AccountHistoryField;
use App\Models\AccountHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateAccountProfileAction
{
    /**
     * Each field that really changes is saved and recorded in the account history.
     *
     * @param  array{name: string, email?: string}  $data
     *
     * @throws ValidationException
     */
    public function handle(User $target, array $data, User $actor): User
    {
        $name = Validator::make(
            ['name' => trim((string) ($data['name'] ?? ''))],
            ['name' => ['required', 'string', 'max:150']],
        )->validate()['name'];

        return DB::transaction(function () use ($target, $name, $actor): User {
            if ($name !== $target->name) {
                $oldName = $target->name;

                $target->update(['name' => $name]);

                AccountHistory::query()->create([
                    'user_id' => $target->id,
                    'field' => AccountHistoryField::NameChanged,
                    'old_value' => ['name' => $oldName],
                    'new_value' => ['name' => $name],
                    'author_id' => $actor->id,
                ]);
            }

            return $target;
        });
    }
}
