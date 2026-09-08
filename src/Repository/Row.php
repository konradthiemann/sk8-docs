<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Type-safe access to raw DBAL result rows, whose values are `mixed` by contract.
 * Wrong types are a programming error (a changed query), so they fail loudly.
 */
final class Row
{
    /**
     * @param array<string, mixed> $row
     */
    public static function string(array $row, string $key): string
    {
        $value = self::scalar($row, $key);

        return \is_string($value) ? $value : (string) $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function int(array $row, string $key): int
    {
        return (int) self::scalar($row, $key);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function float(array $row, string $key): float
    {
        return (float) self::scalar($row, $key);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function scalar(array $row, string $key): bool|float|int|string
    {
        if (!\array_key_exists($key, $row)) {
            throw new \UnexpectedValueException(\sprintf('Spalte "%s" fehlt im Ergebnis.', $key));
        }
        if (!\is_scalar($row[$key])) {
            throw new \UnexpectedValueException(\sprintf('Spalte "%s" ist kein einfacher Wert.', $key));
        }

        return $row[$key];
    }
}
