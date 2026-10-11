<?php

namespace App\Actions;

use App\Enums\ConceptType;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\ContractPeriod;
use App\Models\Payment;
use App\Models\PeriodConcept;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;

class BuildClientStatementAction
{
    /**
     * The read-only account statement of the client an account is linked to, for the portal
     * (RF-93 to RF-96): its contracts, newest first, each with its periods, newest first, with
     * the fee, the extras, the discounts with their justification, the total, what has been
     * paid, the balance, the payment status and the due date, and the valid payments only (RF-96).
     *
     * Everything is loaded at once, so the number of queries does not grow with the contracts or
     * the periods, and each contract goes through the `portal.contracts.view` ability, which only
     * lets a client account see the contracts of its own client (RF-97). An account without a
     * client, or that is not a client account, gets nothing.
     *
     * @return array<int, array{
     *     id: int,
     *     status: ContractStatus,
     *     monthly_amount: string,
     *     due_day: int,
     *     start_date: CarbonInterface,
     *     term_months: int|null,
     *     term_end_month: CarbonInterface|null,
     *     periods: array<int, array<string, mixed>>
     * }>
     */
    public function handle(User $account): array
    {
        if ($account->client_id === null) {
            return [];
        }

        $contracts = Contract::query()
            ->where('client_id', $account->client_id)
            ->with([
                'periods' => fn (Relation $periods) => $periods->reorder()->orderByDesc('month'),
                'periods.concepts',
                'periods.payments' => fn (Relation $payments) => $payments->whereNull('voided_at'),
            ])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return $contracts
            ->filter(fn (Contract $contract): bool => Gate::forUser($account)->allows('portal.contracts.view', $contract))
            ->map(fn (Contract $contract): array => [
                'id' => $contract->id,
                'status' => $contract->status,
                'monthly_amount' => $contract->monthly_amount,
                'due_day' => $contract->due_day,
                'start_date' => $contract->start_date,
                'term_months' => $contract->term_months,
                'term_end_month' => $contract->term_end_month,
                'periods' => $contract->periods->map(fn (ContractPeriod $period): array => $this->period($period))->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function period(ContractPeriod $period): array
    {
        return [
            'id' => $period->id,
            'month' => $period->month,
            'due_date' => $period->due_date,
            'fee' => $period->fee,
            'extras' => $this->concepts($period, ConceptType::Extra),
            'discounts' => $this->concepts($period, ConceptType::Discount),
            'total' => $period->total,
            'paid_amount' => $period->paid_amount,
            'balance' => $period->balance,
            'payment_status' => $period->payment_status,
            'overdue' => $period->isOverdue(),
            'payments' => $period->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'paid_on' => $payment->paid_on,
                'amount' => $payment->amount,
                'method' => $payment->method,
                'reference' => $payment->reference,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<int, array{description: string, amount: string}>
     */
    private function concepts(ContractPeriod $period, ConceptType $type): array
    {
        return $period->concepts
            ->filter(fn (PeriodConcept $concept): bool => $concept->type === $type)
            ->map(fn (PeriodConcept $concept): array => ['description' => $concept->description, 'amount' => $concept->amount])
            ->values()
            ->all();
    }
}
