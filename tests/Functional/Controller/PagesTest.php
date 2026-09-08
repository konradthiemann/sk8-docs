<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;

#[CoversNothing]
final class PagesTest extends ContentWebTestCase
{
    /** First real chronicle entry; presence is asserted, never the number of entries. */
    private const string FIRST_ENTRY_PATH = '/eintrag/project-bootstrap';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providePages(): iterable
    {
        yield 'chronik' => ['/', 'Chronik'];
        yield 'chronik countdown' => ['/', 'Tage bis zum Contest'];
        yield 'chronik filtered' => ['/?type=feature&repo=sk8-docs&tag=docs', 'Chronik'];
        yield 'adr list' => ['/adr', 'Architektur-Entscheidungen'];
        yield 'adr list shows status badge' => ['/adr', 'akzeptiert'];
        yield 'adr detail' => ['/adr/ADR-001', 'Multi-Repo-Struktur'];
        yield 'adr detail renders body' => ['/adr/ADR-001', 'Kontext'];
        yield 'tags' => ['/tags', 'Tags'];
        yield 'tag detail' => ['/tags/postgresql', 'postgresql'];
        yield 'repos' => ['/repos', 'Repos'];
        yield 'search' => ['/suche?q=Symfony', 'Treffer'];
        yield 'search hit' => ['/suche?q=Symfony', 'Backend-Stack'];
        yield 'search empty query' => ['/suche', 'Suche'];
        yield 'search without hits' => ['/suche?q=xyzzyplugh', 'Keine Treffer'];
        yield 'lernpfad' => ['/lernpfad', 'Lernpfad'];
    }

    #[DataProvider('providePages')]
    public function testItRendersPageWithGermanText(string $url, string $expectedText): void
    {
        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($expectedText, (string) $this->client->getResponse()->getContent());
    }

    public function testItServesPagesInGerman(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertSame('de', $crawler->filter('html')->attr('lang'));
        self::assertSelectorTextContains('nav', 'Chronik');
        self::assertSelectorTextContains('nav', 'ADRs');
        self::assertSelectorTextContains('nav', 'Lernpfad');
        self::assertSelectorTextContains('nav', 'Tags');
        self::assertSelectorTextContains('nav', 'Suche');
    }

    public function testItFormatsDatesInGerman(): void
    {
        $this->client->request('GET', '/adr/ADR-001');

        self::assertSelectorTextContains('main', '7. September 2026');
    }

    public function testItLinksAdrsFromTheMetaBlock(): void
    {
        $this->client->request('GET', '/adr');

        self::assertSelectorExists('a[href="/adr/ADR-004"]');
    }

    public function testTheChronicleListsTheFirstEntryWithItsMeta(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $card = self::cardFor($crawler, self::FIRST_ENTRY_PATH);
        self::assertStringContainsString('Projekt-Initialisierung', $card);
        self::assertStringContainsString('7. September 2026', $card);
        self::assertStringContainsString('badge--infrastruktur', $card);
    }

    public function testTheLearningPathListsTheFirstEntryAtPositionOne(): void
    {
        $crawler = $this->client->request('GET', '/lernpfad');

        self::assertResponseIsSuccessful();
        $positions = $crawler->filter('.entry-list--numbered li')->each(
            static fn(Crawler $item): array => [
                trim($item->filter('.entry-card__position')->text()),
                (string) $item->filter('h2 a')->attr('href'),
            ],
        );

        self::assertContains(['1', self::FIRST_ENTRY_PATH], $positions);
    }

    /**
     * The whole point of the app: Markdown from content/ becomes readable HTML. Mermaid
     * fences, GFM tables and relative links to other content files must survive the pipeline.
     */
    public function testTheFirstEntryRendersDiagramsTablesAndRewrittenLinks(): void
    {
        $crawler = $this->client->request('GET', self::FIRST_ENTRY_PATH);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Projekt-Initialisierung');
        self::assertSelectorTextContains('main', '7. September 2026');

        // ```mermaid stays a <pre class="mermaid"> for the client-side renderer …
        self::assertSelectorExists('.prose pre.mermaid');
        // … every other fence keeps its language class for highlight.js.
        self::assertSelectorExists('.prose pre code.language-php');
        self::assertSelectorExists('.prose pre code.language-sql');

        self::assertGreaterThanOrEqual(4, $crawler->filter('.prose table')->count());
        self::assertSelectorExists('.prose table th');

        // ../adr/ADR-009-ux-telemetrie.md → /adr/ADR-009
        foreach (['ADR-001', 'ADR-002', 'ADR-006', 'ADR-009'] as $adrId) {
            self::assertSelectorExists(\sprintf('.prose a[href="/adr/%s"]', $adrId));
        }
        // No relative link to a content file may survive the renderer.
        self::assertCount(0, $crawler->filter('.prose a[href$=".md"]'));

        self::assertSelectorExists('section.admonition--lernpunkte');
    }

    public function testTheFirstEntryIsFoundBySearchAndByItsTags(): void
    {
        $this->client->request('GET', '/suche?q=Telemetrie');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf('a[href="%s"]', self::FIRST_ENTRY_PATH));

        $this->client->request('GET', '/repos/sk8-docs');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf('a[href="%s"]', self::FIRST_ENTRY_PATH));

        $this->client->request('GET', '/tags/bootstrap');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf('a[href="%s"]', self::FIRST_ENTRY_PATH));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownPages(): iterable
    {
        yield 'entry' => ['/eintrag/gibt-es-nicht'];
        yield 'adr' => ['/adr/ADR-999'];
        yield 'tag' => ['/tags/gibt-es-nicht'];
        yield 'repo' => ['/repos/gibt-es-nicht'];
    }

    #[DataProvider('provideUnknownPages')]
    public function testItReturns404ForUnknownSlugs(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * The HTML of the entry card linking to $path, so assertions stay scoped to one entry
     * instead of depending on how many entries exist.
     */
    private static function cardFor(Crawler $crawler, string $path): string
    {
        $cards = $crawler->filter('article.entry-card')->each(
            static fn(Crawler $card): string => $card->html(),
        );
        $matching = array_values(array_filter(
            $cards,
            static fn(string $html): bool => str_contains($html, \sprintf('href="%s"', $path)),
        ));

        self::assertNotSame([], $matching, \sprintf('no entry card links to %s', $path));

        return $matching[0];
    }

    public function testHealthEndpointAnswersJson(): void
    {
        $this->client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertJsonStringEqualsJsonString('{"status":"ok"}', (string) $this->client->getResponse()->getContent());
    }
}
