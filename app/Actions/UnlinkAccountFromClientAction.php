<?php

namespace App\Actions;

use App\Enums\ClientHistoryField;
use App\Models\ClientHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnlinkAccountFromClientAction
{
    /**
     * Unlinks a portal account from its client. An open session of that account is cut on its
     * next request by EnsureAccountIsActive, which notices the link changed (RF-42).
     */
    public function handle(User $account, User $actor): void
    {
        $clientId = $account->client_id;

        if ($clientId === null) {
            return;
        }

        DB::transaction(function () use ($account, $clientId, $actor): void {
            $account->update(['client_id' => null]);

            ClientHistory::query()->create([
                'client_id' => $clientId,
                'field' => ClientHistoryField::AccountLink,
                'old_value' => ['account_id' => $account->id, 'account_email' => $account->email, 'client_id' => $clientId],
                'new_value' => ['account_id' => $account->id, 'account_email' => $account->email, 'client_id' => null],
                'author_id' => $actor->id,
            ]);
        });
    }
}
