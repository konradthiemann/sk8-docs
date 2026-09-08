<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Content\ContentValidator;
use App\Content\ParsedDocument;
use App\Content\ValidationError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentValidator::class)]
final class ContentValidatorTest extends TestCase
{
    private const string ENTRY_BODY = <<<'MD'
        ## Was

        Text.

        ## Warum

        Text.

        ## Wie

        Text.

        ## Tests

        Text.

        ## Lernpunkte

        - Punkt
        MD;

    private ContentValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ContentValidator();
    }

    /**
     * @return array<string, mixed>
     */
    private static function validEntryFrontmatter(): array
    {
        return [
            'id' => 1,
            'title' => 'Projekt-Initialisierung',
            'date' => new \DateTimeImmutable('2026-09-07'),
            'type' => 'infrastruktur',
            'agents' => ['architect', 'implementer'],
            'repos' => ['sk8-docs'],
            'tags' => ['symfony', 'docker'],
            'summary' => 'Ein Satz.',
            'learning_path' => 1,
            'adrs' => ['ADR-004'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function validAdrFrontmatter(): array
    {
        return [
            'id' => 'ADR-004',
            'title' => 'Dokumentationsplattform',
            'status' => 'akzeptiert',
            'date' => '2026-09-07',
            'agents' => ['architect'],
            'tags' => ['docs'],
        ];
    }

    public function testItAcceptsAValidEntry(): void
    {
        $errors = $this->validator->validateEntry(
            '0001-projekt-initialisierung.md',
            new ParsedDocument(self::validEntryFrontmatter(), self::ENTRY_BODY),
        );

        self::assertSame([], $errors);
    }

    public function testItAcceptsAnEntryWithOptionalSectionsInBetween(): void
    {
        $body = str_replace("## Tests\n", "## Datenbank\n\nSQL.\n\n## API\n\nJSON.\n\n## Tests\n", self::ENTRY_BODY);
        $errors = $this->validator->validateEntry(
            '0001-projekt-initialisierung.md',
            new ParsedDocument(self::validEntryFrontmatter(), $body),
        );

        self::assertSame([], $errors);
    }

    public function testItAcceptsAValidAdr(): void
    {
        $errors = $this->validator->validateAdr(
            'ADR-004-docs-plattform.md',
            new ParsedDocument(self::validAdrFrontmatter(), "## Kontext\n\nText."),
        );

        self::assertSame([], $errors);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function provideInvalidEntryFrontmatter(): iterable
    {
        $base = self::validEntryFrontmatter();

        yield 'missing title' => [array_diff_key($base, ['title' => 1]), 'title', 'Pflichtfeld'];
        yield 'missing summary' => [array_diff_key($base, ['summary' => 1]), 'summary', 'Pflichtfeld'];
        yield 'missing agents' => [array_diff_key($base, ['agents' => 1]), 'agents', 'Pflichtfeld'];
        yield 'missing repos' => [array_diff_key($base, ['repos' => 1]), 'repos', 'Pflichtfeld'];
        yield 'empty title' => [['title' => '   '] + $base, 'title', 'leer'];
        yield 'wrong type enum' => [['type' => 'bugfix'] + $base, 'type', 'feature, infrastruktur, entscheidung, recherche, refactoring'];
        yield 'date as german string' => [['date' => '7. September 2026'] + $base, 'date', 'JJJJ-MM-TT'];
        yield 'date invalid day' => [['date' => '2026-02-30'] + $base, 'date', 'JJJJ-MM-TT'];
        yield 'id not an int' => [['id' => 'eins'] + $base, 'id', 'ganze Zahl'];
        yield 'agents not a list' => [['agents' => 'architect'] + $base, 'agents', 'Liste'];
        yield 'agents empty list' => [['agents' => []] + $base, 'agents', 'leer'];
        yield 'unknown agent role' => [['agents' => ['architect', 'chatbot']] + $base, 'agents', 'chatbot'];
        yield 'repos empty list' => [['repos' => []] + $base, 'repos', 'leer'];
        yield 'tags not a list' => [['tags' => 'symfony'] + $base, 'tags', 'Liste'];
        yield 'learning_path not an int' => [['learning_path' => 'erster'] + $base, 'learning_path', 'ganze Zahl'];
        yield 'learning_path zero' => [['learning_path' => 0] + $base, 'learning_path', 'ganze Zahl'];
        yield 'adrs with bad id format' => [['adrs' => ['ADR-4']] + $base, 'adrs', 'ADR-NNN'];
        yield 'unknown field' => [['learningPath' => 2] + $base, 'learningPath', 'Unbekanntes Feld'];
    }

    /**
     * @param array<string, mixed> $frontmatter
     */
    #[DataProvider('provideInvalidEntryFrontmatter')]
    public function testItRejectsInvalidEntryFrontmatter(array $frontmatter, string $field, string $messagePart): void
    {
        $errors = $this->validator->validateEntry(
            '0001-projekt-initialisierung.md',
            new ParsedDocument($frontmatter, self::ENTRY_BODY),
        );

        self::assertErrorFor($errors, $field, $messagePart);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidEntryBodies(): iterable
    {
        yield 'missing section Tests' => [
            str_replace("## Tests\n\nText.\n\n", '', self::ENTRY_BODY),
            'Tests',
        ];
        yield 'sections out of order' => [
            // strtr swaps in a single pass; chained str_replace would duplicate a heading.
            strtr(self::ENTRY_BODY, ['## Warum' => '## Wie', '## Wie' => '## Warum']),
            'Reihenfolge',
        ];
        yield 'duplicate section' => [
            self::ENTRY_BODY . "\n\n## Was\n\nNoch einmal.",
            'mehrfach',
        ];
        yield 'empty body' => ['', 'Was'];
    }

    #[DataProvider('provideInvalidEntryBodies')]
    public function testItRejectsInvalidEntryBodies(string $body, string $messagePart): void
    {
        $errors = $this->validator->validateEntry(
            '0001-projekt-initialisierung.md',
            new ParsedDocument(self::validEntryFrontmatter(), $body),
        );

        self::assertErrorFor($errors, 'body', $messagePart);
    }

    public function testItIgnoresHeadingsInsideCodeFences(): void
    {
        $body = str_replace("## Wie\n\nText.", "## Wie\n\n```md\n## Tests\n```\n\nText.", self::ENTRY_BODY);
        $errors = $this->validator->validateEntry(
            '0001-projekt-initialisierung.md',
            new ParsedDocument(self::validEntryFrontmatter(), $body),
        );

        self::assertSame([], $errors);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideEntryFilenameMismatches(): iterable
    {
        yield 'number differs from id' => ['0002-projekt-initialisierung.md', 'id'];
        yield 'number not four digits' => ['1-projekt-initialisierung.md', 'file'];
        yield 'slug with uppercase' => ['0001-Projekt.md', 'file'];
        yield 'slug with umlaut' => ['0001-einführung.md', 'file'];
        yield 'missing slug' => ['0001.md', 'file'];
        yield 'wrong extension' => ['0001-projekt.markdown', 'file'];
    }

    #[DataProvider('provideEntryFilenameMismatches')]
    public function testItRejectsEntryFilenamesThatDoNotMatchTheId(string $filename, string $field): void
    {
        $errors = $this->validator->validateEntry(
            $filename,
            new ParsedDocument(self::validEntryFrontmatter(), self::ENTRY_BODY),
        );

        self::assertNotSame([], $errors);
        self::assertSame($field, $errors[0]->field);
        self::assertSame($filename, $errors[0]->file);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function provideInvalidAdrFrontmatter(): iterable
    {
        $base = self::validAdrFrontmatter();

        yield 'missing status' => [array_diff_key($base, ['status' => 1]), 'status', 'Pflichtfeld'];
        yield 'missing agents' => [array_diff_key($base, ['agents' => 1]), 'agents', 'Pflichtfeld'];
        yield 'missing date' => [array_diff_key($base, ['date' => 1]), 'date', 'Pflichtfeld'];
        yield 'wrong status enum' => [['status' => 'accepted'] + $base, 'status', 'vorgeschlagen, akzeptiert, abgelöst'];
        yield 'id without prefix' => [['id' => '004'] + $base, 'id', 'ADR-NNN'];
        yield 'supersedes bad format' => [['supersedes' => 'ADR-3'] + $base, 'supersedes', 'ADR-NNN'];
        yield 'superseded_by bad format' => [['superseded_by' => 3] + $base, 'superseded_by', 'ADR-NNN'];
        yield 'unknown field' => [['summary' => 'x'] + $base, 'summary', 'Unbekanntes Feld'];
    }

    /**
     * @param array<string, mixed> $frontmatter
     */
    #[DataProvider('provideInvalidAdrFrontmatter')]
    public function testItRejectsInvalidAdrFrontmatter(array $frontmatter, string $field, string $messagePart): void
    {
        $errors = $this->validator->validateAdr(
            'ADR-004-docs-plattform.md',
            new ParsedDocument($frontmatter, "## Kontext\n\nText."),
        );

        self::assertErrorFor($errors, $field, $messagePart);
    }

    public function testItRejectsAnAdrWhoseFilenameDoesNotMatchTheId(): void
    {
        $errors = $this->validator->validateAdr(
            'ADR-005-docs-plattform.md',
            new ParsedDocument(self::validAdrFrontmatter(), "## Kontext\n\nText."),
        );

        self::assertErrorFor($errors, 'id', 'ADR-005');
    }

    public function testItRejectsAnAdrFilenameWithoutSlug(): void
    {
        $errors = $this->validator->validateAdr(
            'ADR-004.md',
            new ParsedDocument(self::validAdrFrontmatter(), "## Kontext\n\nText."),
        );

        self::assertErrorFor($errors, 'file', 'ADR-NNN-slug.md');
    }

    public function testItReportsEveryProblemAtOnce(): void
    {
        $frontmatter = self::validEntryFrontmatter();
        unset($frontmatter['title'], $frontmatter['summary']);
        $frontmatter['type'] = 'nope';

        $errors = $this->validator->validateEntry(
            '0001-projekt-initialisierung.md',
            new ParsedDocument($frontmatter, ''),
        );

        $fields = array_map(static fn(ValidationError $error): string => $error->field, $errors);
        self::assertContains('title', $fields);
        self::assertContains('summary', $fields);
        self::assertContains('type', $fields);
        self::assertContains('body', $fields);
    }

    /**
     * @param list<ValidationError> $errors
     */
    private static function assertErrorFor(array $errors, string $field, string $messagePart): void
    {
        $matching = array_values(array_filter(
            $errors,
            static fn(ValidationError $error): bool => $error->field === $field,
        ));

        self::assertNotSame([], $matching, \sprintf(
            'Expected an error for field "%s", got: %s',
            $field,
            implode(' | ', array_map(static fn(ValidationError $e): string => $e->field . ': ' . $e->message, $errors)),
        ));
        self::assertStringContainsString($messagePart, $matching[0]->message);
    }
}
