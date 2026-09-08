<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Turns relative Markdown links between content files into application routes, so that
 * links work both on GitHub (`../adr/ADR-006-api.md`) and in the rendered app (`/adr/ADR-006`).
 */
final class InternalLinkRewriter
{
    private const string ADR_LINK = '#^(?:(?:\.{1,2}/)*adr/|(?:\./)?)(ADR-\d{3})(?:-[a-z0-9-]+)?(?:\.md)?(\#.*)?$#';
    private const string ENTRY_LINK = '#^(?:(?:\.{1,2}/)*entries/|(?:\./)?)\d{4}-([a-z0-9-]+?)(?:\.md)?(\#.*)?$#';

    public function __construct(
        private readonly string $adrPath = '/adr',
        private readonly string $entryPath = '/eintrag',
    ) {}

    public function rewrite(string $url): string
    {
        if (1 === preg_match(self::ADR_LINK, $url, $matches)) {
            return $this->adrPath . '/' . $matches[1] . ($matches[2] ?? '');
        }

        if (1 === preg_match(self::ENTRY_LINK, $url, $matches)) {
            return $this->entryPath . '/' . $matches[1] . ($matches[2] ?? '');
        }

        return $url;
    }
}
