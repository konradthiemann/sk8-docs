<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\DocAdr;
use App\Entity\DocEntry;
use App\Entity\DocTag;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[CoversNothing]
final class DocsImportCommandTest extends KernelTestCase
{
    private CommandTester $tester;
    private EntityManagerInterface $entityManager;
    private Filesystem $filesystem;
    private ?string $tempDir = null;

    protected function setUp(): void
    {
        $application = new Application(self::bootKernel());
        $this->tester = new CommandTester($application->find('docs:import'));
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->filesystem = new Filesystem();
    }

    protected function tearDown(): void
    {
        if (null !== $this->tempDir) {
            $this->filesystem->remove($this->tempDir);
        }

        parent::tearDown();
    }

    public function testDryRunOverTheRealContentDirectorySucceeds(): void
    {
        $exitCode = $this->tester->execute(['--dry-run' => true]);

        $display = $this->tester->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode, $display);
        self::assertStringContainsString('Trockenlauf', $display);
        // Derived from the files on disk so new entries and ADRs cannot break this test.
        self::assertStringContainsString(
            \sprintf('%d Einträge, %d ADRs', self::countContentFiles('entries'), self::countContentFiles('adr')),
            $display,
        );
        self::assertSame(0, $this->entityManager->getRepository(DocAdr::class)->count([]));
    }

    public function testDryRunOverBrokenContentFailsAndListsEveryError(): void
    {
        $exitCode = $this->tester->execute([
            '--dry-run' => true,
            '--content-dir' => self::fixture('content-broken'),
        ]);

        $display = $this->tester->getDisplay();
        self::assertSame(Command::FAILURE, $exitCode, $display);
        self::assertStringContainsString('0001-kaputter-eintrag.md', $display);
        self::assertStringContainsString('type', $display);
        self::assertStringContainsString('## Tests', $display);
        self::assertStringContainsString('nichts importiert', $display);
    }

    public function testImportOverBrokenContentWritesNothing(): void
    {
        $exitCode = $this->tester->execute(['--content-dir' => self::fixture('content-broken')]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(0, $this->entityManager->getRepository(DocAdr::class)->count([]));
    }

    public function testImportOverTheRealContentDirectoryImportsEveryFile(): void
    {
        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode, $this->tester->getDisplay());
        self::assertStringContainsString('Import abgeschlossen', $this->tester->getDisplay());
        self::assertGreaterThanOrEqual(10, self::countContentFiles('adr'), 'the ten ADRs must be present');
        self::assertSame(self::countContentFiles('adr'), $this->entityManager->getRepository(DocAdr::class)->count([]));
        self::assertSame(self::countContentFiles('entries'), $this->entityManager->getRepository(DocEntry::class)->count([]));

        $adr = $this->entityManager->find(DocAdr::class, 'ADR-004');
        self::assertInstanceOf(DocAdr::class, $adr);
        self::assertSame('ADR-004-docs-plattform', $adr->getSlug());
        self::assertStringContainsString('<h2 id="kontext">', $adr->getBodyHtml());
        self::assertContains('docs', array_map(static fn(DocTag $tag): string => $tag->getName(), $adr->getTags()->toArray()));
    }

    public function testImportFillsTheSearchVectorOfEveryRow(): void
    {
        $this->tester->execute([]);
        $connection = $this->entityManager->getConnection();

        foreach (['doc_adr', 'doc_entry'] as $table) {
            $total = $connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s', $table));
            $indexed = $connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE search_vector IS NOT NULL', $table));

            self::assertIsNumeric($total);
            self::assertIsNumeric($indexed);
            self::assertGreaterThan(0, (int) $total, $table . ' must not be empty');
            self::assertSame((int) $total, (int) $indexed, $table . ' must be fully indexed');
        }
    }

    public function testImportIsIdempotent(): void
    {
        $this->tester->execute(['--content-dir' => self::fixture('content-valid')]);
        $firstImport = $this->snapshot();

        $exitCode = $this->tester->execute(['--content-dir' => self::fixture('content-valid')]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame($firstImport, $this->snapshot());
        self::assertSame(2, $this->entityManager->getRepository(DocEntry::class)->count([]));
        self::assertSame(2, $this->entityManager->getRepository(DocAdr::class)->count([]));
        self::assertSame(3, $this->entityManager->getRepository(DocTag::class)->count([]));
    }

    public function testImportUpdatesChangedFilesAndDeletesRowsWhoseFileDisappeared(): void
    {
        $dir = $this->copyFixtureToTempDir('content-valid');
        $this->tester->execute(['--content-dir' => $dir]);
        self::assertSame(2, $this->entityManager->getRepository(DocEntry::class)->count([]));

        $entryFile = $dir . '/entries/0002-zweiter-eintrag.md';
        file_put_contents($entryFile, str_replace('title: Zweiter Eintrag', 'title: Geänderter Titel', (string) file_get_contents($entryFile)));
        $this->filesystem->remove($dir . '/adr/ADR-002-zweite-entscheidung.md');

        $exitCode = $this->tester->execute(['--content-dir' => $dir]);
        $this->entityManager->clear();

        self::assertSame(Command::SUCCESS, $exitCode, $this->tester->getDisplay());
        self::assertStringContainsString('1 gelöscht', $this->tester->getDisplay());
        self::assertNull($this->entityManager->find(DocAdr::class, 'ADR-002'));
        self::assertSame(1, $this->entityManager->getRepository(DocAdr::class)->count([]));

        $entry = $this->entityManager->getRepository(DocEntry::class)->findOneBy(['slug' => 'zweiter-eintrag']);
        self::assertInstanceOf(DocEntry::class, $entry);
        self::assertSame('Geänderter Titel', $entry->getTitle());
    }

    public function testImportRemovesTagsThatAreNoLongerUsed(): void
    {
        $dir = $this->copyFixtureToTempDir('content-valid');
        $this->tester->execute(['--content-dir' => $dir]);
        self::assertNotNull($this->entityManager->getRepository(DocTag::class)->findOneBy(['name' => 'docker']));

        $this->filesystem->remove($dir . '/entries/0002-zweiter-eintrag.md');
        $this->tester->execute(['--content-dir' => $dir]);
        $this->entityManager->clear();

        self::assertNull($this->entityManager->getRepository(DocTag::class)->findOneBy(['name' => 'docker']));
    }

    public function testImportFailsWithAClearMessageWhenTheDirectoryDoesNotExist(): void
    {
        $exitCode = $this->tester->execute(['--content-dir' => '/nirgendwo/content']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('nicht gefunden', $this->tester->getDisplay());
    }

    private static function fixture(string $name): string
    {
        return \dirname(__DIR__, 2) . '/Fixtures/' . $name;
    }

    /**
     * Number of Markdown files in the real content directory ("entries" or "adr"), so the
     * expectations grow with the documentation instead of pinning today's numbers.
     */
    private static function countContentFiles(string $kind): int
    {
        $paths = glob(\dirname(__DIR__, 3) . '/content/' . $kind . '/*.md');

        return false === $paths ? 0 : \count($paths);
    }

    private function copyFixtureToTempDir(string $name): string
    {
        $this->tempDir = sys_get_temp_dir() . '/sk8-docs-' . bin2hex(random_bytes(4));
        $this->filesystem->mirror(self::fixture($name), $this->tempDir);

        return $this->tempDir;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function snapshot(): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, slug, title, body_html FROM doc_entry ORDER BY id',
        );
        $rows[] = ['adr_ids' => $this->entityManager->getConnection()->fetchFirstColumn('SELECT id FROM doc_adr ORDER BY id')];

        return $rows;
    }
}
