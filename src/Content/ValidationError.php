<?php

declare(strict_types=1);

namespace App\Content;

/**
 * One validation problem of one content file, reported in German for the console output.
 */
final readonly class ValidationError
{
    public function __construct(
        public string $file,
        public string $field,
        public string $message,
    ) {}
}
