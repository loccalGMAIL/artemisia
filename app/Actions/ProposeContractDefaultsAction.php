<?php

namespace App\Actions;

use App\Models\Client;

class ProposeContractDefaultsAction
{
    /**
     * Proposes the due day and the term of the client's contract with the latest start date,
     * whatever its status (RF-13), and a start date only when that contract has a fixed term:
     * the month after its last one (RF-14, RF-15). The monthly amount is never proposed: it
     * always comes from the budget (RF-17). Nothing is stored.
     *
     * @return array{due_day: int|null, term_months: int|null, indefinite: bool, start_date: string|null}
     */
    public function handle(Client $client): array
    {
        $previous = $client->contracts()
            ->withTrashed()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if ($previous === null) {
            return ['due_day' => null, 'term_months' => null, 'indefinite' => false, 'start_date' => null];
        }

        return [
            'due_day' => $previous->due_day,
            'term_months' => $previous->term_months,
            'indefinite' => $previous->isIndefinite(),
            'start_date' => $previous->term_end_month?->addMonth()->toDateString(),
        ];
    }
}
