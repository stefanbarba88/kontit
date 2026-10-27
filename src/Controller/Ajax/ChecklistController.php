<?php

namespace App\Controller\Ajax;

use App\Entity\ChecklistItem;
use App\Entity\Task;
use App\Repository\UserRepository;
use App\Service\TaskService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ChecklistController extends AbstractAjaxController
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('/ajax/tasks/{id}/checklist', name: 'ajax_checklist_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function add(Request $request, Task $task): JsonResponse
    {
        $this->denyAccessUnlessGranted('TASK_EDIT', $task);
        $data = $this->payload($request);

        try {
            $assigned = !empty($data['assignedTo']) ? $this->users->find((int) $data['assignedTo']) : null;
            $item = $this->tasks->addChecklistItem($task, (string) ($data['text'] ?? ''), $assigned, $this->actor());
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage());
        }

        return $this->ok($this->state($task, $item), 'Stavka je dodata.');
    }

    #[Route('/ajax/checklist/{id}/toggle', name: 'ajax_checklist_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, ChecklistItem $item): JsonResponse
    {
        $task = $item->getTask();
        $this->denyAccessUnlessGranted('TASK_COMPLETE', $task);

        try {
            $this->tasks->setChecklistItemDone($item, (bool) ($this->payload($request)['done'] ?? false), $this->actor());
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 403);
        }

        return $this->ok($this->state($task, $item));
    }

    #[Route('/ajax/checklist/{id}/assign', name: 'ajax_checklist_assign', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function assign(Request $request, ChecklistItem $item): JsonResponse
    {
        $task = $item->getTask();
        $this->denyAccessUnlessGranted('TASK_EDIT', $task);
        $userId = $this->payload($request)['userId'] ?? null;

        try {
            $this->tasks->assignChecklistItem($item, $userId ? $this->users->find((int) $userId) : null);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage());
        }

        return $this->ok($this->state($task, $item));
    }

    #[Route('/ajax/checklist/{id}', name: 'ajax_checklist_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(ChecklistItem $item): JsonResponse
    {
        $task = $item->getTask();
        $this->denyAccessUnlessGranted('TASK_EDIT', $task);
        $this->tasks->deleteChecklistItem($item);

        return $this->ok($this->state($task), 'Stavka je obrisana.');
    }

    #[Route('/ajax/tasks/{id}/checklist/reorder', name: 'ajax_checklist_reorder', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reorder(Request $request, Task $task): JsonResponse
    {
        $this->denyAccessUnlessGranted('TASK_EDIT', $task);
        $order = array_map('intval', (array) ($this->payload($request)['order'] ?? []));
        $this->tasks->reorderChecklist($task, $order);

        return $this->ok($this->state($task));
    }

    /**
     * Stanje koje JS treba posle svake izmene: HTML stavke (ako postoji), brojači i status zadatka.
     *
     * @return array<string, mixed>
     */
    private function state(Task $task, ?ChecklistItem $item = null): array
    {
        return [
            'html' => null !== $item && null !== $item->getId() && $task->getChecklistItems()->contains($item)
                ? $this->renderView('task/_checklist_item.html.twig', ['item' => $item, 'task' => $task])
                : null,
            'done' => $task->getChecklistDone(),
            'total' => $task->getChecklistTotal(),
            'status' => $task->getStatus()->value,
        ];
    }
}
