<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\DocAdrRepository;
use App\Repository\DocEntryRepository;
use App\Repository\DocTagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TagController extends AbstractController
{
    public function __construct(
        private readonly DocTagRepository $tags,
        private readonly DocEntryRepository $entries,
        private readonly DocAdrRepository $adrs,
    ) {}

    #[Route('/tags', name: 'tag_list', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('tag/index.html.twig', [
            'tags' => $this->tags->findAllWithCounts(),
        ]);
    }

    #[Route('/tags/{name}', name: 'tag', requirements: ['name' => '[^/]+'], methods: ['GET'])]
    public function show(string $name): Response
    {
        $tag = $this->tags->findOneByName($name)
            ?? throw $this->createNotFoundException(\sprintf('Tag "%s" nicht gefunden.', $name));

        return $this->render('tag/show.html.twig', [
            'tag' => $tag,
            'entries' => $this->entries->findForChronicle(null, null, $tag->getName()),
            'adrs' => $this->adrs->findByTag($tag->getName()),
            'adrTitles' => $this->adrs->findTitles(),
        ]);
    }
}
