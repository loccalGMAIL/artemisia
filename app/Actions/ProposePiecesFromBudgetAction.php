<?php

namespace App\Actions;

use App\Enums\BudgetStatus;
use App\Exceptions\BudgetNotAcceptedException;
use App\Models\Budget;

class ProposePiecesFromBudgetAction
{
    /**
     * Proposes one piece per item of an accepted budget, with the item name, quantity and the
     * work category of its service (RF-1, RF-10). Nothing is stored: the staff edits the
     * proposal and only confirming it creates pieces (RF-2, plan D-2).
     *
     * @return list<array{budget_item_id: int, name: string, quantity: int, work_category_id: int}>
     *
     * @throws BudgetNotAcceptedException
     */
    public function handle(Budget $budget): array
    {
        if ($budget->status !== BudgetStatus::Accepted) {
            throw new BudgetNotAcceptedException;
        }

        return $budget->items()
            ->with('service:id,work_category_id')
            ->orderBy('id')
            ->get()
            ->map(fn ($item): array => [
                'budget_item_id' => $item->id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'work_category_id' => $item->service->work_category_id,
            ])
            ->all();
    }
}
