<?php

namespace App\Controller\Ajax;

use App\Http\Query;
use App\Service\ClientJobMatrixService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Matrica "Pregled poslova po klijentima" (SPEC 8r): osvežavanje tabele bez učitavanja strane.
 * Ruta koristi ClientJobMatrixService, pa važi ista vidljivost kao na početnoj i u listi zadataka.
 */
class MatrixController extends AbstractAjaxController
{
    public const PER_PAGE_BLOCK = 15;
    public const PER_PAGE_PAGE = 50;

    public function __construct(private readonly ClientJobMatrixService $matrix)
    {
    }

    /** Strana 1 vraća ceo sadržaj tabele (body), a strane posle nje samo redove i karte koji se dodaju. */
    #[Route('/ajax/matrix', name: 'ajax_matrix', methods: ['GET'])]
    public function table(Request $request): JsonResponse
    {
        $full = Query::bool($request->query, 'full');
        $page = max(1, Query::int($request->query, 'page', 1));
        $view = $this->matrix->build($this->matrix->filters($request->query->all()), $this->actor(), $page, $full ? self::PER_PAGE_PAGE : self::PER_PAGE_BLOCK);
        $vars = ['mx' => $view, 'full' => $full];

        $data = [
            'total' => $view->total,
            'page' => $page,
            'hasMore' => $view->hasMore(),
        ];
        if (1 === $page) {
            $data['body'] = $this->renderView('matrix/_body.html.twig', $vars);
        } else {
            $data['rows'] = $this->renderView('matrix/_more_rows.html.twig', $vars);
            $data['cards'] = $this->renderView('matrix/_more_cards.html.twig', $vars);
            $data['foot'] = $this->renderView('matrix/_foot.html.twig', $vars);
        }

        return $this->ok($data);
    }
}
