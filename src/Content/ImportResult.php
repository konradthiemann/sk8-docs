<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Outcome of one `docs:import` run.
 */
final readonly class ImportResult
{
    /**
     * @param list<ValidationError> $errors
     */
    public function __construct(
        public array $errors,
        public int $entries,
        public int $adrs,
        public int $deleted,
        public bool $dryRun,
    ) {}

    public function isSuccessful(): bool
    {
        return [] === $this->errors;
    }
}
