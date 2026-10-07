<?php

namespace App\Actions;

use App\Enums\ClientHistoryField;
use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\ClientHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Moves a client between active and inactive, recording the change (RF-25, RF-36).
 */
abstract class ChangeClientStatusAction
{
    abstract protected function target(): ClientStatus;

    public function handle(Client $client, User $actor): Client
    {
        $target = $this->target();

        if ($client->status === $target) {
            return $client;
        }

        return DB::transaction(function () use ($client, $target, $actor): Client {
            $previous = $client->status;

            $client->update(['status' => $target]);

            ClientHistory::query()->create([
                'client_id' => $client->id,
                'field' => ClientHistoryField::Status,
                'old_value' => ['status' => $previous->value],
                'new_value' => ['status' => $target->value],
                'author_id' => $actor->id,
            ]);

            return $client;
        });
    }
}
