<?php

namespace App\Actions;

use App\Enums\ClientHistoryField;
use App\Models\ClientContact;
use App\Models\ClientHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RemoveClientContactAction
{
    /**
     * Deletes the contact for good; its data is kept as a snapshot in the history (plan D-9).
     * If it was the primary one and others remain, another becomes primary (RF-20).
     */
    public function handle(ClientContact $contact, User $actor): void
    {
        DB::transaction(function () use ($contact, $actor): void {
            $snapshot = $contact->snapshot();
            $wasPrimary = $contact->is_primary;

            $contact->delete();

            $newPrimary = $wasPrimary
                ? ClientContact::query()->where('client_id', $contact->client_id)->orderBy('id')->first()
                : null;

            $newPrimary?->update(['is_primary' => true]);

            ClientHistory::query()->create([
                'client_id' => $contact->client_id,
                'field' => ClientHistoryField::Contacts,
                'old_value' => $snapshot,
                'new_value' => array_filter([
                    'removed' => true,
                    'new_primary_contact_id' => $newPrimary?->id,
                ], fn ($value) => $value !== null),
                'author_id' => $actor->id,
            ]);
        });
    }
}
