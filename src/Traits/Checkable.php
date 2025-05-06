<?php

namespace Vvb13a\LaravelModelChecker\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use LogicException;
use Vvb13a\LaravelModelChecker\Jobs\RunModelChecksJob;
use Vvb13a\LaravelModelChecker\Models\Finding;
use Vvb13a\LaravelModelChecker\Models\Summary;
use Vvb13a\LaravelResponseChecker\Enums\FindingLevel;

trait Checkable
{
    public function getLastCheckedAt(): ?Carbon
    {
        return $this->findingsSummary?->updated_at;
    }

    public function hasCheckErrors(): bool
    {
        return $this->getFindingsStatus() === FindingLevel::ERROR;
    }

    public function getFindingsStatus(): ?FindingLevel
    {
        return $this->findingsSummary?->status;
    }

    public function hasCheckWarnings(): bool
    {
        return $this->getFindingsStatus() === FindingLevel::WARNING;
    }

    public function queueChecks(): void
    {
        if ($this->getKey() === null) {
            throw new LogicException("Cannot queue checks for a model that has not been persisted yet (missing ID).");
        }

        RunModelChecksJob::dispatch(
            modelId: $this->getKey(),
            modelClass: static::class
        );
    }

    /**
     * Initialize the trait for a model instance.
     * Registers a listener to delete related findings and summary when the model is permanently deleted.
     */
    protected function initializeCheckable(): void
    {
        static::deleting(function ($model) {
            $isForceDeleting = method_exists($model, 'isForceDeleting') && $model->isForceDeleting();
            $usesSoftDeletes = method_exists($model, 'runSoftDelete');

            if ($isForceDeleting || !$usesSoftDeletes) {
                $model->findings()->delete();
                $model->findingsSummary()->delete();
            }
        });
    }

    public function findings(): MorphMany
    {
        return $this->morphMany(Finding::class, 'checkable');
    }

    public function findingsSummary(): MorphOne
    {
        return $this->morphOne(Summary::class, 'checkable');
    }
}
