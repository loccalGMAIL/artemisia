<?php

namespace App\Actions;

use App\Enums\PaymentMethod;
use App\Enums\PeriodHistoryField;
use App\Exceptions\PaymentExceedsBalanceException;
use App\Exceptions\PeriodLimitExceededException;
use App\Models\ContractPeriod;
use App\Models\Payment;
use App\Models\User;
use App\Support\BusinessDate;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegisterPaymentAction extends ChangesPeriod
{
    public const MAX_VALID_PAYMENTS = 100;

    /**
     * Registers a payment on a period (RF-58): a date that is not in the future and may be as
     * old as needed (RF-66, RF-67), an amount above zero (RF-63), a way of paying (RF-59) and an
     * optional reference (RF-60). A period takes several payments (RF-61) up to 100 valid ones
     * (RF-62). The amount is checked against the balance as it is when the payment is
     * confirmed, with the period locked, not against the one the person saw when opening the
     * form (RF-64, RF-65, plan D-8). The contract and the client may be in any state (RF-24,
     * RF-35). The payment is recorded in the history of the period (RF-79).
     *
     * @param  array<string, mixed>  $data  `paid_on`, `amount`, `method` and optionally `reference`
     *
     * @throws ValidationException
     * @throws PeriodLimitExceededException
     * @throws PaymentExceedsBalanceException
     */
    public function handle(ContractPeriod $period, array $data, User $actor): Payment
    {
        $reference = is_string($data['reference'] ?? null) ? trim($data['reference']) : null;

        $validated = Validator::make(
            [
                'paid_on' => $this->normalizeDate($data['paid_on'] ?? null),
                'amount' => $data['amount'] ?? null,
                'method' => $data['method'] ?? null,
                'reference' => $reference === '' ? null : $reference,
            ],
            [
                'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.BusinessDate::today()->toDateString()],
                'amount' => ['required', 'numeric', 'max:9999999999.99'],
                'method' => ['required', Rule::enum(PaymentMethod::class)],
                'reference' => ['nullable', 'string', 'max:100'],
            ],
            [
                'paid_on.required' => __('contracts.validation.payment_date_invalid'),
                'paid_on.date_format' => __('contracts.validation.payment_date_invalid'),
                'paid_on.before_or_equal' => __('contracts.validation.payment_date_future'),
                'amount.*' => __('contracts.validation.payment_amount_invalid'),
                'method.*' => __('contracts.validation.payment_method_invalid'),
                'reference.max' => __('contracts.validation.payment_reference_too_long', ['max' => 100]),
            ],
        )->validate();

        $cents = Money::toCents($validated['amount']);

        if ($cents < 1) {
            throw ValidationException::withMessages(['amount' => __('contracts.validation.payment_amount_invalid')]);
        }

        return $this->changing($period, function (ContractPeriod $locked) use ($validated, $cents, $actor): Payment {
            if ($locked->payments()->valid()->count() >= self::MAX_VALID_PAYMENTS) {
                throw PeriodLimitExceededException::payments(self::MAX_VALID_PAYMENTS);
            }

            if ($cents > Money::toCents($locked->balance)) {
                throw new PaymentExceedsBalanceException($locked->balance);
            }

            $payment = $locked->payments()->create([
                'paid_on' => $validated['paid_on'],
                'amount' => Money::fromCents($cents),
                'method' => $validated['method'],
                'reference' => $validated['reference'],
                'created_by' => $actor->id,
            ]);

            $this->record($locked, PeriodHistoryField::PaymentRegistered, null, $payment->refresh()->snapshot(), $actor);

            return $payment;
        });
    }

    private function normalizeDate(mixed $date): ?string
    {
        if ($date instanceof CarbonInterface) {
            return $date->toDateString();
        }

        return is_string($date) ? trim($date) : null;
    }
}
