<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\DocAdrRepository;
use App\Repository\DocEntryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RepoController extends AbstractController
{
    public function __construct(
        private readonly DocEntryRepository $entries,
        private readonly DocAdrRepository $adrs,
    ) {}

    #[Route('/repos', name: 'repo_list', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('repo/index.html.twig', [
            'repos' => $this->entries->findReposWithCounts(),
        ]);
    }

    #[Route('/repos/{name}', name: 'repo', requirements: ['name' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(string $name): Response
    {
        $entries = $this->entries->findByRepo($name);
        if ([] === $entries) {
            throw $this->createNotFoundException(\sprintf('Repo "%s" hat keine Einträge.', $name));
        }

        return $this->render('repo/show.html.twig', [
            'repo' => $name,
            'entries' => $entries,
            'adrTitles' => $this->adrs->findTitles(),
        ]);
    }
}
