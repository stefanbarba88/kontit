<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserDocument;
use App\Repository\DocumentCategoryRepository;
use App\Repository\UserAbsenceRepository;
use App\Repository\UserDocumentRepository;
use App\Service\UserDocumentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Dokumenti korisnika (SPEC 8l): lista po kategorijama, otpremanje (FilePond), pregled kroz kontroler (nikad direktan link),
 * korpa (soft delete) i trajno brisanje (admin).
 */
class UserDocumentController extends AbstractController
{
    public function __construct(
        private readonly UserDocumentService $service,
        private readonly UserDocumentRepository $documents,
    ) {
    }

    #[Route('/users/{id}/documents', name: 'user_documents', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function index(Request $request, User $user, DocumentCategoryRepository $categories, UserAbsenceRepository $absences): Response
    {
        $this->denyAccessUnlessGranted('USER_DOCUMENTS_LIST', $user);
        $trash = $request->query->getBoolean('trash') && $this->isGranted('ROLE_ADMIN');
        $categoryId = $request->query->getInt('category') ?: null;

        // šef vidi samo ono što sme (doznake svog tima), pa se lista filtrira Voterom
        $visible = array_values(array_filter($this->documents->findForUser($user, $trash, $categoryId), fn (UserDocument $d) => $this->isGranted('USER_DOCUMENT_VIEW', $d)));
        $groups = [];
        foreach ($visible as $doc) {
            $groups[$doc->getCategory()->getId()] ??= ['category' => $doc->getCategory(), 'documents' => []];
            $groups[$doc->getCategory()->getId()]['documents'][] = $doc;
        }

        return $this->render('document/index.html.twig', [
            'person' => $user,
            'groups' => array_values($groups),
            'categories' => $categories->findOrdered(),
            'activeCategories' => $categories->findOrdered(true),
            'categoryId' => $categoryId,
            'trash' => $trash,
            'canUpload' => $this->isGranted('USER_DOCUMENT_UPLOAD', $user),
            // odsustva ove i prošle godine koja traže dokument, za vezu (npr. doznaka uz bolovanje)
            'absences' => array_values(array_filter(
                $absences->findInPeriod([$user], new \DateTimeImmutable((date('Y') - 1).'-01-01'), new \DateTimeImmutable(date('Y').'-12-31')),
                static fn ($a) => $a->getType()->isRequiresDocument(),
            )),
            'preselectAbsence' => $request->query->getInt('absence') ?: null,
        ]);
    }

    #[Route('/users/{id}/documents', name: 'user_documents_upload', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function upload(Request $request, User $user, DocumentCategoryRepository $categories, UserAbsenceRepository $absences): Response
    {
        $this->denyAccessUnlessGranted('USER_DOCUMENT_UPLOAD', $user);
        if (!$this->isCsrfTokenValid('documents_'.$user->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Neispravan CSRF token.');
        }
        $me = $this->getUser();
        \assert($me instanceof User);

        $category = $categories->find($request->request->getInt('category'));
        $files = $request->files->all('files') ?: array_filter([$request->files->get('file')]);
        $validUntil = null;
        if ($request->request->getString('validUntil')) {
            try {
                $validUntil = new \DateTimeImmutable($request->request->getString('validUntil'));
            } catch (\Exception) {
            }
        }
        $absence = $request->request->getInt('absence') ? $absences->find($request->request->getInt('absence')) : null;

        if (null === $category || !$category->isActive()) {
            $this->addFlash('danger', 'Izaberi kategoriju dokumenta.');
        } elseif ([] === $files) {
            $this->addFlash('danger', 'Izaberi fajl.');
        } else {
            $count = 0;
            foreach ($files as $file) {
                try {
                    $this->service->upload($user, $me, $file, $category, \count($files) > 1 ? '' : $request->request->getString('title'), $validUntil, $request->request->getString('note'), $absence);
                    ++$count;
                } catch (\DomainException $e) {
                    $this->addFlash('danger', sprintf('%s: %s', $file->getClientOriginalName(), $e->getMessage()));
                }
            }
            if ($count) {
                $this->addFlash('success', 1 === $count ? 'Dokument je dodat.' : sprintf('Dodato dokumenata: %d.', $count));
            }
        }

        return $this->redirectToRoute('user_documents', ['id' => $user->getId()]);
    }

    /**
     * Fajl dokumenta, samo kroz Voter (inline za pregled, ?download=1 za preuzimanje).
     */
    #[Route('/documents/{id}/file', name: 'user_document_file', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function serve(Request $request, UserDocument $document): Response
    {
        $this->denyAccessUnlessGranted('USER_DOCUMENT_VIEW', $document);
        if ($document->isDeleted() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException();
        }
        $path = $this->service->path($document);
        if (!is_file($path)) {
            throw $this->createNotFoundException('Fajl ne postoji.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $document->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setContentDisposition($request->query->getBoolean('download') ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE, $document->getOriginalName(), 'dokument');

        return $response;
    }

    #[Route('/documents/{id}/{action}', name: 'user_document_action', requirements: ['id' => '\d+', 'action' => 'delete|restore|purge'], methods: ['POST'])]
    public function action(Request $request, UserDocument $document, string $action): Response
    {
        $this->denyAccessUnlessGranted(match ($action) { 'delete' => 'USER_DOCUMENT_DELETE', default => 'USER_DOCUMENT_PURGE' }, $document);
        if (!$this->isCsrfTokenValid('document_'.$document->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Neispravan CSRF token.');
        }
        $owner = $document->getUser();
        try {
            match ($action) {
                'delete' => $this->service->delete($document),
                'restore' => $this->service->restore($document),
                'purge' => $this->service->purge($document),
            };
            $this->addFlash('success', match ($action) {
                'delete' => 'Dokument je premešten u korpu.',
                'restore' => 'Dokument je vraćen iz korpe.',
                'purge' => 'Dokument je trajno obrisan.',
            });
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('user_documents', ['id' => $owner->getId(), 'trash' => 'delete' === $action ? null : 1]);
    }
}
