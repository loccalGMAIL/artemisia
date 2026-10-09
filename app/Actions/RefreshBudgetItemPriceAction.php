<?php

namespace App\Actions;

use App\Models\BudgetItem;
use App\Models\User;

class RefreshBudgetItemPriceAction extends ModifiesBudgetItem
{
    /**
     * Sets the copied price of one item to the current list price of its service (RF-28).
     * The price is never typed in: it can only come from the service (RF-29).
     */
    public function handle(BudgetItem $item, User $actor): BudgetItem
    {
        return $this->apply($item, ['unit_price' => $item->service->list_price], $actor);
    }
}
