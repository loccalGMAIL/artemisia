<?php

namespace App\Actions;

use App\Enums\ClientHistoryField;
use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\ClientHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArchiveClientAction
{
    /**
     * Archives (soft deletes) a client and marks it inactive (RF-28, RF-29). Nothing is erased (RF-31).
     */
    public function handle(Client $client, User $actor): Client
    {
        if ($client->trashed()) {
            return $client;
        }

        return DB::transaction(function () use ($client, $actor): Client {
            $previous = $client->status;

            $client->update(['status' => ClientStatus::Inactive]);
            $client->delete();

            ClientHistory::query()->create([
                'client_id' => $client->id,
                'field' => ClientHistoryField::Archived,
                'old_value' => ['archived' => false, 'status' => $previous->value],
                'new_value' => ['archived' => true, 'status' => ClientStatus::Inactive->value],
                'author_id' => $actor->id,
            ]);

            return $client;
        });
    }
}
