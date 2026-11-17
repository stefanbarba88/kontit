<?php

namespace App\Controller\Ajax;

use App\Entity\Client;
use App\Entity\Task;
use App\Entity\TodoItem;
use App\Enum\TodoRepeat;
use App\Repository\ClientRepository;
use App\Service\ClientService;
use App\Repository\TodoItemRepository;
use App\Service\TodoClock;
use App\Service\TodoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Podsetnik (AJAX). PRIVATNO: svaka stavka se učitava kroz TodoItemRepository::findOwned, pa tuđa stavka daje 404 (nikad 403),
 * a TODO_* voter je dodatna provera. Svaki odgovor nosi novo stanje liste: { count, html }.
 */
class TodoController extends AbstractAjaxController
{
    public function __construct(
        private readonly TodoService $todo,
        private readonly TodoItemRepository $items,
        private readonly EntityManagerInterface $em,
        private readonly TodoClock $clock,
        private readonly ClientRepository $clientRepository,
        private readonly ClientService $clientService,
    ) {
    }

    /** Klijenti koje korisnik sme da vidi, za izbor veze u prozoru stavke (pretraga po nazivu). */
    #[Route('/ajax/todo/clients', name: 'ajax_todo_clients', methods: ['GET'])]
    public function clients(Request $request): JsonResponse
    {
        $me = $this->actor();
        $qb = $this->clientRepository->createVisibleQueryBuilder($me, $this->clientService->canAssignAnyone($me), $request->query->getString('q'), 'active')->setMaxResults(30);

        return $this->ok(array_map(static fn (Client $c): array => ['id' => $c->getId(), 'name' => $c->getShortName()], $qb->getQuery()->getResult()));
    }

