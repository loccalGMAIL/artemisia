<?php

namespace App\Actions;

use App\Enums\BudgetStatus;
use App\Models\Budget;
use App\Models\User;
use App\Services\BudgetHeaderValidator;
use Illuminate\Validation\ValidationException;

class CreateBudgetAction
{
    public function __construct(private readonly BudgetHeaderValidator $validator) {}

    /**
     * Creates a draft budget for an existing client (RF-11, RF-12). Its id is the correlative
     * identifier (RF-13) and the totals start at zero until items are added.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data, User $author): Budget
    {
        $values = $this->validator->validate($data);

        return Budget::query()->create([
            ...$values,
            'status' => BudgetStatus::Draft,
            'created_by' => $author->id,
        ]);
    }
}
