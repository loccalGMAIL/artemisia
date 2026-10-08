<?php

namespace App\Services;

use App\Enums\BudgetModality;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates the header data of a budget (RF-14 to RF-19), shared by creation and edition.
 */
class BudgetHeaderValidator
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{client_id: int, title: string, modality: BudgetModality, issue_date: string, validity_date: string}
     *
     * @throws ValidationException
     */
    public function validate(array $data): array
    {
        $validated = Validator::make($data, [
            'client_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'modality' => ['required', Rule::enum(BudgetModality::class)],
            'issue_date' => ['nullable', 'date'],
            'validity_date' => ['required', 'date'],
        ], [
            'client_id.required' => __('budgets.validation.client_required'),
            'client_id.integer' => __('budgets.validation.client_missing'),
        ])->validate();

        // Archived and inactive clients stay valid recipients (RF-20): only existence matters.
        if (! Client::withTrashed()->whereKey($validated['client_id'])->exists()) {
            throw ValidationException::withMessages(['client_id' => __('budgets.validation.client_missing')]);
        }

        $issueDate = isset($validated['issue_date'])
            ? CarbonImmutable::parse($validated['issue_date'])->startOfDay()
            : CarbonImmutable::today();
        $validityDate = CarbonImmutable::parse($validated['validity_date'])->startOfDay();

        if (! $validityDate->greaterThan($issueDate)) {
            throw ValidationException::withMessages(['validity_date' => __('budgets.validation.validity_after_issue')]);
        }

        return [
            'client_id' => (int) $validated['client_id'],
            'title' => trim($validated['title']),
            'modality' => BudgetModality::from($validated['modality']),
            'issue_date' => $issueDate->toDateString(),
            'validity_date' => $validityDate->toDateString(),
        ];
    }
}
