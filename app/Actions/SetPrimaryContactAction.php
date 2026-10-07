<?php

namespace App\Actions;

use App\Enums\ClientHistoryField;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetPrimaryContactAction
{
    /**
     * Un-marks the previous primary contact before marking the new one, in that order,
     * so the unique primary marker is never violated (plan D-8).
     *
     * @throws ValidationException
     */
    public function handle(Client $client, ClientContact $contact, User $actor): void
    {
        if ($contact->client_id !== $client->id) {
            throw ValidationException::withMessages([
                'contact' => __('clients.validation.contact_not_of_client'),
            ]);
        }

        if ($contact->is_primary) {
            return;
        }

        DB::transaction(function () use ($client, $contact, $actor): void {
            $previous = $client->contacts()->where('is_primary', true)->first();

            $previous?->update(['is_primary' => false]);
            $contact->update(['is_primary' => true]);

            ClientHistory::query()->create([
                'client_id' => $client->id,
                'field' => ClientHistoryField::Contacts,
                'old_value' => ['primary_contact_id' => $previous?->id],
                'new_value' => ['primary_contact_id' => $contact->id],
                'author_id' => $actor->id,
            ]);
        });
    }
}