    #[Route('/ajax/todo', name: 'ajax_todo', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        return $this->ok($this->state($request->query->getString('q')));
    }

    /**
     * Brz poll za podsetnike (JS ga zove na ~15 s dok je tab vidljiv). Lagan: samo stavke ulogovanog korisnika čiji je podsetnik
     * poslat, a nije prikazan. Svaka se atomski "preuzima" (shown_at), pa toast dobija samo jedan tab; zvono ostaje rezervni kanal.
     * Sesija se zatvara pre upita, da poll ne drži zaključanu sesiju i ne usporava ostale zahteve.
     */
    #[Route('/ajax/todo/due', name: 'ajax_todo_due', methods: ['GET'])]
    public function due(Request $request): JsonResponse
    {
        $me = $this->actor();
        if ($request->hasSession() && $request->getSession()->isStarted()) {
            $request->getSession()->save();
        }
        $now = $this->clock->now();
        $reminders = [];
        foreach ($this->items->findShowable($me, $now->modify('-2 hours')) as $item) {
            if ($this->items->claimShown((int) $item->getId(), $me, $now)) {
                $reminders[] = ['id' => $item->getId(), 'title' => $item->getTitle()];
            }
        }

        return $this->ok(['reminders' => $reminders, 'count' => $this->todo->dueCount($me), 'open' => $this->todo->openCount($me)]);
    }

    #[Route('/ajax/todo/{id}/snooze', name: 'ajax_todo_snooze', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function snooze(int $id, Request $request): JsonResponse
    {
        $item = $this->owned($id, 'TODO_EDIT');
        try {
            $this->todo->snooze($item, (string) ($this->payload($request)['preset'] ?? ''));
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage());
        }

        return $this->ok($this->state(), 'Obaveštenje je odloženo.');
    }

    #[Route('/ajax/todo', name: 'ajax_todo_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $data = $this->payload($request);
        $item = null;
        try {
            $item = $this->todo->add($this->actor(), (string) ($data['title'] ?? ''));
            $this->todo->fill($item, array_intersect_key($data, array_flip(['note', 'dueDate', 'dueTime', 'remindDate', 'remindTime', 'remindByEmail', 'repeat'])));
            $this->applyClient($item, $data);
            $this->todo->save($item);
        } catch (\Throwable $e) {
            // stavka je već upisana pre popunjavanja polja: neuspeo unos ne sme da ostavi praznu stavku
            if (null !== $item) {
                $this->em->remove($item);
                $this->em->flush();
            }
            if ($e instanceof \DomainException) {
                return $this->fail($e->getMessage());
            }

            throw $e;
        }

        return $this->ok($this->state() + ['id' => $item->getId(), 'hasReminder' => null !== $item->getRemindAt()], 'Dodato u tvoj podsetnik.');
    }

    #[Route('/ajax/todo/reorder', name: 'ajax_todo_reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        $ids = array_map('intval', (array) ($this->payload($request)['ids'] ?? []));
        $this->todo->reorder($this->actor(), $ids);

        return $this->ok($this->state());
    }

    /** "Poništi" brisanja: vraća stavku iz snimka koji je vratio DELETE */
    #[Route('/ajax/todo/restore', name: 'ajax_todo_restore', methods: ['POST'])]
    public function restore(Request $request): JsonResponse
    {
        $data = $this->payload($request);
        $me = $this->actor();
        try {
            $item = $this->todo->add($me, (string) ($data['title'] ?? ''));
            $this->todo->fill($item, array_intersect_key($data, array_flip(['note', 'dueDate', 'dueTime', 'remindDate', 'remindTime', 'remindByEmail', 'repeat', 'pinned'])));
            $client = isset($data['clientId']) ? $this->em->find(Client::class, (int) $data['clientId']) : null;
            $task = isset($data['taskId']) ? $this->em->find(Task::class, (int) $data['taskId']) : null;
            // kontekst se vraća samo ako vlasnik i dalje sme da ga vidi
            $item->setClient($client && $this->isGranted('CLIENT_VIEW', $client) ? $client : null);
            $item->setTask($task && $this->isGranted('TASK_VIEW', $task) ? $task : null);
            $this->todo->save($item);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage());
        }

        return $this->ok($this->state(), 'Stavka je vraćena.');
    }

    /**
     * "Dodaj u podsetnik" sa zadatka ili klijenta: pravi stavku vezanu za njih (rok i podsetnik u isto vreme).
     * Zadatak/klijent se moraju smeti videti (TASK_VIEW / CLIENT_VIEW), inače 404.
     */
    #[Route('/ajax/todo/remind', name: 'ajax_todo_remind', methods: ['POST'])]
    public function remind(Request $request): JsonResponse
    {
        $data = $this->payload($request);
        $task = isset($data['taskId']) ? $this->em->find(Task::class, (int) $data['taskId']) : null;
        $client = isset($data['clientId']) ? $this->em->find(Client::class, (int) $data['clientId']) : null;
        if (null === $task && null === $client) {
            return $this->fail('Nedostaje zadatak ili klijent.');
        }
        if (($task && !$this->isGranted('TASK_VIEW', $task)) || ($client && !$this->isGranted('CLIENT_VIEW', $client))) {
            throw $this->createNotFoundException();
        }
        $item = null;
        try {
            if (empty($data['date'])) {
                throw new \DomainException('Izaberi datum obaveštenja.');
            }
            $item = $this->todo->add($this->actor(), (string) ($data['title'] ?? ''));
            $time = $data['time'] ?? '09:00';
            $this->todo->fill($item, ['dueDate' => $data['date'], 'dueTime' => $time, 'remindDate' => $data['date'], 'remindTime' => $time, 'remindByEmail' => !empty($data['remindByEmail'])]);
            $item->setTask($task)->setClient($task?->getClient() ?? $client);
            $this->todo->save($item);
        } catch (\DomainException $e) {
            if (null !== $item) {
                $this->todo->delete($item);
            }

            return $this->fail($e->getMessage());
        }

        return $this->ok($this->state(), 'Dodato u tvoj podsetnik.');
    }

    #[Route('/ajax/todo/{id}', name: 'ajax_todo_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->owned($id, 'TODO_EDIT');
        $data = $this->payload($request);
        try {
            $this->todo->fill($item, array_intersect_key($data, array_flip(['title', 'note', 'dueDate', 'dueTime', 'remindDate', 'remindTime', 'remindByEmail', 'repeat', 'pinned'])));
            $this->applyClient($item, $data);
            $this->todo->save($item);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage());
        }

        return $this->ok($this->state() + ['hasReminder' => null !== $item->getRemindAt()], 'Sačuvano.');
    }

    #[Route('/ajax/todo/{id}/done', name: 'ajax_todo_done', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function done(int $id): JsonResponse
    {
        $item = $this->owned($id, 'TODO_EDIT');
        $next = $this->todo->complete($item);

        return $this->ok($this->state() + ['repeated' => null !== $next], 'Završeno.');
    }

    #[Route('/ajax/todo/{id}/reopen', name: 'ajax_todo_reopen', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reopen(int $id): JsonResponse
    {
        $item = $this->owned($id, 'TODO_EDIT');
        $this->todo->reopen($item);

        return $this->ok($this->state());
    }

    #[Route('/ajax/todo/{id}', name: 'ajax_todo_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->owned($id, 'TODO_DELETE');
        $snapshot = $this->todo->snapshot($item);
        $wasRepeating = TodoRepeat::NONE !== $item->getRepeat();
        $this->todo->delete($item);

        return $this->ok($this->state() + ['snapshot' => $snapshot, 'repeating' => $wasRepeating]);
    }

    /** Tuđa ili nepostojeća stavka: 404 */
    private function owned(int $id, string $attribute): TodoItem
    {
        $item = $this->items->findOwned($id, $this->actor());
        if (null === $item || !$this->isGranted($attribute, $item)) {
            throw $this->createNotFoundException();
        }

        return $item;
    }

    /**
     * @return array{count: int, html: string}
     */
    /** Veza na klijenta iz prozora stavke: prazno skida vezu, nova veza traži CLIENT_VIEW (inače 404), neizmenjena veza se ne dira. */
    private function applyClient(TodoItem $item, array $data): void
    {
        if (!\array_key_exists('clientId', $data)) {
            return;
        }
        if (null === $data['clientId'] || '' === $data['clientId']) {
            $item->setClient(null);

            return;
        }
        if ($item->getClient()?->getId() === (int) $data['clientId']) {
            return;
        }
        $client = $this->em->find(Client::class, (int) $data['clientId']);
        if (!$client || !$this->isGranted('CLIENT_VIEW', $client)) {
            throw $this->createNotFoundException();
        }
        $item->setClient($client);
    }

    private function state(string $query = ''): array
    {
        $me = $this->actor();
        $query = trim($query);

        $sections = '' === $query ? $this->todo->sections($me) : null;
        $state = [
            'count' => $this->todo->dueCount($me),
            'open' => $this->todo->openCount($me),
            'html' => $this->renderView('todo/_sections.html.twig', [
                'sections' => $sections,
                'found' => '' === $query ? [] : $this->todo->search($me, $query),
                'query' => $query,
            ]),
        ];
        // kartica na početnoj (skraćena lista) se osvežava iz istog odgovora kao panel i signal u headeru
        if (null !== $sections) {
            $state['compactHtml'] = $this->renderView('todo/_compact.html.twig', ['compact' => $this->todo->compactFrom($sections)]);
        }

        return $state;
    }
}
