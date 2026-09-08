<?php

declare(strict_types=1);

namespace App\Tests\Content;

use App\Content\ContentValidator;
use App\Content\FrontmatterException;
use App\Content\FrontmatterParser;
use App\Content\ValidationError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validates every Markdown file below content/ (frontmatter, sections, filenames).
 * Runs without kernel or database: `vendor/bin/phpunit --testsuite content`.
 */
#[CoversNothing]
final class ContentSuiteTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideContentFiles(): iterable
    {
        $contentDir = \dirname(__DIR__, 2) . '/content';

        foreach (['entries', 'adr'] as $kind) {
            foreach (self::glob($contentDir . '/' . $kind . '/*.md') as $path) {
                yield $kind . '/' . basename($path) => [$kind, $path];
            }
        }
    }

    #[DataProvider('provideContentFiles')]
    public function testContentFileIsValid(string $kind, string $path): void
    {
        $parser = new FrontmatterParser();
        $validator = new ContentValidator();
        $file = basename($path);

        try {
            $document = $parser->parse((string) file_get_contents($path));
        } catch (FrontmatterException $exception) {
            self::fail(\sprintf('%s: %s', $file, $exception->getMessage()));
        }

        $errors = 'adr' === $kind
            ? $validator->validateAdr($file, $document)
            : $validator->validateEntry($file, $document);

        self::assertSame([], array_map(
            static fn(ValidationError $error): string => \sprintf('%s [%s]: %s', $error->file, $error->field, $error->message),
            $errors,
        ));
    }

    public function testEveryAdrExists(): void
    {
        $files = self::glob(\dirname(__DIR__, 2) . '/content/adr/ADR-*.md');

        self::assertGreaterThanOrEqual(10, \count($files), 'the ten architecture decisions must be present');
    }

    /**
     * @return list<string>
     */
    private static function glob(string $pattern): array
    {
        $paths = glob($pattern);

        return false === $paths ? [] : $paths;
    }
}
