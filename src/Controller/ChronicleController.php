<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EntryType;
use App\Repository\DocAdrRepository;
use App\Repository\DocEntryRepository;
use App\Repository\DocTagRepository;
use App\Service\ContestCountdown;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ChronicleController extends AbstractController
{
    public function __construct(
        private readonly DocEntryRepository $entries,
        private readonly DocAdrRepository $adrs,
        private readonly DocTagRepository $tags,
        private readonly ContestCountdown $countdown,
    ) {}

    #[Route('/', name: 'chronicle', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $typeParam = $request->query->getString('type');
        $repo = $request->query->getString('repo');
        $tag = $request->query->getString('tag');
        $type = '' !== $typeParam ? EntryType::tryFrom($typeParam) : null;

        // An unknown type yields no entries instead of silently showing everything.
        $entries = '' !== $typeParam && null === $type
            ? []
            : $this->entries->findForChronicle($type, $repo, $tag);

        return $this->render('chronicle/index.html.twig', [
            'entries' => $entries,
            'adrTitles' => $this->adrs->findTitles(),
            'filter' => ['type' => $typeParam, 'repo' => $repo, 'tag' => $tag],
            'types' => EntryType::cases(),
            'repos' => $this->entries->findReposWithCounts(),
            'tags' => $this->tags->findAllWithCounts(),
            'countByType' => $this->entries->countByType(),
            'adrCount' => $this->adrs->count([]),
            'daysLeft' => $this->countdown->daysLeft(),
            'contestDate' => $this->countdown->getContestDate(),
        ]);
    }
}
