<?php

namespace App\Controller\Admin;

use App\Entity\JobCategory;
use App\Form\JobCategoryType;
use App\Repository\JobCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Kategorije poslova (SPEC 8g.1): redosled prevlačenjem, dodavanje i izmena u modalu, deaktivacija umesto brisanja.
 */
#[IsGranted('CATALOG_MANAGE')]
class JobCategoryController extends AbstractController
{
    public function __construct(
        private readonly JobCategoryRepository $categories,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/admin/catalog/categories', name: 'admin_category_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->page($this->createForm(JobCategoryType::class, new JobCategory(), ['action' => $this->generateUrl('admin_category_new')]));
    }

    #[Route('/admin/catalog/categories/new', name: 'admin_category_new', methods: ['POST'])]
    #[Route('/admin/catalog/categories/{id}/edit', name: 'admin_category_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function save(Request $request, ?JobCategory $category = null): Response
    {
        $isNew = null === $category;
        $category ??= new JobCategory(position: $this->categories->nextPosition());
        $form = $this->createForm(JobCategoryType::class, $category, [
            'action' => $isNew ? $this->generateUrl('admin_category_new') : $this->generateUrl('admin_category_edit', ['id' => $category->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($category);
            $this->em->flush();
            $this->addFlash('success', sprintf('Kategorija „%s“ je sačuvana.', $category->getName()));

            return $this->redirectToRoute('admin_category_index');
        }

        // greška: ista strana sa otvorenim modalom i porukama
        return $this->page($form, $category, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Route('/admin/catalog/categories/{id}/toggle', name: 'admin_category_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, JobCategory $category): Response
    {
        $this->csrf($request, 'category_toggle_'.$category->getId());
        $category->setActive(!$category->isActive());
        $this->em->flush();
        $this->addFlash('success', sprintf('Kategorija „%s“ je %s.', $category->getName(), $category->isActive() ? 'aktivirana' : 'deaktivirana'));

        return $this->redirectToRoute('admin_category_index');
    }

    #[Route('/admin/catalog/categories/{id}/delete', name: 'admin_category_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, JobCategory $category): Response
    {
        $this->csrf($request, 'category_delete_'.$category->getId());
        if (($this->categories->jobCounts()[$category->getId()] ?? 0) > 0) {
            $this->addFlash('warning', 'Kategorija ima poslove, pa se ne briše. Možeš da je deaktiviraš.');
        } else {
            $this->em->remove($category);
            $this->em->flush();
            $this->addFlash('success', 'Kategorija je obrisana.');
        }

        return $this->redirectToRoute('admin_category_index');
    }

    /**
     * Novi redosled posle prevlačenja: { ids: [3, 1, 2] }
     */
    #[Route('/ajax/job-categories/reorder', name: 'ajax_category_reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        $ids = array_map('intval', (array) ($request->toArray()['ids'] ?? []));
        $byId = [];
        foreach ($this->categories->findAll() as $category) {
            $byId[$category->getId()] = $category;
        }
        foreach (array_values(array_unique($ids)) as $position => $id) {
            ($byId[$id] ?? null)?->setPosition($position);
        }
        $this->em->flush();

        return new JsonResponse(['success' => true, 'data' => null, 'message' => 'Redosled je sačuvan.']);
    }

    private function page(\Symfony\Component\Form\FormInterface $form, ?JobCategory $editing = null, int $status = Response::HTTP_OK): Response
    {
        return $this->render('admin/catalog/categories.html.twig', [
            'categories' => $this->categories->findOrdered(),
            'counts' => $this->categories->jobCounts(),
            'form' => $form,
            'editing' => $editing,
        ], new Response(status: $status));
    }

    private function csrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Neispravan CSRF token.');
        }
    }
}
