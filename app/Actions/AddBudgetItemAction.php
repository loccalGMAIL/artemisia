<?php

namespace App\Actions;

use App\Enums\BudgetHistoryField;
use App\Exceptions\BudgetNotEditableException;
use App\Models\Budget;
use App\Models\BudgetHistory;
use App\Models\BudgetItem;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AddBudgetItemAction
{
    public const MAX_ITEMS = 100;

    public function __construct(private readonly RecalculateBudgetTotalsAction $recalculate) {}

    /**
     * Adds an item copying name, description and list price of an active service (RF-21,
     * RF-22, RF-29). The same service may appear more than once (RF-23).
     *
     * @throws BudgetNotEditableException
     * @throws ValidationException
     */
    public function handle(Budget $budget, Service $service, mixed $quantity, ?string $description, User $actor): BudgetItem
    {
        $budget->assertEditable();

        $validated = Validator::make(['quantity' => $quantity], [
            'quantity' => ['required', 'integer', 'min:1'],
        ])->validate();

        if (! $service->is_active) {
            throw ValidationException::withMessages(['service_id' => __('budgets.validation.service_inactive')]);
        }

        return DB::transaction(function () use ($budget, $service, $validated, $description, $actor): BudgetItem {
            if ($budget->items()->count() >= self::MAX_ITEMS) {
                throw ValidationException::withMessages([
                    'items' => __('budgets.validation.items_limit', ['max' => self::MAX_ITEMS]),
                ]);
            }

            $description = $description === null ? $service->description : trim($description);

            $item = $budget->items()->create([
                'service_id' => $service->id,
                'name' => $service->name,
                'description' => $description === '' ? null : $description,
                'unit_price' => $service->list_price,
                'quantity' => (int) $validated['quantity'],
            ]);

            $this->recalculate->handle($budget);

            BudgetHistory::query()->create([
                'budget_id' => $budget->id,
                'field' => BudgetHistoryField::ItemAdded,
                'old_value' => null,
                'new_value' => $item->refresh()->snapshot(),
                'author_id' => $actor->id,
            ]);

            return $item;
        });
    }
}
