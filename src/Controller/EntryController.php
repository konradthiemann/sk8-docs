<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\DocAdrRepository;
use App\Repository\DocEntryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EntryController extends AbstractController
{
    public function __construct(
        private readonly DocEntryRepository $entries,
        private readonly DocAdrRepository $adrs,
    ) {}

    #[Route('/eintrag/{slug}', name: 'entry', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(string $slug): Response
    {
        $entry = $this->entries->findOneBySlug($slug)
            ?? throw $this->createNotFoundException(\sprintf('Eintrag "%s" nicht gefunden.', $slug));

        return $this->render('entry/show.html.twig', [
            'entry' => $entry,
            'adrTitles' => $this->adrs->findTitles(),
        ]);
    }

    #[Route('/lernpfad', name: 'learning_path', methods: ['GET'])]
    public function learningPath(): Response
    {
        return $this->render('entry/learning_path.html.twig', [
            'entries' => $this->entries->findLearningPath(),
            'adrTitles' => $this->adrs->findTitles(),
        ]);
    }
}
