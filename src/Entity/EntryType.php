<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Kind of a chronicle entry (frontmatter field `type`, ADR-004).
 */
enum EntryType: string
{
    case Feature = 'feature';
    case Infrastructure = 'infrastruktur';
    case Decision = 'entscheidung';
    case Research = 'recherche';
    case Refactoring = 'refactoring';

    public function label(): string
    {
        return match ($this) {
            self::Feature => 'Feature',
            self::Infrastructure => 'Infrastruktur',
            self::Decision => 'Entscheidung',
            self::Research => 'Recherche',
            self::Refactoring => 'Refactoring',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn(self $type): string => $type->value, self::cases());
    }
}
