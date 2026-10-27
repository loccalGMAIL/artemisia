<?php

namespace App\Actions;

use App\Enums\AccountHistoryField;
use App\Exceptions\CannotChangeOwnEmailException;
use App\Exceptions\DuplicateAccountEmailException;
use App\Models\AccountHistory;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateAccountProfileAction
{
    /**
     * Each field that really changes is saved and recorded in the account history. The email
     * is only touched when it comes in the data, and nobody changes the email of their own account.
     *
     * @param  array{name: string, email?: string}  $data
     *
     * @throws ValidationException
     * @throws CannotChangeOwnEmailException
     * @throws DuplicateAccountEmailException
     */
    public function handle(User $target, array $data, User $actor): User
    {
        $changesEmail = array_key_exists('email', $data);

        $input = ['name' => trim($data['name'])];
        $rules = ['name' => ['required', 'string', 'max:150']];

        if ($changesEmail) {
            $input['email'] = mb_strtolower(trim((string) $data['email']));
            $rules['email'] = ['required', 'string', 'email', 'max:150'];
        }

        $validated = Validator::make($input, $rules)->validate();

        $email = $changesEmail && $validated['email'] !== mb_strtolower($target->email)
            ? $validated['email']
            : null;

        if ($email !== null) {
            if ($target->is($actor)) {
                throw new CannotChangeOwnEmailException;
            }

            $this->ensureEmailIsFree($email, $target);
        }

        try {
            return DB::transaction(fn (): User => $this->apply($target, $validated['name'], $email, $actor));
        } catch (UniqueConstraintViolationException $exception) {
            // Another request took the email between the check and the save.
            $this->ensureEmailIsFree((string) $email, $target);

            throw $exception;
        }
    }

    private function apply(User $target, string $name, ?string $email, User $actor): User
    {
        if ($name !== $target->name) {
            $this->record($target, AccountHistoryField::NameChanged, ['name' => $target->name], ['name' => $name], $actor);

            $target->name = $name;
        }

        if ($email !== null) {
            $this->record($target, AccountHistoryField::EmailChanged, ['email' => $target->email], ['email' => $email], $actor);

            $target->email = $email;
        }

        $target->save();

        return $target;
    }

    /**
     * @param  array<string, string>  $old
     * @param  array<string, string>  $new
     */
    private function record(User $target, AccountHistoryField $field, array $old, array $new, User $actor): void
    {
        AccountHistory::query()->create([
            'user_id' => $target->id,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'author_id' => $actor->id,
        ]);
    }

    /**
     * @throws DuplicateAccountEmailException
     */
    private function ensureEmailIsFree(string $email, User $target): void
    {
        $existing = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereKeyNot($target->getKey())
            ->first();

        if ($existing !== null) {
            throw new DuplicateAccountEmailException($existing);
        }
    }
}
