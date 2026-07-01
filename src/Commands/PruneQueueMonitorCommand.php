<?php

namespace Croustibat\FilamentJobsMonitor\Commands;

use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Illuminate\Console\Command;

class PruneQueueMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'filament-jobs-monitor:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune the filament-jobs-monitor records using the configured retention.';

    /**
     * Execute the console command.
     *
     * Laravel's built-in `model:prune` command only auto-discovers prunable
     * models living in the application's `app/Models` directory, so the
     * package's QueueMonitor model is never reached unless an explicit
     * `--model=` flag is passed. This dedicated command targets the package
     * model directly so pruning works out of the box for a package install.
     */
    public function handle(): int
    {
        $this->components->info('Pruning '.QueueMonitor::class);

        $total = $this->getModel()->pruneAll();

        if ($total === 0) {
            $this->components->info('No '.QueueMonitor::class.' records to prune.');
        } else {
            $this->components->info($total.' '.QueueMonitor::class.' records pruned.');
        }

        return self::SUCCESS;
    }

    /**
     * Resolve the prunable model instance.
     */
    protected function getModel(): QueueMonitor
    {
        return new QueueMonitor;
    }
}
