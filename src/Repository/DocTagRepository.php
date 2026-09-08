<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DocTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocTag>
 */
final class DocTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocTag::class);
    }

    public function findOneByName(string $name): ?DocTag
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * Every tag with the number of entries and ADRs using it, alphabetically.
     *
     * @return list<array{name: string, entries: int, adrs: int}>
     */
    public function findAllWithCounts(): array
    {
        $sql = <<<'SQL'
            SELECT t.name,
                   (SELECT COUNT(*) FROM doc_entry_tag et WHERE et.doc_tag_id = t.id) AS entries,
                   (SELECT COUNT(*) FROM doc_adr_tag at WHERE at.doc_tag_id = t.id)   AS adrs
            FROM doc_tag t
            ORDER BY t.name
            SQL;

        $rows = [];
        foreach ($this->getEntityManager()->getConnection()->fetchAllAssociative($sql) as $row) {
            $rows[] = [
                'name' => Row::string($row, 'name'),
                'entries' => Row::int($row, 'entries'),
                'adrs' => Row::int($row, 'adrs'),
            ];
        }

        return $rows;
    }

    /**
     * Tags that are neither attached to an entry nor to an ADR.
     *
     * @return list<DocTag>
     */
    public function findOrphans(): array
    {
        /** @var list<DocTag> $tags */
        $tags = $this->createQueryBuilder('t')
            ->where('NOT EXISTS (SELECT 1 FROM App\Entity\DocEntry e JOIN e.tags et WHERE et = t)')
            ->andWhere('NOT EXISTS (SELECT 1 FROM App\Entity\DocAdr a JOIN a.tags at WHERE at = t)')
            ->getQuery()
            ->getResult();

        return $tags;
    }
}
