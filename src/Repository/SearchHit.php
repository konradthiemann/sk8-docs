<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * One full-text search result (entry or ADR) with a highlighted snippet.
 */
final readonly class SearchHit
{
    public const string KIND_ENTRY = 'entry';
    public const string KIND_ADR = 'adr';

    public string $snippetHtml;

    public function __construct(
        public string $kind,
        public string $key,
        public string $title,
        public \DateTimeImmutable $date,
        public float $rank,
        string $snippet,
    ) {
        // ts_headline marks matches with [[ ]]; escape everything else, then turn the markers into <mark>.
        $this->snippetHtml = str_replace(
            ['[[', ']]'],
            ['<mark>', '</mark>'],
            htmlspecialchars($snippet, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
        );
    }

    public function isEntry(): bool
    {
        return self::KIND_ENTRY === $this->kind;
    }
}
