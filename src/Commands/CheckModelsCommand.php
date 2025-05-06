<?php

namespace Vvb13a\LaravelModelChecker\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Vvb13a\LaravelModelChecker\Jobs\RunBulkModelChecksJob;
use Vvb13a\LaravelModelChecker\Traits\Checkable;

class CheckModelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'response-check:models
                            {modelClass : The fully qualified class name of the Eloquent model to check (e.g., App\\\\Models\\\\News)}
                            {--now : Dispatch the bulk job synchronously (for testing/debugging)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch jobs to check all checkable instances of a given model class.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $modelClass = $this->argument('modelClass');

        // Basic validation
        if (!class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
            $this->error("Invalid Eloquent model class provided: {$modelClass}");
            return Command::FAILURE;
        }

        if (!in_array(Checkable::class, class_uses_recursive($modelClass))) {
            $this->warn("Warning: The model class {$modelClass} does not directly use the Checkable trait. Checks will only be dispatched for instances if they inherit the trait correctly.");
        }

        if ($this->option('now')) {
            $this->info("Dispatching bulk checks synchronously for {$modelClass}...");
            RunBulkModelChecksJob::dispatchSync($modelClass);
            $this->info("Synchronous bulk check dispatch finished for {$modelClass}.");
        } else {
            $this->info("Dispatching bulk checks job for {$modelClass} to the queue...");
            RunBulkModelChecksJob::dispatch($modelClass);
            $this->info("Bulk check job dispatched successfully.");
        }

        return Command::SUCCESS;
    }
}
