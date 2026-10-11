<?php

namespace App\Actions;

use App\Enums\PeriodHistoryField;
use App\Exceptions\PeriodTotalException;
use App\Models\PeriodConcept;
use App\Models\User;

class RemovePeriodConceptAction extends ChangesPeriod
{
    /**
     * Removes an extra or a discount from its period (RF-47). The row is deleted and its
     * snapshot stays in the history of the period (RF-48, RF-52, plan D-5). The total is
     * recalculated, and it is refused when it would fall below what has been paid (RF-51).
     *
     * @throws PeriodTotalException
     */
    public function handle(PeriodConcept $concept, User $actor): void
    {
        $this->changing($concept->contractPeriod, function ($period) use ($concept, $actor): void {
            $current = $period->concepts()->whereKey($concept->id)->firstOrFail();

            $snapshot = $current->snapshot();

            $current->delete();

            $this->record($period, PeriodHistoryField::ConceptRemoved, $snapshot, ['removed' => true], $actor);
        });
    }
}
