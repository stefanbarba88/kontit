<?php

namespace App\Controller\Ajax;

use App\Entity\Client;
use App\Http\Query;
use App\Entity\JobTemplate;
use App\Service\ClientJobMatrixService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Matrica "Pregled poslova po klijentima" (SPEC 8r): osvežavanje tabele bez učitavanja strane i zadaci jedne ćelije za SweetAlert prozor.
 * Obe rute koriste ClientJobMatrixService, pa važi ista vidljivost kao na početnoj i u listi zadataka.
 */
class MatrixController extends AbstractAjaxController
{
    public const PER_PAGE_BLOCK = 15;
    public const PER_PAGE_PAGE = 50;

    public function __construct(private readonly ClientJobMatrixService $matrix, private readonly EntityManagerInterface $em)
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
            'counters' => $this->renderView('matrix/_counters.html.twig', $vars),
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

    /** job: id posla (JobTemplate); nepoznat posao ili nevidljiv klijent je 404 */
    #[Route('/ajax/matrix/cell', name: 'ajax_matrix_cell', methods: ['GET'])]
    public function cell(Request $request): JsonResponse
    {
        $client = $this->em->find(Client::class, Query::id($request->query, 'client') ?? 0);
        if (!$client instanceof Client || !$this->isGranted('CLIENT_VIEW', $client)) {
            throw $this->createNotFoundException();
        }
        $job = $this->em->find(JobTemplate::class, Query::id($request->query, 'job') ?? 0);
        if (!$job instanceof JobTemplate) {
            throw $this->createNotFoundException();
        }
        $filters = $this->matrix->filters(['period' => (string) $request->query->get('period', '')]);

        return $this->ok($this->matrix->cellDetails($this->actor(), $client, $job, $filters) + ['clientName' => $client->getShortName(), 'jobName' => $job->getName()]);
    }
}
