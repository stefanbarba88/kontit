<?php

namespace App\Controller\Admin;

use App\Entity\DocumentCategory;
use App\Repository\DocumentCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Podešavanja → Kategorije dokumenata (SPEC 8l): dodavanje, preimenovanje, redosled i deaktivacija (bez brisanja).
 */
#[Route('/admin/document-categories')]
#[IsGranted('CATALOG_MANAGE')]
class DocumentCategoryController extends AbstractController
{
    public function __construct(
        private readonly DocumentCategoryRepository $categories,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'admin_document_category_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/document_category/index.html.twig', ['categories' => $this->categories->findOrdered()]);
    }

    #[Route('/save', name: 'admin_document_category_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('document_categories', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Neispravan CSRF token.');
        }
        $id = $request->request->getInt('id');
        $category = $id ? $this->categories->find($id) : new DocumentCategory(position: \count($this->categories->findAll()));
        if (null === $category) {
            throw $this->createNotFoundException();
        }
        $category->setName(trim($request->request->getString('name')))->setPosition($request->request->getInt('position', $category->getPosition()));
        if ($request->request->has('toggle')) {
            $category->setActive(!$category->isActive());
        }
        $errors = $this->validator->validate($category);
        if (\count($errors) > 0) {
            $this->addFlash('danger', (string) $errors->get(0)->getMessage());
        } else {
            $this->em->persist($category);
            $this->em->flush();
            $this->addFlash('success', sprintf('Kategorija „%s“ je sačuvana.', $category->getName()));
        }

        return $this->redirectToRoute('admin_document_category_index');
    }
}
