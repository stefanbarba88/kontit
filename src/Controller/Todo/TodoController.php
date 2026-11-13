<?php

namespace App\Controller\Todo;

use App\Entity\User;
use App\Service\TodoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Podsetnik, cela strana. Svako vidi samo svoje stavke (TodoService radi nad ulogovanim korisnikom, bez ID-a vlasnika u URL-u).
 */
class TodoController extends AbstractController
{
    /** Stari URL (Podsetnik se zvao Moja lista): trajno preusmeravanje */
    #[Route('/moja-lista', name: 'todo_legacy', methods: ['GET'])]
    public function legacy(Request $request): Response
    {
        return $this->redirectToRoute('todo_index', $request->query->all(), Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/podsetnik', name: 'todo_index', methods: ['GET'])]
    public function index(Request $request, TodoService $todo): Response
    {
        $me = $this->getUser();
        \assert($me instanceof User);
        $query = trim($request->query->getString('q'));

        return $this->render('todo/index.html.twig', [
            'sections' => '' === $query ? $todo->sections($me) : null,
            'found' => '' === $query ? [] : $todo->search($me, $query),
            'query' => $query,
        ]);
    }
}
