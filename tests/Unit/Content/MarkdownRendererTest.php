<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Content\InternalLinkRewriter;
use App\Content\MarkdownRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownRenderer::class)]
#[CoversClass(InternalLinkRewriter::class)]
final class MarkdownRendererTest extends TestCase
{
    private MarkdownRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new MarkdownRenderer(new InternalLinkRewriter());
    }

    public function testItRendersMermaidFencesAsPreMermaid(): void
    {
        $html = $this->renderer->render("```mermaid\ngraph TD\n  A --> B\n```\n");

        self::assertStringContainsString('<pre class="mermaid">graph TD', $html);
        self::assertStringContainsString('A --&gt; B', $html);
        self::assertStringNotContainsString('<code', $html);
    }

    public function testItRendersOtherFencesForHighlightJs(): void
    {
        $html = $this->renderer->render("```php\n<?php echo 1;\n```\n");

        self::assertStringContainsString('<pre><code class="language-php">', $html);
        self::assertStringContainsString('&lt;?php echo 1;', $html);
    }

    public function testItRendersFencesWithoutLanguage(): void
    {
        $html = $this->renderer->render("```\nplain\n```\n");

        self::assertStringContainsString('<pre><code>plain', $html);
    }

    public function testItRendersGfmTables(): void
    {
        $html = $this->renderer->render("| Feld | Typ |\n|---|---|\n| id | int |\n");

        self::assertStringContainsString('<table>', $html);
        self::assertStringContainsString('<th>Feld</th>', $html);
        self::assertStringContainsString('<td>int</td>', $html);
    }

    public function testItAddsPermalinksToHeadings(): void
    {
        $html = $this->renderer->render("## Was du daraus lernst\n");

        self::assertStringContainsString('<h2 id="was-du-daraus-lernst">', $html);
        self::assertStringContainsString('href="#was-du-daraus-lernst"', $html);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInternalLinks(): iterable
    {
        yield 'adr from entry' => ['../adr/ADR-006-api-zugriff-sicherheit.md', '/adr/ADR-006'];
        yield 'adr with anchor' => ['../adr/ADR-006-api-zugriff-sicherheit.md#kontext', '/adr/ADR-006#kontext'];
        yield 'adr sibling' => ['ADR-002-backend-stack.md', '/adr/ADR-002'];
        yield 'adr relative sibling' => ['./ADR-002-backend-stack.md', '/adr/ADR-002'];
        yield 'adr without extension' => ['../adr/ADR-006-api-zugriff-sicherheit', '/adr/ADR-006'];
        yield 'entry from adr' => ['../entries/0002-trick-tree.md', '/eintrag/trick-tree'];
        yield 'entry sibling' => ['0002-trick-tree.md', '/eintrag/trick-tree'];
        yield 'entry with anchor' => ['../entries/0002-trick-tree.md#wie', '/eintrag/trick-tree#wie'];
    }

    #[DataProvider('provideInternalLinks')]
    public function testItRewritesInternalMarkdownLinksToRoutes(string $target, string $expected): void
    {
        $html = $this->renderer->render(\sprintf('[Link](%s)', $target));

        self::assertStringContainsString(\sprintf('href="%s"', $expected), $html);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUntouchedLinks(): iterable
    {
        yield 'external' => ['https://symfony.com/doc/current/index.html'];
        yield 'anchor only' => ['#kontext'];
        yield 'absolute path' => ['/adr/ADR-001'];
        yield 'other file' => ['../README.md'];
    }

    #[DataProvider('provideUntouchedLinks')]
    public function testItLeavesOtherLinksAlone(string $target): void
    {
        $html = $this->renderer->render(\sprintf('[Link](%s)', $target));

        self::assertStringContainsString(\sprintf('href="%s"', $target), $html);
    }

    public function testItAutolinksBareUrls(): void
    {
        $html = $this->renderer->render('Siehe https://symfony.com/doc');

        self::assertStringContainsString('<a href="https://symfony.com/doc">https://symfony.com/doc</a>', $html);
    }

    public function testItWrapsTheLernpunkteSectionAsAdmonition(): void
    {
        $html = $this->renderer->render("## Tests\n\nText.\n\n## Lernpunkte\n\n- Punkt eins\n- Punkt zwei\n\n## Alternativen\n\nDanach.\n");

        self::assertMatchesRegularExpression(
            '#<section class="admonition admonition--lernpunkte">\s*<h2 id="lernpunkte">.*?</h2>\s*<ul>.*?</ul>\s*</section>\s*<h2 id="alternativen">#s',
            $html,
        );
        self::assertStringContainsString('Danach.', $html);
    }

    public function testItWrapsLernpunkteWhenItIsTheLastSection(): void
    {
        $html = $this->renderer->render("## Lernpunkte\n\n- Punkt\n");

        self::assertStringContainsString('<section class="admonition admonition--lernpunkte">', $html);
        self::assertStringEndsWith("</section>\n", $html);
    }

    public function testItPassesRawHtmlThroughBecauseContentIsTrusted(): void
    {
        $html = $this->renderer->render('<kbd>Strg</kbd> drücken');

        self::assertStringContainsString('<kbd>Strg</kbd>', $html);
    }

    public function testItRendersStrikethroughAndTaskLists(): void
    {
        $html = $this->renderer->render("~~alt~~\n\n- [x] fertig\n- [ ] offen\n");

        self::assertStringContainsString('<del>alt</del>', $html);
        self::assertStringContainsString('type="checkbox"', $html);
    }
}
