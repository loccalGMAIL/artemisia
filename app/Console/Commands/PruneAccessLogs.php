<?php

namespace App\Console\Commands;

use App\Models\AccessLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Deletes access logs older than the retention period (RNF-5). It never touches
 * account_histories, which are kept without a time limit (RNF-6).
 */
#[Signature('access-logs:prune')]
#[Description('Delete access logs older than 24 months')]
class PruneAccessLogs extends Command
{
    public function handle(): int
    {
        $deleted = AccessLog::query()->expired()->delete();

        $this->info(__('logs.prune.done', ['count' => $deleted, 'months' => AccessLog::RETENTION_MONTHS]));

        return self::SUCCESS;
    }
}
