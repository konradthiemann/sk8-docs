<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Content\ContentImporter;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Every page must stay readable before anything has been documented. This runs against an
 * empty fixture directory on purpose: asserting empty states against the real content/
 * directory would only hold while the project has no documentation at all.
 */
#[CoversNothing]
final class EmptyStateTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $result = static::getContainer()->get(ContentImporter::class)->import(\dirname(__DIR__, 2) . '/Fixtures/content-empty');
        self::assertTrue($result->isSuccessful(), 'importing an empty content directory must succeed');
        self::assertSame(0, $result->entries);
        self::assertSame(0, $result->adrs);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideEmptyStates(): iterable
    {
        yield 'chronicle' => ['/', 'Noch keine Einträge'];
        yield 'learning path' => ['/lernpfad', 'noch keine Einträge'];
        yield 'adr list' => ['/adr', 'Noch keine Entscheidungen importiert'];
        yield 'tags' => ['/tags', 'Noch keine Tags vorhanden'];
        yield 'repos' => ['/repos', 'noch keine Repos'];
        yield 'search' => ['/suche?q=Symfony', 'Keine Treffer'];
    }

    #[DataProvider('provideEmptyStates')]
    public function testItShowsAGermanEmptyState(string $url, string $expectedText): void
    {
        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', $expectedText);
    }

    public function testTheChronicleStillShowsTheContestCountdown(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Tage bis zum Contest');
    }
}
