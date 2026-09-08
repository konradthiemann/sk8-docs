<?php

declare(strict_types=1);

namespace App\Content;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Splits a Markdown file into the YAML block between the leading `---` lines and the body.
 *
 * Unquoted ISO dates are returned as \DateTimeImmutable (Yaml::PARSE_DATETIME); without
 * that flag Symfony Yaml would silently turn `2026-09-07` into a Unix timestamp.
 */
final class FrontmatterParser
{
    private const string DELIMITER = '---';

    /**
     * @throws FrontmatterException
     */
    public function parse(string $markdown): ParsedDocument
    {
        $normalized = str_replace("\r\n", "\n", $markdown);
        $lines = explode("\n", $normalized);

        if (self::DELIMITER !== trim($lines[0])) {
            throw new FrontmatterException('Kein Frontmatter gefunden: Die Datei muss mit einer Zeile "---" beginnen.');
        }

        $closingIndex = null;
        foreach ($lines as $index => $line) {
            if ($index > 0 && self::DELIMITER === trim($line)) {
                $closingIndex = $index;
                break;
            }
        }

        if (null === $closingIndex) {
            throw new FrontmatterException('Frontmatter ist nicht geschlossen: Es fehlt die zweite Zeile "---".');
        }

        $yaml = implode("\n", \array_slice($lines, 1, $closingIndex - 1));
        $body = implode("\n", \array_slice($lines, $closingIndex + 1));

        try {
            $frontmatter = Yaml::parse($yaml, Yaml::PARSE_DATETIME);
        } catch (ParseException $exception) {
            throw new FrontmatterException(\sprintf('Frontmatter ist kein gültiges YAML: %s', $exception->getMessage()), 0, $exception);
        }

        if (null === $frontmatter) {
            $frontmatter = [];
        }

        if (!\is_array($frontmatter) || ([] !== $frontmatter && array_is_list($frontmatter))) {
            throw new FrontmatterException('Frontmatter muss aus Schlüssel-Wert-Paaren bestehen (z. B. "id: 1"), keine Liste und kein einzelner Wert.');
        }

        // YAML keys may be parsed as integers ("2026: x"); the schema works on string keys.
        $fields = [];
        foreach ($frontmatter as $key => $value) {
            $fields[(string) $key] = $value;
        }

        return new ParsedDocument($fields, ltrim($body, "\n"));
    }
}
