<?php

namespace App\Controller;

use App\Enum\HelpGuide;
use App\Service\HelpRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Uputstvo u aplikaciji (SPEC 8o): svako vidi uputstva svoje role i nižih (HelpVoter), sadržaj je Markdown u docs/uputstvo/.
 */
#[IsGranted('ROLE_RADNIK')]
class HelpController extends AbstractController
{
    public function __construct(private readonly HelpRenderer $renderer)
    {
    }

    /** Bez izbora: otvara uputstvo najviše role koju korisnik ima. */
    #[Route('/uputstvo', name: 'help_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('help_show', ['guide' => $this->available()[0]->value]);
    }

    #[Route('/uputstvo/{guide}', name: 'help_show', requirements: ['guide' => 'admin|sef|radnik'], methods: ['GET'])]
    public function show(HelpGuide $guide): Response
    {
        $this->denyAccessUnlessGranted('HELP_VIEW', $guide);

        return $this->render('help/show.html.twig', [
            'guide' => $guide,
            'guides' => $this->available(),
            'doc' => $this->renderer->render($guide),
        ]);
    }

    #[Route('/uputstvo/slike/{file}', name: 'help_image', requirements: ['file' => '[A-Za-z0-9._-]+\.(png|jpe?g|webp|gif)'], methods: ['GET'])]
    public function image(string $file): BinaryFileResponse
    {
        $path = $this->renderer->imagePath($file) ?? throw $this->createNotFoundException();
        $response = new BinaryFileResponse($path);
        $response->setPrivate();
        $response->setMaxAge(3600);

        return $response;
    }

    /**
     * @return list<HelpGuide>
     */
    private function available(): array
    {
        return array_values(array_filter(HelpGuide::cases(), fn (HelpGuide $g) => $this->isGranted('HELP_VIEW', $g)));
    }
}
