<?php

declare(strict_types=1);

namespace App\Content;

use App\Entity\AdrStatus;
use App\Entity\EntryType;

/**
 * Checks a parsed content file against the frontmatter schema of ADR-004.
 *
 * All problems of a file are collected instead of stopping at the first one, so the
 * author can fix everything in one go. Messages are German because they end up in
 * the console output of `docs:import`.
 */
final class ContentValidator
{
    public const array ENTRY_REQUIRED_SECTIONS = ['Was', 'Warum', 'Wie', 'Tests', 'Lernpunkte'];

    /** Agent roles that may appear in `agents` (never tool or vendor names). */
    public const array KNOWN_AGENTS = ['architect', 'tester', 'implementer', 'uiux', 'documentarian', 'researcher', 'agentic-engineer'];

    private const array ENTRY_FIELDS = ['id', 'title', 'date', 'type', 'agents', 'repos', 'tags', 'summary', 'learning_path', 'adrs', 'tickets'];
    private const array ENTRY_REQUIRED = ['id', 'title', 'date', 'type', 'agents', 'repos', 'summary'];

    private const array ADR_FIELDS = ['id', 'title', 'status', 'date', 'agents', 'tags', 'supersedes', 'superseded_by'];
    private const array ADR_REQUIRED = ['id', 'title', 'status', 'date', 'agents'];

    private const string ENTRY_FILENAME = '/^(\d{4})-([a-z0-9]+(?:-[a-z0-9]+)*)\.md$/';
    private const string ADR_FILENAME = '/^(ADR-\d{3})-([a-z0-9]+(?:-[a-z0-9]+)*)\.md$/';
    private const string ADR_ID = '/^ADR-\d{3}$/';
    private const string TICKET_ID = '/^(T-\d{4}|R-\d{2})$/';

    /**
     * @return list<ValidationError>
     */
    public function validateEntry(string $file, ParsedDocument $document): array
    {
        $errors = [];
        $fm = $document->frontmatter;
        $add = static function (string $field, string $message) use (&$errors, $file): void {
            $errors[] = new ValidationError($file, $field, $message);
        };

        $this->checkFields($fm, self::ENTRY_FIELDS, self::ENTRY_REQUIRED, $add);

        $id = $fm['id'] ?? null;
        if (\array_key_exists('id', $fm) && !$this->isPositiveInt($id)) {
            $add('id', 'Muss eine ganze Zahl größer als 0 sein (z. B. 1).');
            $id = null;
        }

        $this->checkNonEmptyString($fm, 'title', $add);
        $this->checkNonEmptyString($fm, 'summary', $add);
        $this->checkDate($fm, $add);
        $this->checkEnum($fm, 'type', EntryType::values(), $add);
        $this->checkAgents($fm, $add);
        $this->checkStringList($fm, 'repos', true, $add);
        $this->checkStringList($fm, 'tags', false, $add);

        if (\array_key_exists('learning_path', $fm) && !$this->isPositiveInt($fm['learning_path'])) {
            $add('learning_path', 'Muss eine ganze Zahl größer als 0 sein (Position im Lernpfad).');
        }

        if (\array_key_exists('adrs', $fm)) {
            $adrs = $fm['adrs'];
            if (!\is_array($adrs) || !array_is_list($adrs)) {
                $add('adrs', 'Muss eine Liste sein, z. B. [ADR-002].');
            } else {
                foreach ($adrs as $adr) {
                    if (!\is_string($adr) || 1 !== preg_match(self::ADR_ID, $adr)) {
                        $add('adrs', \sprintf('"%s" hat nicht das Format ADR-NNN.', $this->stringify($adr)));
                    }
                }
            }
        }

        $this->checkTickets($fm, $add);

        if (1 !== preg_match(self::ENTRY_FILENAME, basename($file), $matches)) {
            $add('file', 'Dateiname muss dem Muster NNNN-slug.md entsprechen (vierstellige Nummer, kebab-case, z. B. 0001-projekt-initialisierung.md).');
        } elseif (\is_int($id) && (int) $matches[1] !== $id) {
            $add('id', \sprintf('id %d passt nicht zur Nummer im Dateinamen (%s).', $id, $matches[1]));
        }

        foreach ($this->checkEntrySections($document->body) as $message) {
            $add('body', $message);
        }

        return $errors;
    }

