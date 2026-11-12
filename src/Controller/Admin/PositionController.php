<?php

namespace App\Controller\Admin;

use App\Entity\Position;
use App\Form\PositionType;
use App\Repository\PositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Pozicije zaposlenih (nivoi): redosled po senioritetu prevlačenjem, izmena u modalu.
 * Brisanje je dozvoljeno samo za poziciju koja nikad nije korišćena (nema korisnika ni istorije), inače samo deaktivacija.
 * Pozicija NE daje prava pristupa.
 */
#[IsGranted('POSITION_MANAGE')]
class PositionController extends AbstractController
{
    public function __construct(
        private readonly PositionRepository $positions,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/admin/positions', name: 'admin_position_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->page($this->createForm(PositionType::class, new Position(), ['action' => $this->generateUrl('admin_position_new')]));
    }

    #[Route('/admin/positions/new', name: 'admin_position_new', methods: ['POST'])]
    #[Route('/admin/positions/{id}/edit', name: 'admin_position_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function save(Request $request, ?Position $position = null): Response
    {
        $isNew = null === $position;
        $position ??= new Position(level: $this->nextLevel());
        $form = $this->createForm(PositionType::class, $position, [
            'action' => $isNew ? $this->generateUrl('admin_position_new') : $this->generateUrl('admin_position_edit', ['id' => $position->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($position);
            $this->em->flush();
            $this->addFlash('success', sprintf('Pozicija „%s“ je sačuvana.', $position->getName()));

            return $this->redirectToRoute('admin_position_index');
        }

        return $this->page($form, $position, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Route('/admin/positions/{id}/toggle', name: 'admin_position_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, Position $position): Response
    {
        $this->csrf($request, 'position_toggle_'.$position->getId());
        $position->setActive(!$position->isActive());
        $this->em->flush();
        $this->addFlash('success', sprintf('Pozicija „%s“ je %s.', $position->getName(), $position->isActive() ? 'aktivirana' : 'deaktivirana'));

        return $this->redirectToRoute('admin_position_index');
    }

    #[Route('/admin/positions/{id}/delete', name: 'admin_position_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Position $position): Response
    {
        $this->csrf($request, 'position_delete_'.$position->getId());
        if ($this->positions->isUsed($position)) {
            $this->addFlash('warning', 'Pozicija je korišćena (ima korisnike ili istoriju), pa se ne briše. Možeš da je deaktiviraš.');
        } else {
            $this->em->remove($position);
            $this->em->flush();
            $this->addFlash('success', 'Pozicija je obrisana.');
        }

        return $this->redirectToRoute('admin_position_index');
    }

    /**
     * Novi redosled posle prevlačenja: { ids: [3, 1, 2] }. Prva je najniža pozicija.
     */
    #[Route('/ajax/positions/reorder', name: 'ajax_position_reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        $ids = array_map('intval', (array) ($request->toArray()['ids'] ?? []));
        $byId = [];
        foreach ($this->positions->findAll() as $position) {
            $byId[$position->getId()] = $position;
        }
        foreach (array_values(array_unique($ids)) as $index => $id) {
            ($byId[$id] ?? null)?->setLevel($index + 1);
        }
        $this->em->flush();

        return new JsonResponse(['success' => true, 'data' => null, 'message' => 'Redosled je sačuvan.']);
    }

    private function nextLevel(): int
    {
        return 1 + (int) $this->em->createQuery('SELECT COALESCE(MAX(p.level), 0) FROM '.Position::class.' p')->getSingleScalarResult();
    }

    private function csrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Neispravan CSRF token.');
        }
    }

    private function page(FormInterface $form, ?Position $editing = null, int $status = Response::HTTP_OK): Response
    {
        return $this->render('admin/position/index.html.twig', [
            'positions' => $this->positions->findOrdered(),
            'counts' => $this->positions->userCounts(),
            'used' => $this->usedIds(),
            'form' => $form,
            'editing' => $editing,
        ], new Response(status: $status));
    }

    /** @return array<int, true> id pozicija koje su ikad korišćene (korisnici ili istorija) */
    private function usedIds(): array
    {
        $ids = array_keys($this->positions->userCounts());
        $rows = $this->em->getConnection()->fetchFirstColumn('SELECT from_position_id FROM user_position_log WHERE from_position_id IS NOT NULL UNION SELECT to_position_id FROM user_position_log WHERE to_position_id IS NOT NULL');
        foreach (array_merge($ids, $rows) as $id) {
            $used[(int) $id] = true;
        }

        return $used ?? [];
    }
}
