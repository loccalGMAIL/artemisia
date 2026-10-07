<?php

namespace App\Actions;

use App\Enums\ClientHistoryField;
use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\ClientHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RestoreClientAction
{
    /**
     * Restores an archived client; it comes back inactive and is reactivated on purpose (RF-32, RF-33).
     */
    public function handle(Client $client, User $actor): Client
    {
        if (! $client->trashed()) {
            return $client;
        }

        return DB::transaction(function () use ($client, $actor): Client {
            $previous = $client->status;

            $client->restore();
            $client->update(['status' => ClientStatus::Inactive]);

            ClientHistory::query()->create([
                'client_id' => $client->id,
                'field' => ClientHistoryField::Archived,
                'old_value' => ['archived' => true, 'status' => $previous->value],
                'new_value' => ['archived' => false, 'status' => ClientStatus::Inactive->value],
                'author_id' => $actor->id,
            ]);

            return $client;
        });
    }
}
