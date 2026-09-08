<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DocEntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One chronicle entry (`content/entries/NNNN-slug.md`). The Markdown file is the source of
 * truth; this row is a rebuildable index (ADR-004).
 */
#[ORM\Entity(repositoryClass: DocEntryRepository::class)]
#[ORM\Table(name: 'doc_entry')]
#[ORM\Index(name: 'idx_doc_entry_date', columns: ['date'])]
#[ORM\Index(name: 'idx_doc_entry_search', columns: ['search_vector'])]
class DocEntry
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(length: 200, unique: true)]
    private string $slug;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    #[ORM\Column(length: 32, enumType: EntryType::class)]
    private EntryType $type;

    #[ORM\Column(type: Types::TEXT)]
    private string $summary;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $agents;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $repos;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $adrs;

    #[ORM\Column(name: 'learning_path', nullable: true)]
    private ?int $learningPath;

    #[ORM\Column(name: 'body_markdown', type: Types::TEXT)]
    private string $bodyMarkdown;

    #[ORM\Column(name: 'body_html', type: Types::TEXT)]
    private string $bodyHtml;

    #[ORM\Column(name: 'imported_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $importedAt;

    /** Maintained by native SQL in the importer, never written through the ORM. */
    #[ORM\Column(name: 'search_vector', type: 'tsvector', nullable: true, insertable: false, updatable: false)]
    private ?string $searchVector = null;

    /** @var Collection<int, DocTag> */
    #[ORM\ManyToMany(targetEntity: DocTag::class)]
    #[ORM\JoinTable(name: 'doc_entry_tag')]
    #[ORM\JoinColumn(name: 'doc_entry_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'doc_tag_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $tags;

    /**
     * @param list<string> $agents
     * @param list<string> $repos
     * @param list<string> $adrs
     */
    public function __construct(
        int $id,
        string $slug,
        string $title,
        \DateTimeImmutable $date,
        EntryType $type,
        string $summary,
        array $agents,
        array $repos,
        array $adrs,
        ?int $learningPath,
        string $bodyMarkdown,
        string $bodyHtml,
        \DateTimeImmutable $importedAt,
    ) {
        $this->id = $id;
        $this->tags = new ArrayCollection();
        $this->update($slug, $title, $date, $type, $summary, $agents, $repos, $adrs, $learningPath, $bodyMarkdown, $bodyHtml, $importedAt);
    }

    /**
     * Overwrites every value from a re-parsed file (upsert by id).
     *
     * @param list<string> $agents
     * @param list<string> $repos
     * @param list<string> $adrs
     */
    public function update(
        string $slug,
        string $title,
        \DateTimeImmutable $date,
        EntryType $type,
        string $summary,
        array $agents,
        array $repos,
        array $adrs,
        ?int $learningPath,
        string $bodyMarkdown,
        string $bodyHtml,
        \DateTimeImmutable $importedAt,
    ): void {
        $this->slug = $slug;
        $this->title = $title;
        $this->date = $date;
        $this->type = $type;
        $this->summary = $summary;
        $this->agents = $agents;
        $this->repos = $repos;
        $this->adrs = $adrs;
        $this->learningPath = $learningPath;
        $this->bodyMarkdown = $bodyMarkdown;
        $this->bodyHtml = $bodyHtml;
        $this->importedAt = $importedAt;
    }

    /**
     * @param iterable<DocTag> $tags
     */
    public function syncTags(iterable $tags): void
    {
        $this->tags->clear();
        foreach ($tags as $tag) {
            $this->tags->add($tag);
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getType(): EntryType
    {
        return $this->type;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    /** @return list<string> */
    public function getAgents(): array
    {
        return $this->agents;
    }

    /** @return list<string> */
    public function getRepos(): array
    {
        return $this->repos;
    }

    /** @return list<string> */
    public function getAdrs(): array
    {
        return $this->adrs;
    }

    public function getLearningPath(): ?int
    {
        return $this->learningPath;
    }

    public function getBodyMarkdown(): string
    {
        return $this->bodyMarkdown;
    }

    public function getBodyHtml(): string
    {
        return $this->bodyHtml;
    }

    public function getImportedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }

    /** @return Collection<int, DocTag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }
}
