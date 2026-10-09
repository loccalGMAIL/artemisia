<?php

namespace App\Services;

use App\Enums\BudgetModality;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Validates the header data of a budget (RF-14 to RF-19), shared by creation and edition.
 * Every problem is reported together, so a form shows all of them at once.
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
        $validator = Validator::make($data, [
            'client_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'modality' => ['required', Rule::enum(BudgetModality::class)],
            'issue_date' => ['nullable', 'date'],
            'validity_date' => ['required', 'date'],
        ], [
            'client_id.required' => __('budgets.validation.client_required'),
            'client_id.integer' => __('budgets.validation.client_missing'),
        ]);

        $validator->after(function ($validator) use ($data): void {
            $errors = $validator->errors();

            // Archived and inactive clients stay valid recipients (RF-20): only existence matters.
            if (! $errors->has('client_id') && ! Client::withTrashed()->whereKey($data['client_id'])->exists()) {
                $validator->errors()->add('client_id', __('budgets.validation.client_missing'));
            }

            if ($errors->has('issue_date') || $errors->has('validity_date')) {
                return;
            }

            if (! $this->validityFollowsIssue($data)) {
                $validator->errors()->add('validity_date', __('budgets.validation.validity_after_issue'));
            }
        });

        $validated = $validator->validate();

        return [
            'client_id' => (int) $validated['client_id'],
            'title' => trim($validated['title']),
            'modality' => BudgetModality::from($validated['modality']),
            'issue_date' => $this->issueDate($data)->toDateString(),
            'validity_date' => CarbonImmutable::parse($validated['validity_date'])->startOfDay()->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validityFollowsIssue(array $data): bool
    {
        try {
            $validity = CarbonImmutable::parse($data['validity_date'])->startOfDay();

            return $validity->greaterThan($this->issueDate($data));
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function issueDate(array $data): CarbonImmutable
    {
        return filled($data['issue_date'] ?? null)
            ? CarbonImmutable::parse($data['issue_date'])->startOfDay()
            : CarbonImmutable::today();
    }
}
