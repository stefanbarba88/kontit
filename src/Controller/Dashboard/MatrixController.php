<?php

namespace App\Controller\Dashboard;

use App\Controller\Ajax\MatrixController as MatrixAjax;
use App\Entity\User;
use App\Service\ClientJobMatrixService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Stranica matrice "Pregled poslova po klijentima" (SPEC 8r): ista tabela kao na početnoj, preko cele širine, po 50 klijenata.
 */
class MatrixController extends AbstractController
{
    #[Route('/pregled-poslova', name: 'matrix_index', methods: ['GET'])]
    public function index(Request $request, ClientJobMatrixService $matrix): Response
    {
        $me = $this->getUser();
        \assert($me instanceof User);
        $filters = $matrix->filters($request->query->all());

        return $this->render('matrix/index.html.twig', [
            'mx' => $matrix->build($filters, $me, 1, MatrixAjax::PER_PAGE_PAGE),
            'full' => true,
            'periodOptions' => $matrix->periodOptions($filters),
            'periodLabel' => $matrix->periodLabel($filters),
        ]);
    }
}
