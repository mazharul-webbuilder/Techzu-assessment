<?php

namespace App\Console\Commands;

use App\Services\Common\SlaEscalationService;
use Illuminate\Console\Command;

class CheckSlaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:check-sla';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan active tickets for SLA breach escalations and early warning alerts';

    /**
     * Execute the console command.
     */
    public function handle(SlaEscalationService $service): int
    {
        $this->info('Scanning active tickets for SLA breaches and warning thresholds...');

        $results = $service->checkAndEscalate();

        $this->info("Completed SLA scan: {$results['breaches']} escalated breach(es), {$results['warnings']} warning alert(s) sent.");

        return self::SUCCESS;
    }
}
