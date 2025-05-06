<?php

namespace Vvb13a\LaravelModelChecker\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use Vvb13a\LaravelModelChecker\Contracts\CheckableInterface;
use Vvb13a\LaravelModelChecker\Exceptions\MalformedConfigException;
use Vvb13a\LaravelModelChecker\Models\Summary;
use Vvb13a\LaravelModelChecker\Traits\Checkable;
use Vvb13a\LaravelResponseChecker\Concerns\ProvidesConfigurationDetails;
use Vvb13a\LaravelResponseChecker\Contracts\CheckInterface;
use Vvb13a\LaravelResponseChecker\DTOs\Finding as FindingDTO;
use Vvb13a\LaravelResponseChecker\Enums\FindingLevel;
use Vvb13a\LaravelResponseChecker\Exceptions\InvalidReturnedCheckFindingException;

class RunModelChecksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param  int  $modelId  The ID of the checkable model.
     * @param  string  $modelClass  The fully qualified class name of the checkable model.
     */
    public function __construct(
        public int $modelId,
        public string $modelClass
    ) {
        $this->onConnection(Config::get('model-checker.queue_connection'));
        $this->onQueue(Config::get('model-checker.queue_name', 'default'));
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws RuntimeException If the model is not configured correctly or other runtime errors occur.
     * @throws Throwable For any other unhandled exceptions during execution (will cause job failure).
     * @throws MalformedConfigException If the package configuration is invalid.
     * @throws ModelNotFoundException If the model cannot be found.
     */
    public function handle(): void
    {
        $generatedFindings = new Collection();

        try {
            /** @var CheckableInterface&Model $model */
            $model = $this->getModelInstance();

            $model->findings()->delete();

            $urls = $model->getCheckableUrls();

            if (empty($urls)) {
                $model->findingsSummary()->delete();
                return;
            }

            $checkClasses = Config::get('model-checker.checks', []);
            if (empty($checkClasses)) {
                throw new MalformedConfigException(
                    "Configuration error: No checks defined in 'model-checker.checks' config file."
                );
            }

            $storeSuccessFindings = Config::get('model-checker.store_success_findings', false);
            $httpOptions = Config::get('model-checker.http_options', ['timeout' => 15]);

            foreach ($urls as $url) {
                $findingsFromUrl = $this->checkUrl($model, $url, $checkClasses, $storeSuccessFindings,
                    $httpOptions);
                $generatedFindings = $generatedFindings->merge($findingsFromUrl);
            }

            $this->calculateAndUpdateStatus($model, $generatedFindings);

        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Retrieve and validate the model instance.
     *
     * @throws ModelNotFoundException If the model record is not found.
     * @throws RuntimeException If the model does not implement the required interface or trait.
     * @throws Throwable If any other error occurs during retrieval.
     */
    protected function getModelInstance(): ?Model
    {
        try {
            /** @var (CheckableInterface&Model)|null $model */
            $model = $this->modelClass::find($this->modelId);

            if (!$model) {
                throw new ModelNotFoundException("Model [{$this->modelClass}] with ID [{$this->modelId}] not found.");
            }

            if (!($model instanceof CheckableInterface)) {
                throw new RuntimeException("Model [{$this->modelClass}] ID [{$this->modelId}] does not implement CheckableInterface.");
            }

            if (!in_array(Checkable::class, class_uses_recursive($model), true)) {
                throw new RuntimeException("Model [{$this->modelClass}] ID [{$this->modelId}] does not use Checkable trait (required for status).");
            }

            return $model;

        } catch (ModelNotFoundException|RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException("Error retrieving model {$this->modelClass} ID {$this->modelId}: ".$e->getMessage(),
                0, $e);
        }
    }

    /**
     * Perform checks for a single URL, save findings, and return the generated Finding DTOs.
     *
     * @return Collection<int, FindingDTO> Collection of DTOs generated for this URL.
     * @throws InvalidReturnedCheckFindingException If a check returns data in an unexpected format.
     * @throws Throwable If saving a finding fails (e.g., DB error).
     */
    protected function checkUrl(
        CheckableInterface&Model $model,
        string $url,
        array $checkClasses,
        bool $storeSuccessFindings,
        array $httpOptions
    ): Collection {
        $findingsToReturn = new Collection();

        try {
            $response = Http::withOptions($httpOptions)->get($url);

        } catch (Throwable $e) {
            $dto = FindingDTO::error(
                message: "HTTP request failed: ".$e->getMessage(),
                checkName: 'HttpRequest',
                url: $url,
                details: ['exception' => get_class($e)]
            );
            $this->saveFinding($model, $dto);
            $findingsToReturn->push($dto);
            return $findingsToReturn;
        }

        foreach ($checkClasses as $checkClass) {
            try {
                /** @var CheckInterface $checkInstance */
                $checkInstance = App::make($checkClass);

                $findingsFromCheck = $checkInstance->check($url, $response);

                if (!is_iterable($findingsFromCheck)) {
                    throw new InvalidReturnedCheckFindingException(
                        checkClassName: $checkClass,
                        invalidData: $findingsFromCheck,
                        message: sprintf(
                            "Check '%s' failed: The check() method must return an iterable (like array or Collection), but returned type '%s'.",
                            $checkClass,
                            get_debug_type($findingsFromCheck)
                        )
                    );
                }

                foreach ($findingsFromCheck as $findingDto) {
                    if (!($findingDto instanceof FindingDTO)) {
                        throw new InvalidReturnedCheckFindingException(
                            checkClassName: $checkClass,
                            invalidData: $findingDto,
                            message: sprintf(
                                "Check '%s' failed: Expected items in the returned iterable to be instances of %s, but found an item of type '%s'.",
                                $checkClass,
                                FindingDTO::class,
                                get_debug_type($findingDto)
                            )
                        );
                    }

                    if (!$storeSuccessFindings && $findingDto->level === FindingLevel::SUCCESS) {
                        continue;
                    }

                    $this->saveFinding($model, $findingDto);
                    $findingsToReturn->push($findingDto);
                }

            } catch (Throwable $e) {
                $config = ($checkInstance instanceof ProvidesConfigurationDetails) ? $checkInstance->getConfigurationDetails() : null;
                $dto = FindingDTO::error(
                    message: "Check execution failed: ".$e->getMessage(),
                    checkName: $checkClass,
                    url: $url,
                    configuration: $config,
                    details: ['exception' => get_class($e)]
                );
                $this->saveFinding($model, $dto);
                $findingsToReturn->push($dto);
            }
        }
        return $findingsToReturn;
    }

    /**
     * Saves a Finding DTO to the database via the Finding Model.
     */
    protected function saveFinding(CheckableInterface&Model $model, FindingDTO $findingDto): void
    {
        try {
            $model->findings()->create([
                'level' => $findingDto->level,
                'message' => $findingDto->message,
                'check_name' => $findingDto->checkName,
                'url' => $findingDto->url,
                'configuration' => $findingDto->configuration,
                'details' => $findingDto->details,
            ]);
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * Calculate summary metrics from generated findings DTOs and update the status record.
     *
     * @param  CheckableInterface&Model  $model  The model being checked.
     * @param  Collection<int, FindingDTO>  $generatedFindings  Collection of DTOs generated in this job run.
     */
    protected function calculateAndUpdateStatus(
        CheckableInterface&Model $model,
        Collection $generatedFindings
    ): void {
        try {
            $findingLevelCounts = Summary::initializeLevelCounts();
            $checkLevelCounts = Summary::initializeLevelCounts();

            $overallStatus = FindingLevel::SUCCESS;

            $uniqueChecksRun = new Collection();

            $generatedFindings->countBy(fn(FindingDTO $dto) => $dto->level->value)
                ->each(function (int $count, string $levelValue) use (&$findingLevelCounts) {
                    if (isset($findingLevelCounts[$levelValue])) {
                        $findingLevelCounts[$levelValue] = $count;
                    }
                });

            $groupedByCheck = $generatedFindings->groupBy('checkName');
            foreach ($groupedByCheck as $checkName => $checkDtos) {
                $uniqueChecksRun->push($checkName);

                $mostSevereForCheck = null;
                foreach ($checkDtos as $dto) {
                    $mostSevereForCheck = $this->getMoreSevereLevel($mostSevereForCheck, $dto->level);
                }

                if ($mostSevereForCheck) {
                    if (isset($checkLevelCounts[$mostSevereForCheck->value])) {
                        $checkLevelCounts[$mostSevereForCheck->value]++;
                    }
                    $overallStatus = $this->getMoreSevereLevel($overallStatus, $mostSevereForCheck);
                }
            }

            $totalFindingsCount = $generatedFindings->count();
            $totalChecksRunCount = $uniqueChecksRun->unique()->count();

            Summary::updateOrCreate(
                [
                    'checkable_id' => $model->getKey(),
                    'checkable_type' => $model->getMorphClass(),
                ],
                [
                    'status' => $overallStatus,
                    'finding_counts' => $findingLevelCounts,
                    'check_counts' => $checkLevelCounts,
                    'check_totals' => $totalChecksRunCount,
                    'finding_totals' => $totalFindingsCount,
                ]
            );

        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * Compares two FindingLevel enums and returns the more severe one.
     * Handles null input gracefully.
     */
    private function getMoreSevereLevel(?FindingLevel $levelA, ?FindingLevel $levelB): ?FindingLevel
    {
        if ($levelB === null) {
            return $levelA;
        }
        if ($levelA === null) {
            return $levelB;
        }

        $severity = [
            FindingLevel::ERROR->value => 4,
            FindingLevel::WARNING->value => 3,
            FindingLevel::INFO->value => 2,
            FindingLevel::SUCCESS->value => 1,
        ];

        return ($severity[$levelB->value] > $severity[$levelA->value]) ? $levelB : $levelA;
    }
}
