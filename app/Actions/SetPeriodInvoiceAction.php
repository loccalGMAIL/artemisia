<?php

namespace App\Actions;

use App\Enums\PeriodHistoryField;
use App\Models\ContractPeriod;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SetPeriodInvoiceAction extends ChangesPeriod
{
    /**
     * Loads, changes or clears the invoice reference of a period: number and date go together
     * or not at all (RF-53, RF-54, RF-55). The same number may be on more than one period
     * (RF-56). A change records the previous and the new value (RF-57); saving what is already
     * there records nothing.
     *
     * @throws ValidationException
     */
    public function handle(ContractPeriod $period, ?string $number, mixed $date, User $actor): ContractPeriod
    {
        $number = $number === null ? '' : trim($number);
        $date = $this->normalizeDate($date);

        if ($number === '' && $date === '') {
            $number = $date = null;
        } else {
            $validated = Validator::make(
                ['invoice_number' => $number, 'invoice_date' => $date],
                [
                    'invoice_number' => ['required', 'string', 'max:50'],
                    'invoice_date' => ['required', 'date_format:Y-m-d'],
                ],
                [
                    'invoice_number.required' => __('contracts.validation.invoice_number_required'),
                    'invoice_number.max' => __('contracts.validation.invoice_number_too_long', ['max' => 50]),
                    'invoice_date.required' => __('contracts.validation.invoice_date_required'),
                    'invoice_date.date_format' => __('contracts.validation.invoice_date_invalid'),
                ],
            )->validate();

            $number = $validated['invoice_number'];
            $date = $validated['invoice_date'];
        }

        return $this->changing($period, function (ContractPeriod $locked) use ($number, $date, $actor): ContractPeriod {
            $old = ['number' => $locked->invoice_number, 'date' => $locked->invoice_date?->toDateString()];
            $new = ['number' => $number, 'date' => $date];

            if ($old === $new) {
                return $locked;
            }

            $locked->forceFill(['invoice_number' => $number, 'invoice_date' => $date])->save();

            $this->record($locked, PeriodHistoryField::InvoiceChanged, $old, $new, $actor);

            return $locked;
        });
    }

    private function normalizeDate(mixed $date): string
    {
        if ($date instanceof CarbonInterface) {
            return $date->toDateString();
        }

        return is_string($date) ? trim($date) : '';
    }
}
