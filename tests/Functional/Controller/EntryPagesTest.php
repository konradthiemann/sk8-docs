<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Content\ContentImporter;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

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

    public function testEntryDetailShowsAllTicketBadgesWhenTheEntryHasThem(): void
    {
        $filesystem = new Filesystem();
        $dir = sys_get_temp_dir() . '/sk8-docs-entry-pages-' . bin2hex(random_bytes(4));
        $filesystem->mirror(\dirname(__DIR__, 2) . '/Fixtures/content-valid', $dir);
        $entryFile = $dir . '/entries/0002-zweiter-eintrag.md';
        file_put_contents($entryFile, str_replace(
            "summary: Ein zweiter gültiger Eintrag ohne Lernpfad.\n",
            "summary: Ein zweiter gültiger Eintrag ohne Lernpfad.\ntickets: [T-0102, T-0104]\n",
            (string) file_get_contents($entryFile),
        ));

        $result = static::getContainer()->get(ContentImporter::class)->import($dir);
        $filesystem->remove($dir);
        self::assertTrue($result->isSuccessful());

        $this->client->request('GET', '/eintrag/zweiter-eintrag');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.meta', 'T-0102');
        self::assertSelectorTextContains('.meta', 'T-0104');
        self::assertCount(2, $this->client->getCrawler()->filter('.meta .badge--ticket'));
    }

    public function testEntryDetailShowsNoTicketAreaWhenTheEntryHasNone(): void
    {
        $this->client->request('GET', '/eintrag/beispiel-eintrag');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.meta .badge--ticket');
        self::assertSelectorNotExists('.meta [aria-label="Tickets"]');
    }
}
