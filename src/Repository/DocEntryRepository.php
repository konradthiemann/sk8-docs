<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DocEntry;
use App\Entity\EntryType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocEntry>
 */
final class DocEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocEntry::class);
    }

    public function findOneBySlug(string $slug): ?DocEntry
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Chronicle: newest first, optionally narrowed by type, repo and tag.
     *
     * @return list<DocEntry>
     */
    public function findForChronicle(?EntryType $type = null, ?string $repo = null, ?string $tag = null): array
    {
        $qb = $this->newestFirst();

        if (null !== $type) {
            $qb->andWhere('e.type = :type')->setParameter('type', $type->value);
        }
        if (null !== $repo && '' !== $repo) {
            $this->whereRepo($qb, $repo);
        }
        if (null !== $tag && '' !== $tag) {
            $qb->join('e.tags', 't')->andWhere('t.name = :tag')->setParameter('tag', $tag);
        }

        /** @var list<DocEntry> $entries */
        $entries = $qb->getQuery()->getResult();

        return $entries;
    }

    /**
     * @return list<DocEntry>
     */
    public function findByRepo(string $repo): array
    {
        /** @var list<DocEntry> $entries */
        $entries = $this->whereRepo($this->newestFirst(), $repo)->getQuery()->getResult();

        return $entries;
    }

    /**
     * @return list<DocEntry>
     */
    public function findByAdr(string $adrId): array
    {
        $qb = $this->newestFirst()
            ->andWhere('JSONB_CONTAINS(e.adrs, :adr) = TRUE')
            ->setParameter('adr', json_encode([$adrId], \JSON_THROW_ON_ERROR));

        /** @var list<DocEntry> $entries */
        $entries = $qb->getQuery()->getResult();

        return $entries;
    }

    /**
     * Curated reading order (frontmatter `learning_path`), lowest position first.
     *
     * @return list<DocEntry>
     */
    public function findLearningPath(): array
    {
        /** @var list<DocEntry> $entries */
        $entries = $this->createQueryBuilder('e')
            ->where('e.learningPath IS NOT NULL')
            ->orderBy('e.learningPath', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $entries;
    }

    /**
     * Distinct repositories mentioned in entries with the number of entries each.
     *
     * @return list<array{name: string, entries: int}>
     */
    public function findReposWithCounts(): array
    {
        $sql = <<<'SQL'
            SELECT r.name, COUNT(*) AS entries
            FROM doc_entry e, jsonb_array_elements_text(e.repos) AS r(name)
            GROUP BY r.name
            ORDER BY r.name
            SQL;

        $rows = [];
        foreach ($this->getEntityManager()->getConnection()->fetchAllAssociative($sql) as $row) {
            $rows[] = ['name' => Row::string($row, 'name'), 'entries' => Row::int($row, 'entries')];
        }

        return $rows;
    }

    /**
     * @return array<string, int> type value => number of entries
     */
    public function countByType(): array
    {
        /** @var list<array{type: string, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('e.type AS type', 'COUNT(e.id) AS total')
            ->groupBy('e.type')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $type = $row['type'] instanceof EntryType ? $row['type']->value : (string) $row['type'];
            $counts[$type] = (int) $row['total'];
        }

        return $counts;
    }

    private function newestFirst(): QueryBuilder
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.date', 'DESC')
            ->addOrderBy('e.id', 'DESC');
    }

    private function whereRepo(QueryBuilder $qb, string $repo): QueryBuilder
    {
        return $qb
            ->andWhere('JSONB_CONTAINS(e.repos, :repo) = TRUE')
            ->setParameter('repo', json_encode([$repo], \JSON_THROW_ON_ERROR));
    }
}
