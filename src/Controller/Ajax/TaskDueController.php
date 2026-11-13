<?php

namespace App\Controller\Ajax;

use App\Entity\Task;
use App\Service\TaskDueService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Rok i raspored" na zadatku (SPEC 8g.5). Rok menja ko sme da menja zadatak (TASK_EDIT).
 * Prilagođavanje rasporeda posla za buduće periode traži i pravo upravljanja poslovima klijenta (CATALOG_MANAGE).
 * Telo: { dueDate: "Y-m-d", future: bool, lastDay: bool } ili { reset: true } za povratak na podrazumevani rok.
 */
class TaskDueController extends AbstractAjaxController
{
    public function __construct(private readonly TaskDueService $due)
    {
    }

    #[Route('/ajax/tasks/{id}/due-preview', name: 'ajax_task_due_preview', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('TASK_EDIT', 'task')]
    public function preview(Request $request, Task $task): JsonResponse
    {
        $data = $this->payload($request);
        $date = $this->date($data['dueDate'] ?? null);
        if (null === $date) {
            return $this->fail('Unesi ispravan datum.', 422);
        }

        return $this->ok($this->due->preview($task, $date, (bool) ($data['lastDay'] ?? false)));
    }

    #[Route('/ajax/tasks/{id}/due-change', name: 'ajax_task_due_change', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('TASK_EDIT', 'task')]
    public function change(Request $request, Task $task): JsonResponse
    {
        $data = $this->payload($request);
        try {
            if (!empty($data['reset'])) {
                $this->due->resetDue($task);

                return $this->ok(null, 'Rok je vraćen na podrazumevani.');
            }
            $date = $this->date($data['dueDate'] ?? null);
            if (null === $date) {
                return $this->fail('Unesi ispravan datum.', 422);
            }
            $future = (bool) ($data['future'] ?? false);
            if ($future && !$this->isGranted('CATALOG_MANAGE')) {
                return $this->fail('Nemaš pravo da menjaš raspored posla klijenta.', 403);
            }
            $impact = $this->due->setDue($task, $date, $future, (bool) ($data['lastDay'] ?? false), $this->actor());
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $message = 'Rok je promenjen samo za ovaj zadatak.';
        if (null !== $impact) {
            $message = 'Rok je promenjen, a raspored za klijenta je sačuvan.'.($impact->movedCount() > 0 ? sprintf(' Rok je promenjen na još %d otvorenih zadataka.', $impact->movedCount()) : '');
        }

        return $this->ok($impact?->toArray(), $message);
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $date || $date->format('Y-m-d') !== $value ? null : $date;
    }
}
