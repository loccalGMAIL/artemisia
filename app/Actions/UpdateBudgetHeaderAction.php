<?php

namespace App\Actions;

use App\Enums\BudgetHistoryField;
use App\Exceptions\BudgetNotEditableException;
use App\Models\Budget;
use App\Models\BudgetHistory;
use App\Models\User;
use App\Services\BudgetHeaderValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateBudgetHeaderAction
{
    public function __construct(private readonly BudgetHeaderValidator $validator) {}

    /**
     * Edits the header of a draft or sent budget (RF-16) and records what changed (RF-33).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws BudgetNotEditableException
     * @throws ValidationException
     */
    public function handle(Budget $budget, array $data, User $actor): Budget
    {
        $budget->assertEditable();

        // An edit that does not mention the issue date keeps the current one.
        $data['issue_date'] ??= $budget->issue_date->toDateString();

        $values = $this->validator->validate($data);

        $before = $budget->headerSnapshot();

        $budget->fill($values);

        $after = $budget->headerSnapshot();
        $changed = array_keys(array_diff_assoc($after, $before));

        if ($changed === []) {
            return $budget;
        }

        return DB::transaction(function () use ($budget, $before, $after, $changed, $actor): Budget {
            $budget->save();

            BudgetHistory::query()->create([
                'budget_id' => $budget->id,
                'field' => BudgetHistoryField::HeaderChanged,
                'old_value' => array_intersect_key($before, array_flip($changed)),
                'new_value' => array_intersect_key($after, array_flip($changed)),
                'author_id' => $actor->id,
            ]);

            return $budget;
        });
    }
}
