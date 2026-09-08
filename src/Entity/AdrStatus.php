<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Lifecycle state of an architecture decision record (frontmatter field `status`, ADR-004).
 */
enum AdrStatus: string
{
    case Proposed = 'vorgeschlagen';
    case Accepted = 'akzeptiert';
    case Superseded = 'abgelöst';

    public function label(): string
    {
        return $this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn(self $status): string => $status->value, self::cases());
    }
}
