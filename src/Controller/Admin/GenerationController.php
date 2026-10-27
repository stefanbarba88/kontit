<?php

namespace App\Controller\Admin;

use App\Service\TaskGenerationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/generation')]
#[IsGranted('CATALOG_MANAGE')]
class GenerationController extends AbstractController
{
    #[Route('', name: 'admin_generation', methods: ['GET'])]
    public function index(TaskGenerationService $generator): Response
    {
        $preview = $generator->preview(30);

        return $this->render('admin/generation/index.html.twig', [
            'preview' => $preview,
            'dueNow' => \count(array_filter($preview, static fn (array $r) => !$r['exists'] && $r['generateOn'] <= new \DateTimeImmutable('today'))),
        ]);
    }

    #[Route('/run', name: 'admin_generation_run', methods: ['POST'])]
    public function run(Request $request, TaskGenerationService $generator): Response
    {
        if (!$this->isCsrfTokenValid('generation_run', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Neispravan CSRF token.');
        }

        $result = $generator->generate($this->getUser());
        $this->addFlash('success', sprintf('Napravljeno zadataka: %d (preskočeno, već postoje: %d).', $result['created'], $result['skipped']));
        foreach ($result['errors'] as $error) {
            $this->addFlash('warning', $error);
        }

        return $this->redirectToRoute('admin_generation');
    }
}
