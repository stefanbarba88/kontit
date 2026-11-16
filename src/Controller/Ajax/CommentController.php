<?php

namespace App\Controller\Ajax;

use App\Entity\Task;
use App\Entity\TaskComment;
use App\Service\TaskService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class CommentController extends AbstractAjaxController
{
    public function __construct(private readonly TaskService $tasks)
    {
    }

    #[Route('/ajax/tasks/{id}/comments', name: 'ajax_comment_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function add(Request $request, Task $task): JsonResponse
    {
        $this->denyAccessUnlessGranted('TASK_VIEW', $task);
        $this->denyAccessUnlessGranted('TASK_COMMENT', $task);

        try {
            $comment = $this->tasks->addComment($task, $this->actor(), (string) ($this->payload($request)['body'] ?? ''));
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage());
        }

        return $this->ok($this->state($comment), 'Komentar je dodat.');
    }

    #[Route('/ajax/comments/{id}/edit', name: 'ajax_comment_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(Request $request, TaskComment $comment): JsonResponse
    {
        $this->denyAccessUnlessGranted('TASK_VIEW', $comment->getTask());
        $this->denyAccessUnlessGranted('TASK_COMMENT', $comment->getTask());

        try {
            $this->tasks->editComment($comment, $this->actor(), (string) ($this->payload($request)['body'] ?? ''));
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 403);
        }

        return $this->ok($this->state($comment), 'Komentar je izmenjen.');
    }

    /**
     * @return array<string, mixed>
     */
    private function state(TaskComment $comment): array
    {
        return ['html' => $this->renderView('task/_comment.html.twig', ['comment' => $comment])];
    }
}
