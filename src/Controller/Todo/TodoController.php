<?php

namespace App\Controller\Todo;

use App\Entity\User;
use App\Service\TodoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Moja lista, cela strana. Svako vidi samo svoje stavke (TodoService radi nad ulogovanim korisnikom, bez ID-a vlasnika u URL-u).
 */
class TodoController extends AbstractController
{
    #[Route('/moja-lista', name: 'todo_index', methods: ['GET'])]
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
