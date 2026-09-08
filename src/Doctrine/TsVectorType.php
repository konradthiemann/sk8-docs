<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * PostgreSQL `tsvector` column for full-text search (ADR-004).
 *
 * The value is written by native SQL in the importer (`to_tsvector('german', ...)`), so the
 * entity column is read-only; this type only tells the schema tool what the column looks like.
 */
final class TsVectorType extends Type
{
    public const string NAME = 'tsvector';

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'TSVECTOR';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            \is_string($value) => $value,
            default => throw new \UnexpectedValueException(\sprintf('tsvector-Spalte lieferte %s statt einer Zeichenkette.', get_debug_type($value))),
        };
    }
}
