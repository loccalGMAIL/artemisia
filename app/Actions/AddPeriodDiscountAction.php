<?php

namespace App\Actions;

use App\Enums\ConceptType;
use App\Exceptions\PeriodLimitExceededException;
use App\Exceptions\PeriodTotalException;
use App\Models\ContractPeriod;
use App\Models\PeriodConcept;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AddPeriodDiscountAction extends AddsPeriodConcept
{
    /**
     * @param  array<string, mixed>  $data  `description` (the justification) and `amount`
     *
     * @throws ValidationException
     * @throws PeriodLimitExceededException
     * @throws PeriodTotalException
     */
    public function handle(ContractPeriod $period, array $data, User $actor): PeriodConcept
    {
        return $this->add($period, ConceptType::Discount, $data, $actor);
    }
}
