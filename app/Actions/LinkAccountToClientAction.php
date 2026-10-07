<?php

namespace App\Actions;

use App\Enums\ClientHistoryField;
use App\Models\Client;
use App\Models\ClientHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LinkAccountToClientAction
{
    /**
     * Links a portal account to a client. A client admits many accounts (RF-39), but an
     * account belongs to at most one client (RF-40).
     *
     * @throws ValidationException
     */
    public function handle(Client $client, User $account, User $actor): void
    {
        if (! $account->hasRole('client')) {
            throw ValidationException::withMessages([
                'account' => __('clients.validation.account_not_client'),
            ]);
        }

        if ($account->client_id === $client->id) {
            return;
        }

        if ($account->client_id !== null) {
            $other = Client::withTrashed()->find($account->client_id);

            throw ValidationException::withMessages([
                'account' => __('clients.validation.account_already_linked', ['name' => $other?->display_name]),
            ]);
        }

        DB::transaction(function () use ($client, $account, $actor): void {
            $account->update(['client_id' => $client->id]);

            ClientHistory::query()->create([
                'client_id' => $client->id,
                'field' => ClientHistoryField::AccountLink,
                'old_value' => ['account_id' => $account->id, 'account_email' => $account->email, 'client_id' => null],
                'new_value' => ['account_id' => $account->id, 'account_email' => $account->email, 'client_id' => $client->id],
                'author_id' => $actor->id,
            ]);
        });
    }
}
