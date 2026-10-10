<?php

namespace App\Actions;

use App\Exceptions\BudgetNotAcceptedException;
use App\Models\Budget;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateLoosePieceAction extends CreatesPiece
{
    /**
     * Creates a pending piece with no source item in an accepted budget. The work category is
     * mandatory because there is no service to inherit it from, and the due date is optional
     * (RF-5, RF-9, RF-10, RF-16).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws BudgetNotAcceptedException
     * @throws ValidationException
     */
    public function handle(Budget $budget, array $data, User $author): Piece
    {
        $budget->assertAccepted();

        $values = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'work_category_id' => ['required', 'integer', 'exists:work_categories,id'],
            'due_date' => ['nullable', 'date'],
        ], [
            'work_category_id.required' => __('pieces.validation.category_required'),
            'work_category_id.integer' => __('pieces.validation.category_required'),
            'work_category_id.exists' => __('pieces.validation.category_missing'),
        ])->validate();

        return DB::transaction(fn (): Piece => $this->storePiece($budget, [
            ...$values,
            'name' => trim($values['name']),
            'budget_item_id' => null,
        ], $author));
    }
}
