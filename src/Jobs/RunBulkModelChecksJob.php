<?php

namespace Vvb13a\LaravelModelChecker\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Vvb13a\LaravelModelChecker\Contracts\CheckableInterface;
use Vvb13a\LaravelModelChecker\Traits\Checkable;
use Vvb13a\LaravelResponseChecker\Enums\FindingLevel;

class RunBulkModelChecksJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param  string  $modelClass  The fully qualified class name of the models to check.
     */
    public function __construct(public string $modelClass, public ?FindingLevel $level = null)
    {
        $this->onConnection(Config::get('model-checker.queue_connection'));
        $this->onQueue(Config::get('model-checker.queue_name'));
    }

    /**
     * Get the unique ID for the job.
     * Prevents duplicate jobs for the exact same class and level filter.
     */
    public function uniqueId(): string
    {
        return $this->modelClass.':'.($this->level?->value ?? 'all');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        if (!class_exists($this->modelClass)) {
            throw new ModelNotFoundException("Model class [{$this->modelClass}] not found or could not be loaded.");
        }

        if (!is_subclass_of($this->modelClass, Model::class)) {
            throw new InvalidArgumentException("Class [{$this->modelClass}] is not a subclass of ".Model::class.".");
        }

        $classImplements = class_implements($this->modelClass);
        if (!isset($classImplements[CheckableInterface::class])) {
            throw new InvalidArgumentException("Model class [{$this->modelClass}] does not implement ".CheckableInterface::class.".");
        }

        $classUses = class_uses_recursive($this->modelClass);
        if (!isset($classUses[Checkable::class])) {
            throw new InvalidArgumentException("Model class [{$this->modelClass}] does not use the ".Checkable::class." trait.");
        }

        $baseQuery = $this->modelClass::query();

        if ($this->level instanceof FindingLevel) {
            $baseQuery->whereHas('findingsSummary', function ($query) {
                $query->where('status', $this->level);
            });
        }

        foreach ($baseQuery->cursor() as $model) {
            RunModelChecksJob::dispatch($model->getKey(), $this->modelClass);
        }
    }
}
