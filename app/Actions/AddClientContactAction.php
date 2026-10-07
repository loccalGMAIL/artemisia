<?php

namespace App\Actions;

use App\Enums\ClientHistoryField;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientHistory;
use App\Models\User;
use App\Services\ClientContactValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddClientContactAction
{
    public const MAX_CONTACTS = 10;

    public function __construct(private readonly ClientContactValidator $validator) {}

    /**
     * The first contact of a client becomes its primary one (RF-16); later ones never do.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(Client $client, array $data, User $actor): ClientContact
    {
        $values = $this->validator->validate($data);

        return DB::transaction(function () use ($client, $values, $actor): ClientContact {
            $existing = $client->contacts()->count();

            if ($existing >= self::MAX_CONTACTS) {
                throw ValidationException::withMessages([
                    'contacts' => __('clients.validation.contacts_limit', ['max' => self::MAX_CONTACTS]),
                ]);
            }

            $contact = $client->contacts()->create([...$values, 'is_primary' => $existing === 0]);

            ClientHistory::query()->create([
                'client_id' => $client->id,
                'field' => ClientHistoryField::Contacts,
                'old_value' => null,
                'new_value' => $contact->snapshot(),
                'author_id' => $actor->id,
            ]);

            return $contact;
        });
    }
}
