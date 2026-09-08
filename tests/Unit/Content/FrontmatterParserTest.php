<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Content\FrontmatterException;
use App\Content\FrontmatterParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FrontmatterParser::class)]
final class FrontmatterParserTest extends TestCase
{
    private FrontmatterParser $parser;

    protected function setUp(): void
    {
        $this->parser = new FrontmatterParser();
    }

    public function testItSplitsFrontmatterAndBody(): void
    {
        $markdown = <<<'MD'
            ---
            id: 1
            title: Projekt-Initialisierung
            agents: [architect, implementer]
            ---

            ## Was

            Text.
            MD;

        $document = $this->parser->parse($markdown);

        self::assertSame(1, $document->frontmatter['id']);
        self::assertSame('Projekt-Initialisierung', $document->frontmatter['title']);
        self::assertSame(['architect', 'implementer'], $document->frontmatter['agents']);
        self::assertSame("## Was\n\nText.", $document->body);
    }

    public function testItKeepsUnquotedIsoDatesAsDateObjects(): void
    {
        $document = $this->parser->parse("---\ndate: 2026-09-07\n---\nBody");

        self::assertInstanceOf(\DateTimeImmutable::class, $document->frontmatter['date']);
        self::assertSame('2026-09-07', $document->frontmatter['date']->format('Y-m-d'));
    }

    public function testItAcceptsWindowsLineEndings(): void
    {
        $document = $this->parser->parse("---\r\nid: ADR-001\r\n---\r\n\r\n## Kontext\r\n");

        self::assertSame('ADR-001', $document->frontmatter['id']);
        self::assertSame("## Kontext\n", $document->body);
    }

    public function testItReturnsEmptyBodyWhenOnlyFrontmatterIsPresent(): void
    {
        $document = $this->parser->parse("---\nid: 1\n---\n");

        self::assertSame('', $document->body);
    }

    public function testItFailsWhenFileDoesNotStartWithFrontmatter(): void
    {
        $this->expectException(FrontmatterException::class);
        $this->expectExceptionMessage('Kein Frontmatter');

        $this->parser->parse("## Was\n\nKein Frontmatter.");
    }

    public function testItFailsWhenFrontmatterIsNotClosed(): void
    {
        $this->expectException(FrontmatterException::class);
        $this->expectExceptionMessage('nicht geschlossen');

        $this->parser->parse("---\nid: 1\ntitle: Offen\n");
    }

    public function testItFailsOnInvalidYaml(): void
    {
        $this->expectException(FrontmatterException::class);
        $this->expectExceptionMessage('YAML');

        $this->parser->parse("---\nid: [unclosed\n---\nBody");
    }

    public function testItFailsWhenFrontmatterIsNotAMapping(): void
    {
        $this->expectException(FrontmatterException::class);
        $this->expectExceptionMessage('Schlüssel-Wert-Paare');

        $this->parser->parse("---\n- eins\n- zwei\n---\nBody");
    }
}
