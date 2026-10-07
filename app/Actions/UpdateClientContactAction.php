<?php

namespace App\Actions;

use App\Enums\ClientHistoryField;
use App\Models\ClientContact;
use App\Models\ClientHistory;
use App\Models\User;
use App\Services\ClientContactValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateClientContactAction
{
    public function __construct(private readonly ClientContactValidator $validator) {}

    /**
     * Edits the data of a contact; which one is primary is changed only by SetPrimaryContactAction.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(ClientContact $contact, array $data, User $actor): ClientContact
    {
        $values = $this->validator->validate($data);

        $before = $contact->snapshot();

        $contact->fill($values);

        if ($contact->snapshot() === $before) {
            return $contact;
        }

        return DB::transaction(function () use ($contact, $before, $actor): ClientContact {
            $contact->save();

            ClientHistory::query()->create([
                'client_id' => $contact->client_id,
                'field' => ClientHistoryField::Contacts,
                'old_value' => $before,
                'new_value' => $contact->snapshot(),
                'author_id' => $actor->id,
            ]);

            return $contact;
        });
    }
}
