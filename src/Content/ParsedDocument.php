<?php

declare(strict_types=1);

namespace App\Content;

/**
 * A Markdown file split into its YAML frontmatter and the Markdown body.
 */
final readonly class ParsedDocument
{
    /**
     * @param array<string, mixed> $frontmatter
     */
    public function __construct(
        public array $frontmatter,
        public string $body,
    ) {}
}