    /**
     * @return list<ValidationError>
     */
    public function validateAdr(string $file, ParsedDocument $document): array
    {
        $errors = [];
        $fm = $document->frontmatter;
        $add = static function (string $field, string $message) use (&$errors, $file): void {
            $errors[] = new ValidationError($file, $field, $message);
        };

        $this->checkFields($fm, self::ADR_FIELDS, self::ADR_REQUIRED, $add);

        $id = $fm['id'] ?? null;
        if (\array_key_exists('id', $fm) && (!\is_string($id) || 1 !== preg_match(self::ADR_ID, $id))) {
            $add('id', \sprintf('"%s" hat nicht das Format ADR-NNN (z. B. ADR-001).', $this->stringify($id)));
            $id = null;
        }

        $this->checkNonEmptyString($fm, 'title', $add);
        $this->checkDate($fm, $add);
        $this->checkEnum($fm, 'status', AdrStatus::values(), $add);
        $this->checkAgents($fm, $add);
        $this->checkStringList($fm, 'tags', false, $add);

        foreach (['supersedes', 'superseded_by'] as $field) {
            if (\array_key_exists($field, $fm) && (!\is_string($fm[$field]) || 1 !== preg_match(self::ADR_ID, $fm[$field]))) {
                $add($field, \sprintf('"%s" hat nicht das Format ADR-NNN.', $this->stringify($fm[$field])));
            }
        }

        if (1 !== preg_match(self::ADR_FILENAME, basename($file), $matches)) {
            $add('file', 'Dateiname muss dem Muster ADR-NNN-slug.md entsprechen (z. B. ADR-001-multi-repo-workspace.md).');
        } elseif (\is_string($id) && $matches[1] !== $id) {
            $add('id', \sprintf('id %s passt nicht zum Dateinamen (%s).', $id, $matches[1]));
        }

        return $errors;
    }

    /**
     * Extracts the `## ` headings outside of code fences.
     *
     * @return list<string>
     */
    public static function extractSections(string $body): array
    {
        $sections = [];
        $inFence = false;

        foreach (explode("\n", str_replace("\r\n", "\n", $body)) as $line) {
            if (1 === preg_match('/^\s{0,3}(```|~~~)/', $line)) {
                $inFence = !$inFence;
                continue;
            }
            if (!$inFence && 1 === preg_match('/^##\s+(.+?)\s*#*\s*$/', $line, $matches)) {
                $sections[] = $matches[1];
            }
        }

        return $sections;
    }

    /**
     * @return list<string>
     */
    private function checkEntrySections(string $body): array
    {
        $messages = [];
        $sections = self::extractSections($body);
        $expected = 'Erwartete Reihenfolge: ' . implode(' · ', array_map(static fn(string $s): string => '## ' . $s, self::ENTRY_REQUIRED_SECTIONS)) . '.';

        $positions = [];
        foreach (self::ENTRY_REQUIRED_SECTIONS as $required) {
            $found = array_keys($sections, $required, true);
            if ([] === $found) {
                $messages[] = \sprintf('Pflicht-Abschnitt "## %s" fehlt. %s', $required, $expected);
                continue;
            }
            if (\count($found) > 1) {
                $messages[] = \sprintf('Abschnitt "## %s" kommt mehrfach vor.', $required);
            }
            $positions[$required] = $found[0];
        }

        $previousName = null;
        $previousPosition = -1;
        foreach (self::ENTRY_REQUIRED_SECTIONS as $required) {
            $position = $positions[$required] ?? null;
            if (null === $position) {
                continue;
            }
            if (null !== $previousName && $position < $previousPosition) {
                $messages[] = \sprintf('Abschnitt "## %s" steht vor "## %s". %s', $required, $previousName, $expected);
            }
            $previousName = $required;
            $previousPosition = $position;
        }

        return $messages;
    }

    /**
     * @param array<string, mixed>           $fm
     * @param list<string>                   $allowed
     * @param list<string>                   $required
     * @param callable(string, string): void $add
     */
    private function checkFields(array $fm, array $allowed, array $required, callable $add): void
    {
        foreach ($required as $field) {
            if (!\array_key_exists($field, $fm)) {
                $add($field, 'Pflichtfeld fehlt.');
            }
        }
        foreach (array_keys($fm) as $field) {
            if (!\in_array((string) $field, $allowed, true)) {
                $add((string) $field, \sprintf('Unbekanntes Feld. Erlaubt sind: %s.', implode(', ', $allowed)));
            }
        }
    }

