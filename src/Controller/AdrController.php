<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\DocAdrRepository;
use App\Repository\DocEntryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdrController extends AbstractController
{
    public function __construct(
        private readonly DocAdrRepository $adrs,
        private readonly DocEntryRepository $entries,
    ) {}

    #[Route('/adr', name: 'adr_list', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('adr/index.html.twig', [
            'adrs' => $this->adrs->findAllOrdered(),
        ]);
    }

    #[Route('/adr/{id}', name: 'adr', requirements: ['id' => 'ADR-\d{3}'], methods: ['GET'])]
    public function show(string $id): Response
    {
        $adr = $this->adrs->find($id)
            ?? throw $this->createNotFoundException(\sprintf('%s nicht gefunden.', $id));

        return $this->render('adr/show.html.twig', [
            'adr' => $adr,
            'adrTitles' => $this->adrs->findTitles(),
            'relatedEntries' => $this->entries->findByAdr($id),
        ]);
    }
}
