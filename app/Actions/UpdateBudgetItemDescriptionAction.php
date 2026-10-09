<?php

namespace App\Actions;

use App\Models\BudgetItem;
use App\Models\User;

class UpdateBudgetItemDescriptionAction extends ModifiesBudgetItem
{
    /** Changes the description of an item; it may be left empty (RF-27). */
    public function handle(BudgetItem $item, ?string $description, User $actor): BudgetItem
    {
        $description = $description === null ? null : trim($description);

        return $this->apply($item, ['description' => $description === '' ? null : $description], $actor);
    }
}
