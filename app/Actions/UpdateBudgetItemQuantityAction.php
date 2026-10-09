<?php

namespace App\Actions;

use App\Models\BudgetItem;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateBudgetItemQuantityAction extends ModifiesBudgetItem
{
    /**
     * Changes the quantity of an item: an integer greater than zero (RF-24, RF-26).
     *
     * @throws ValidationException
     */
    public function handle(BudgetItem $item, mixed $quantity, User $actor): BudgetItem
    {
        $item->budget->assertEditable();

        $validated = Validator::make(['quantity' => $quantity], [
            'quantity' => ['required', 'integer', 'min:1'],
        ])->validate();

        return $this->apply($item, ['quantity' => (int) $validated['quantity']], $actor);
    }
}
