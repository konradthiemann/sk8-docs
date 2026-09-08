<?php

declare(strict_types=1);

namespace App\Content;

use App\Entity\AdrStatus;
use App\Entity\DocAdr;
use App\Entity\DocEntry;
use App\Entity\DocTag;
use App\Entity\EntryType;
use App\Repository\DocAdrRepository;
use App\Repository\DocEntryRepository;
use App\Repository\DocTagRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Markdown → PostgreSQL (ADR-004): validates every file first, aborts with the complete
 * error list if anything is wrong, otherwise upserts entries, ADRs and tags in one
 * transaction, refreshes the full-text vectors and removes rows whose file disappeared.
 */
final class ContentImporter
{
    private const string ENTRIES_DIR = 'entries';
    private const string ADR_DIR = 'adr';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DocEntryRepository $entries,
        private readonly DocAdrRepository $adrs,
        private readonly DocTagRepository $tags,
        private readonly FrontmatterParser $parser,
        private readonly ContentValidator $validator,
        private readonly MarkdownRenderer $renderer,
    ) {}

    /**
     * @throws \InvalidArgumentException when the content directory does not exist
     */
    public function import(string $contentDir, bool $dryRun = false): ImportResult
    {
        if (!is_dir($contentDir)) {
            throw new \InvalidArgumentException(\sprintf('Content-Verzeichnis "%s" nicht gefunden.', $contentDir));
        }

        $errors = [];
        $adrDocuments = $this->load($contentDir . '/' . self::ADR_DIR, $errors);
        $entryDocuments = $this->load($contentDir . '/' . self::ENTRIES_DIR, $errors);

        foreach ($adrDocuments as $file => $document) {
            array_push($errors, ...$this->validator->validateAdr($file, $document));
        }
        foreach ($entryDocuments as $file => $document) {
            array_push($errors, ...$this->validator->validateEntry($file, $document));
        }

        if ([] === $errors) {
            array_push($errors, ...$this->checkCrossReferences($adrDocuments, $entryDocuments));
        }

        if ([] !== $errors) {
            return new ImportResult($errors, 0, 0, 0, $dryRun);
        }

        if ($dryRun) {
            foreach ([...$adrDocuments, ...$entryDocuments] as $document) {
                $this->renderer->render($document->body);
            }

            return new ImportResult([], \count($entryDocuments), \count($adrDocuments), 0, true);
        }

        $now = new \DateTimeImmutable();

        $deleted = $this->entityManager->wrapInTransaction(function () use ($adrDocuments, $entryDocuments, $now): int {
            $tagCache = [];
            $this->upsertAdrs($adrDocuments, $now, $tagCache);
            $this->upsertEntries($entryDocuments, $now, $tagCache);
            $this->entityManager->flush();

            $deleted = $this->deleteVanished(array_keys($adrDocuments), array_keys($entryDocuments));
            $this->entityManager->flush();

            $this->deleteOrphanTags();
            $this->entityManager->flush();

            $this->refreshSearchVectors();

            return $deleted;
        });

        return new ImportResult([], \count($entryDocuments), \count($adrDocuments), $deleted, false);
    }

    /**
     * @param list<ValidationError> $errors
     *
     * @return array<string, ParsedDocument> basename => document, sorted by filename
     */
    private function load(string $dir, array &$errors): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $documents = [];
        $paths = glob($dir . '/*.md');
        if (false === $paths) {
            return [];
        }
        sort($paths);

        foreach ($paths as $path) {
            $file = basename($path);
            $markdown = file_get_contents($path);
            if (false === $markdown) {
                $errors[] = new ValidationError($file, 'file', 'Datei konnte nicht gelesen werden.');
                continue;
            }

            try {
                $documents[$file] = $this->parser->parse($markdown);
            } catch (FrontmatterException $exception) {
                $errors[] = new ValidationError($file, 'frontmatter', $exception->getMessage());
            }
        }

        return $documents;
    }

    /**
     * Duplicate ids/slugs and references to ADRs that do not exist.
     *
     * @param array<string, ParsedDocument> $adrDocuments
     * @param array<string, ParsedDocument> $entryDocuments
     *
     * @return list<ValidationError>
     */
    private function checkCrossReferences(array $adrDocuments, array $entryDocuments): array
    {
        $errors = [];
        $adrIds = [];
        foreach ($adrDocuments as $file => $document) {
            $id = self::string($document->frontmatter['id']);
            if (isset($adrIds[$id])) {
                $errors[] = new ValidationError($file, 'id', \sprintf('%s wird bereits von %s verwendet.', $id, $adrIds[$id]));
            }
            $adrIds[$id] = $file;
        }

        foreach ($adrDocuments as $file => $document) {
            foreach (['supersedes', 'superseded_by'] as $field) {
                $reference = $document->frontmatter[$field] ?? null;
                if (\is_string($reference) && !isset($adrIds[$reference])) {
                    $errors[] = new ValidationError($file, $field, \sprintf('Verweist auf unbekanntes %s.', $reference));
                }
            }
        }

        $entryIds = [];
        $slugs = [];
        foreach ($entryDocuments as $file => $document) {
            $id = self::int($document->frontmatter['id']);
            if (isset($entryIds[$id])) {
                $errors[] = new ValidationError($file, 'id', \sprintf('id %d wird bereits von %s verwendet.', $id, $entryIds[$id]));
            }
            $entryIds[$id] = $file;

            $slug = self::entrySlug($file);
            if (isset($slugs[$slug])) {
                $errors[] = new ValidationError($file, 'file', \sprintf('Slug "%s" wird bereits von %s verwendet.', $slug, $slugs[$slug]));
            }
            $slugs[$slug] = $file;

            foreach (self::stringList($document->frontmatter['adrs'] ?? []) as $reference) {
                if (!isset($adrIds[$reference])) {
                    $errors[] = new ValidationError($file, 'adrs', \sprintf('Verweist auf unbekanntes %s.', $reference));
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, ParsedDocument> $documents
     * @param array<string, DocTag>         $tagCache
     */
    private function upsertAdrs(array $documents, \DateTimeImmutable $now, array &$tagCache): void
    {
        foreach ($documents as $file => $document) {
            $fm = $document->frontmatter;
            $id = self::string($fm['id']);
            $arguments = [
                self::adrSlug($file),
                self::string($fm['title']),
                AdrStatus::from(self::string($fm['status'])),
                self::date($fm['date']),
                self::stringList($fm['agents']),
                isset($fm['supersedes']) ? self::string($fm['supersedes']) : null,
                isset($fm['superseded_by']) ? self::string($fm['superseded_by']) : null,
                $document->body,
                $this->renderer->render($document->body),
                $now,
            ];

            $adr = $this->adrs->find($id);
            if (null === $adr) {
                $adr = new DocAdr($id, ...$arguments);
                $this->entityManager->persist($adr);
            } else {
                $adr->update(...$arguments);
            }

            $adr->syncTags($this->resolveTags(self::stringList($fm['tags'] ?? []), $tagCache));
        }
    }

    /**
     * @param array<string, ParsedDocument> $documents
     * @param array<string, DocTag>         $tagCache
     */
    private function upsertEntries(array $documents, \DateTimeImmutable $now, array &$tagCache): void
    {
        foreach ($documents as $file => $document) {
            $fm = $document->frontmatter;
            $id = self::int($fm['id']);
            $arguments = [
                self::entrySlug($file),
                self::string($fm['title']),
                self::date($fm['date']),
                EntryType::from(self::string($fm['type'])),
                self::string($fm['summary']),
                self::stringList($fm['agents']),
                self::stringList($fm['repos']),
                self::stringList($fm['adrs'] ?? []),
                self::stringList($fm['tickets'] ?? []),
                isset($fm['learning_path']) ? self::int($fm['learning_path']) : null,
                $document->body,
                $this->renderer->render($document->body),
                $now,
            ];

            $entry = $this->entries->find($id);
            if (null === $entry) {
                $entry = new DocEntry($id, ...$arguments);
                $this->entityManager->persist($entry);
            } else {
                $entry->update(...$arguments);
            }

            $entry->syncTags($this->resolveTags(self::stringList($fm['tags'] ?? []), $tagCache));
        }
    }

    /**
     * @param list<string>          $names
     * @param array<string, DocTag> $cache
     *
     * @return list<DocTag>
     */
    private function resolveTags(array $names, array &$cache): array
    {
        $tags = [];
        foreach (array_unique($names) as $name) {
            if (!isset($cache[$name])) {
                $tag = $this->tags->findOneByName($name);
                if (null === $tag) {
                    $tag = new DocTag($name);
                    $this->entityManager->persist($tag);
                }
                $cache[$name] = $tag;
            }
            $tags[] = $cache[$name];
        }

        return $tags;
    }

    /**
     * @param list<string> $adrFiles
     * @param list<string> $entryFiles
     */
    private function deleteVanished(array $adrFiles, array $entryFiles): int
    {
        $keepAdrSlugs = array_map(self::adrSlug(...), $adrFiles);
        $keepEntrySlugs = array_map(self::entrySlug(...), $entryFiles);
        $deleted = 0;

        foreach ($this->adrs->findAll() as $adr) {
            if (!\in_array($adr->getSlug(), $keepAdrSlugs, true)) {
                $this->entityManager->remove($adr);
                ++$deleted;
            }
        }
        foreach ($this->entries->findAll() as $entry) {
            if (!\in_array($entry->getSlug(), $keepEntrySlugs, true)) {
                $this->entityManager->remove($entry);
                ++$deleted;
            }
        }

        return $deleted;
    }

    private function deleteOrphanTags(): void
    {
        foreach ($this->tags->findOrphans() as $tag) {
            $this->entityManager->remove($tag);
        }
    }

    private function refreshSearchVectors(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement(
            "UPDATE doc_entry SET search_vector = to_tsvector('german', title || ' ' || summary || ' ' || body_markdown)",
        );
        $connection->executeStatement(
            "UPDATE doc_adr SET search_vector = to_tsvector('german', title || ' ' || body_markdown)",
        );
    }

    public static function entrySlug(string $file): string
    {
        // 0001-projekt-initialisierung.md → projekt-initialisierung
        return substr(basename($file, '.md'), 5);
    }

    public static function adrSlug(string $file): string
    {
        // ADR-004-docs-plattform.md → ADR-004-docs-plattform
        return basename($file, '.md');
    }

    private static function string(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new \LogicException('Validated frontmatter value is not a string.');
        }

        return $value;
    }

    private static function int(mixed $value): int
    {
        if (!\is_int($value)) {
            throw new \LogicException('Validated frontmatter value is not an integer.');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new \LogicException('Validated frontmatter value is not a list.');
        }

        return array_values(array_map(self::string(...), $value));
    }

    private static function date(mixed $value): \DateTimeImmutable
    {
        return ContentValidator::normalizeDate($value) ?? throw new \LogicException('Validated frontmatter date is invalid.');
    }
}
