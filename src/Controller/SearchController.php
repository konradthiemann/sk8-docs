<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\SearchRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    public function __construct(private readonly SearchRepository $search) {}

    #[Route('/suche', name: 'search', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = trim($request->query->getString('q'));

        return $this->render('search/index.html.twig', [
            'query' => $query,
            'hits' => '' === $query ? [] : $this->search->search($query),
        ]);
    }
}
