<?php

namespace App\Actions;

use App\Enums\PeriodHistoryField;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VoidPaymentAction extends ChangesPeriod
{
    /**
     * Voids a valid payment with a justification (RF-75, RF-76): it stops counting towards what
     * has been paid and is kept with its justification, its author and its date (RF-77, RF-68).
     * A void is final, a voided payment is not reactivated or voided again (RF-78), and the
     * fact is recorded in the history of the period (RF-79). The payment is read again with
     * the period locked, so it is decided on how it is when confirmed (plan D-8).
     *
     * @throws ValidationException
     */
    public function handle(Payment $payment, ?string $justification, User $actor): Payment
    {
        $validated = Validator::make(
            ['void_justification' => $justification === null ? '' : trim($justification)],
            ['void_justification' => ['required', 'string', 'max:255']],
            [
                'void_justification.required' => __('contracts.validation.void_justification_required'),
                'void_justification.max' => __('contracts.validation.void_justification_too_long', ['max' => 255]),
            ],
        )->validate();

        return $this->changing($payment->contractPeriod, function ($period) use ($payment, $validated, $actor): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw ValidationException::withMessages(['payment' => __('contracts.validation.payment_already_voided')]);
            }

            $before = $locked->snapshot();

            $locked->forceFill([
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_justification' => $validated['void_justification'],
            ])->save();

            $this->record($period, PeriodHistoryField::PaymentVoided, $before, [
                'id' => $locked->id,
                'justification' => $validated['void_justification'],
            ], $actor);

            return $locked;
        });
    }
}
