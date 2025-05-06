<?php

namespace Vvb13a\LaravelModelChecker\Contracts;

/**
 * Interface for Eloquent models that can be checked
 * by the Laravel Response Checker infrastructure.
 */
interface CheckableInterface
{
    /**
     * Get the list of absolute URLs associated with this model instance
     * that should be checked.
     *
     * @return array<string> An array of URLs.
     */
    public function getCheckableUrls(): array;
}
