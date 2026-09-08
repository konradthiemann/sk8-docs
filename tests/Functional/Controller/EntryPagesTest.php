<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Content\ContentImporter;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Entry pages are exercised with the fixture content because content/entries is
 * filled feature by feature and may be empty in a fresh checkout.
 */
#[CoversNothing]
final class EntryPagesTest extends WebTestCase
{
    protected function setUp(): void
    {
        $client = static::createClient();
        $result = static::getContainer()->get(ContentImporter::class)->import(\dirname(__DIR__, 2) . '/Fixtures/content-valid');
        self::assertTrue($result->isSuccessful());
        $this->client = $client;
    }

    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    public function testChronikListsEntriesNewestFirstWithMeta(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $titles = $crawler->filter('article h2 a')->each(static fn($node): string => trim($node->text()));
        self::assertSame(['Zweiter Eintrag', 'Beispiel-Eintrag'], $titles);
        self::assertSelectorTextContains('article', '8. September 2026');
        self::assertSelectorExists('a[href="/repos/sk8-backend"]');
        self::assertSelectorExists('a[href="/tags/docker"]');
    }

    public function testChronikFiltersByTypeRepoAndTag(): void
    {
        $crawler = $this->client->request('GET', '/?type=feature');
        self::assertCount(1, $crawler->filter('article'));

        $crawler = $this->client->request('GET', '/?repo=sk8-backend');
        self::assertCount(1, $crawler->filter('article'));
        self::assertSelectorTextContains('article h2', 'Zweiter Eintrag');

        $crawler = $this->client->request('GET', '/?tag=symfony');
        self::assertCount(1, $crawler->filter('article'));
        self::assertSelectorTextContains('article h2', 'Beispiel-Eintrag');

        $this->client->request('GET', '/?type=unbekannt');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Noch keine Einträge');
    }

    public function testEntryDetailRendersBodyMermaidAndLinks(): void
    {
        $this->client->request('GET', '/eintrag/beispiel-eintrag');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Beispiel-Eintrag');
        self::assertSelectorExists('pre.mermaid');
        self::assertSelectorExists('pre code.language-php');
        self::assertSelectorExists('main a[href="/adr/ADR-001"]');
        self::assertSelectorExists('section.admonition--lernpunkte');
        self::assertSelectorTextContains('main', '7. September 2026');
    }

    public function testLearningPathListsOnlyOrderedEntries(): void
    {
        $crawler = $this->client->request('GET', '/lernpfad');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('article'));
        self::assertSelectorTextContains('article', 'Beispiel-Eintrag');
    }

    public function testRepoPageListsEntriesOfThatRepo(): void
    {
        $crawler = $this->client->request('GET', '/repos/sk8-docs');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('article'));
    }

    public function testAdrDetailRewritesLinksToOtherAdrs(): void
    {
        $this->client->request('GET', '/adr/ADR-001');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('main a[href="/adr/ADR-002"]');
    }

    public function testSearchFindsEntriesAndAdrsWithSnippets(): void
    {
        $this->client->request('GET', '/suche?q=Diagramm');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Beispiel-Eintrag');
        self::assertSelectorExists('mark');
    }
}
