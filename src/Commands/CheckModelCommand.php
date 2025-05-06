<?php

namespace Vvb13a\LaravelModelChecker\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Vvb13a\LaravelModelChecker\Jobs\RunModelChecksJob;
use Vvb13a\LaravelModelChecker\Traits\Checkable;

class CheckModelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'response-check:model
                            {modelClass : The fully qualified class name (e.g., App\\\\Models\\\\News)}
                            {modelId : The ID of the model instance to check}
                            {--now : Dispatch the job synchronously (for testing/debugging)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch a job to check a single model instance.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $modelClass = $this->argument('modelClass');
        $modelId = $this->argument('modelId');

        if (!class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
            $this->error("Invalid Eloquent model class provided: {$modelClass}");
            return Command::FAILURE;
        }
        if (!ctype_digit((string) $modelId) || $modelId < 1) {
            $this->error("Invalid model ID provided: {$modelId}");
            return Command::FAILURE;
        }

        $model = $modelClass::find($modelId);
        if (!$model) {
            $this->error("Model {$modelClass} with ID {$modelId} not found.");
            return Command::FAILURE;
        }
        if (!in_array(Checkable::class, class_uses_recursive($model))) {
            $this->error("Model {$modelClass} with ID {$modelId} does not use the Checkable trait.");
            return Command::FAILURE;
        }

        if ($this->option('now')) {
            $this->info("Dispatching check synchronously for {$modelClass} ID {$modelId}...");
            RunModelChecksJob::dispatchSync($modelId, $modelClass); // Use dispatchSync
            $this->info("Synchronous check finished for {$modelClass} ID {$modelId}.");
        } else {
            $this->info("Dispatching check job for {$modelClass} ID {$modelId} to the queue...");
            RunModelChecksJob::dispatch($modelId, $modelClass);
            $this->info("Check job dispatched successfully.");
        }

        return Command::SUCCESS;
    }
}
