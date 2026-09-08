<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DocAdrRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One architecture decision record (`content/adr/ADR-NNN-slug.md`), indexed from Markdown (ADR-004).
 */
#[ORM\Entity(repositoryClass: DocAdrRepository::class)]
#[ORM\Table(name: 'doc_adr')]
#[ORM\Index(name: 'idx_doc_adr_search', columns: ['search_vector'])]
class DocAdr
{
    #[ORM\Id]
    #[ORM\Column(length: 16)]
    private string $id;

    #[ORM\Column(length: 200, unique: true)]
    private string $slug;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 32, enumType: AdrStatus::class)]
    private AdrStatus $status;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $agents;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $supersedes;

    #[ORM\Column(name: 'superseded_by', length: 16, nullable: true)]
    private ?string $supersededBy;

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
    #[ORM\JoinTable(name: 'doc_adr_tag')]
    #[ORM\JoinColumn(name: 'doc_adr_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'doc_tag_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $tags;

    /**
     * @param list<string> $agents
     */
    public function __construct(
        string $id,
        string $slug,
        string $title,
        AdrStatus $status,
        \DateTimeImmutable $date,
        array $agents,
        ?string $supersedes,
        ?string $supersededBy,
        string $bodyMarkdown,
        string $bodyHtml,
        \DateTimeImmutable $importedAt,
    ) {
        $this->id = $id;
        $this->tags = new ArrayCollection();
        $this->update($slug, $title, $status, $date, $agents, $supersedes, $supersededBy, $bodyMarkdown, $bodyHtml, $importedAt);
    }

    /**
     * @param list<string> $agents
     */
    public function update(
        string $slug,
        string $title,
        AdrStatus $status,
        \DateTimeImmutable $date,
        array $agents,
        ?string $supersedes,
        ?string $supersededBy,
        string $bodyMarkdown,
        string $bodyHtml,
        \DateTimeImmutable $importedAt,
    ): void {
        $this->slug = $slug;
        $this->title = $title;
        $this->status = $status;
        $this->date = $date;
        $this->agents = $agents;
        $this->supersedes = $supersedes;
        $this->supersededBy = $supersededBy;
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

    public function getId(): string
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

    public function getStatus(): AdrStatus
    {
        return $this->status;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    /** @return list<string> */
    public function getAgents(): array
    {
        return $this->agents;
    }

    public function getSupersedes(): ?string
    {
        return $this->supersedes;
    }

    public function getSupersededBy(): ?string
    {
        return $this->supersededBy;
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