    /**
     * @param array<string, mixed>           $fm
     * @param callable(string, string): void $add
     */
    private function checkNonEmptyString(array $fm, string $field, callable $add): void
    {
        if (!\array_key_exists($field, $fm)) {
            return;
        }
        if (!\is_string($fm[$field])) {
            $add($field, 'Muss ein Text sein.');
        } elseif ('' === trim($fm[$field])) {
            $add($field, 'Darf nicht leer sein.');
        }
    }

    /**
     * @param array<string, mixed>           $fm
     * @param callable(string, string): void $add
     */
    private function checkDate(array $fm, callable $add): void
    {
        if (!\array_key_exists('date', $fm)) {
            return;
        }
        if (null === self::normalizeDate($fm['date'])) {
            $add('date', \sprintf('"%s" ist kein ISO-Datum im Format JJJJ-MM-TT (z. B. 2026-09-07).', $this->stringify($fm['date'])));
        }
    }

    /**
     * Accepts the \DateTimeImmutable the YAML parser produces for unquoted dates as well
     * as quoted ISO strings; returns null for anything else.
     */
    public static function normalizeDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)->setTime(0, 0);
        }
        if (!\is_string($value) || 1 !== preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return null;
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return new \DateTimeImmutable($value . ' 00:00:00');
    }

    /**
     * @param array<string, mixed>           $fm
     * @param list<string>                   $allowed
     * @param callable(string, string): void $add
     */
    private function checkEnum(array $fm, string $field, array $allowed, callable $add): void
    {
        if (!\array_key_exists($field, $fm)) {
            return;
        }
        if (!\is_string($fm[$field]) || !\in_array($fm[$field], $allowed, true)) {
            $add($field, \sprintf('"%s" ist ungültig. Erlaubt: %s.', $this->stringify($fm[$field]), implode(', ', $allowed)));
        }
    }

    /**
     * @param array<string, mixed>           $fm
     * @param callable(string, string): void $add
     */
    private function checkAgents(array $fm, callable $add): void
    {
        if (!\array_key_exists('agents', $fm)) {
            return;
        }
        if (!$this->checkStringList($fm, 'agents', true, $add)) {
            return;
        }
        /** @var list<string> $agents */
        $agents = $fm['agents'];
        foreach ($agents as $agent) {
            if (!\in_array($agent, self::KNOWN_AGENTS, true)) {
                $add('agents', \sprintf('"%s" ist keine bekannte Agenten-Rolle. Erlaubt: %s.', $agent, implode(', ', self::KNOWN_AGENTS)));
            }
        }
    }

    /**
     * @param array<string, mixed>           $fm
     * @param callable(string, string): void $add
     */
    private function checkTickets(array $fm, callable $add): void
    {
        if (!\array_key_exists('tickets', $fm)) {
            return;
        }
        if (!$this->checkStringList($fm, 'tickets', false, $add)) {
            return;
        }
        /** @var list<string> $tickets */
        $tickets = $fm['tickets'];
        foreach ($tickets as $ticket) {
            if (1 !== preg_match(self::TICKET_ID, $ticket)) {
                $add('tickets', \sprintf('"%s" hat nicht das Format T-NNXX oder R-NN (z. B. T-0102 oder R-04).', $ticket));
            }
        }
    }

    /**
     * @param array<string, mixed>           $fm
     * @param callable(string, string): void $add
     */
    private function checkStringList(array $fm, string $field, bool $required, callable $add): bool
    {
        if (!\array_key_exists($field, $fm)) {
            return false;
        }
        $value = $fm[$field];
        if (!\is_array($value) || !array_is_list($value)) {
            $add($field, 'Muss eine Liste sein, z. B. [a, b].');

            return false;
        }
        if ($required && [] === $value) {
            $add($field, 'Liste darf nicht leer sein.');

            return false;
        }
        foreach ($value as $item) {
            if (!\is_string($item) || '' === trim($item)) {
                $add($field, \sprintf('"%s" ist kein gültiger Eintrag (nur nicht-leere Texte).', $this->stringify($item)));

                return false;
            }
        }

        return true;
    }

    private function isPositiveInt(mixed $value): bool
    {
        return \is_int($value) && $value > 0;
    }

    private function stringify(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        if (\is_scalar($value) || null === $value) {
            return var_export($value, true);
        }

        $encoded = json_encode($value, \JSON_UNESCAPED_UNICODE);

        return false === $encoded ? get_debug_type($value) : $encoded;
    }
}
