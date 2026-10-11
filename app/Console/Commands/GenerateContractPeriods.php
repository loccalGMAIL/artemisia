<?php

namespace App\Console\Commands;

use App\Actions\FinishExpiredContractsAction;
use App\Actions\GenerateContractPeriodsAction;
use App\Enums\ContractStatus;
use App\Models\Contract;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Daily run of the contracts (RF-29, RF-36): creates the periods each active contract is
 * missing, which also makes up for a day that did not run, and then finishes the fixed-term
 * contracts that have expired. A contract that fails is reported and does not stop the others
 * (plan D-9).
 */
#[Signature('contracts:generate-periods')]
#[Description('Generate the missing periods of the active contracts and finish the expired ones')]
class GenerateContractPeriods extends Command
{
    public function handle(GenerateContractPeriodsAction $generate, FinishExpiredContractsAction $finish): int
    {
        $created = 0;
        $failed = 0;

        Contract::query()
            ->where('status', ContractStatus::Active)
            ->orderBy('id')
            ->each(function (Contract $contract) use ($generate, &$created, &$failed): void {
                try {
                    $created += $generate->handle($contract)->count();
                } catch (Throwable $exception) {
                    $failed++;
                    report($exception);
                    $this->error(__('contracts.command.failed', ['id' => $contract->id, 'message' => $exception->getMessage()]));
                }
            });

        $finished = $finish->handle();

        $this->info(__('contracts.command.done', ['created' => $created, 'finished' => $finished]));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
