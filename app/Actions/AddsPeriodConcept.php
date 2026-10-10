<?php

namespace App\Actions;

use App\Enums\ConceptType;
use App\Enums\PeriodHistoryField;
use App\Exceptions\PeriodLimitExceededException;
use App\Exceptions\PeriodTotalException;
use App\Models\ContractPeriod;
use App\Models\PeriodConcept;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Common flow of adding an extra or a discount to a period: the description (the justification
 * of a discount) and an amount above zero are required (RF-42, RF-43, RF-44), a period holds at
 * most 100 concepts (RF-46), the total is recalculated (RF-49) and the addition is recorded
 * (RF-52).
 */
abstract class AddsPeriodConcept extends ChangesPeriod
{
    public const MAX_CONCEPTS = 100;

    /**
     * @param  array<string, mixed>  $data  `description` and `amount`
     *
     * @throws ValidationException
     * @throws PeriodLimitExceededException
     * @throws PeriodTotalException
     */
    protected function add(ContractPeriod $period, ConceptType $type, array $data, User $actor): PeriodConcept
    {
        $description = is_string($data['description'] ?? null) ? trim($data['description']) : '';

        $validated = Validator::make(
            ['description' => $description, 'amount' => $data['amount'] ?? null],
            [
                'description' => ['required', 'string', 'max:255'],
                'amount' => ['required', 'numeric', 'max:99999999.99'],
            ],
            [
                'description.required' => __('contracts.validation.'.$type->value.'_description_required'),
                'description.max' => __('contracts.validation.concept_description_too_long', ['max' => 255]),
                'amount.*' => __('contracts.validation.concept_amount_invalid'),
            ],
        )->validate();

        $cents = Money::toCents($validated['amount']);

        if ($cents < 1) {
            throw ValidationException::withMessages(['amount' => __('contracts.validation.concept_amount_invalid')]);
        }

        return $this->changing($period, function (ContractPeriod $locked) use ($type, $description, $cents, $actor): PeriodConcept {
            if ($locked->concepts()->count() >= self::MAX_CONCEPTS) {
                throw PeriodLimitExceededException::concepts(self::MAX_CONCEPTS);
            }

            $concept = $locked->concepts()->create([
                'type' => $type,
                'description' => $description,
                'amount' => Money::fromCents($cents),
                'created_by' => $actor->id,
            ]);

            $this->record($locked, PeriodHistoryField::ConceptAdded, null, $concept->refresh()->snapshot(), $actor);

            return $concept;
        });
    }
}
