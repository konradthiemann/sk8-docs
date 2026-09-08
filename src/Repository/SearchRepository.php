<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

/**
 * PostgreSQL full-text search over entries and ADRs (`search_vector` columns, German config).
 * Plain DBAL instead of DQL because ts_rank/ts_headline have no DQL equivalent.
 */
final class SearchRepository
{
    private const string HEADLINE_OPTIONS = 'MaxWords=35, MinWords=15, MaxFragments=2, FragmentDelimiter=" … ", StartSel=[[, StopSel=]]';

    public function __construct(private readonly Connection $connection) {}

    /**
     * @return list<SearchHit>
     */
    public function search(string $query, int $limit = 50): array
    {
        $query = trim($query);
        if ('' === $query) {
            return [];
        }

        $sql = <<<SQL
            WITH q AS (SELECT plainto_tsquery('german', :query) AS tsq)
            SELECT kind, key, title, date, rank, snippet FROM (
                SELECT 'entry' AS kind, e.slug AS key, e.title, e.date,
                       ts_rank(e.search_vector, q.tsq) AS rank,
                       ts_headline('german', e.summary || ' ' || e.body_markdown, q.tsq, :options) AS snippet
                FROM doc_entry e, q
                WHERE e.search_vector @@ q.tsq
                UNION ALL
                SELECT 'adr' AS kind, a.id AS key, a.title, a.date,
                       ts_rank(a.search_vector, q.tsq) AS rank,
                       ts_headline('german', a.body_markdown, q.tsq, :options) AS snippet
                FROM doc_adr a, q
                WHERE a.search_vector @@ q.tsq
            ) hits
            ORDER BY rank DESC, date DESC
            LIMIT :limit
            SQL;

        $rows = $this->connection->fetchAllAssociative($sql, [
            'query' => $query,
            'options' => self::HEADLINE_OPTIONS,
            'limit' => $limit,
        ], [
            'limit' => \Doctrine\DBAL\ParameterType::INTEGER,
        ]);

        $hits = [];
        foreach ($rows as $row) {
            $hits[] = new SearchHit(
                Row::string($row, 'kind'),
                Row::string($row, 'key'),
                Row::string($row, 'title'),
                new \DateTimeImmutable(Row::string($row, 'date')),
                Row::float($row, 'rank'),
                Row::string($row, 'snippet'),
            );
        }

        return $hits;
    }
}
