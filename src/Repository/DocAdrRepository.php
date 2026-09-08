<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DocAdr;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocAdr>
 */
final class DocAdrRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocAdr::class);
    }

    /**
     * @return list<DocAdr>
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['id' => 'ASC']);
    }

    /**
     * @return list<DocAdr>
     */
    public function findByTag(string $tag): array
    {
        /** @var list<DocAdr> $adrs */
        $adrs = $this->createQueryBuilder('a')
            ->join('a.tags', 't')
            ->where('t.name = :tag')
            ->setParameter('tag', $tag)
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $adrs;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, DocAdr> keyed by id
     */
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $result = [];
        foreach ($this->findBy(['id' => $ids], ['id' => 'ASC']) as $adr) {
            $result[$adr->getId()] = $adr;
        }

        return $result;
    }

    /**
     * @return array<string, string> id => title, for link labels
     */
    public function findTitles(): array
    {
        /** @var list<array{id: string, title: string}> $rows */
        $rows = $this->createQueryBuilder('a')
            ->select('a.id', 'a.title')
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'title', 'id');
    }
}
