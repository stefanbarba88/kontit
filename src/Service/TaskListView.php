<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\User;
use App\Enum\Role;
use App\Repository\ClientRepository;
use App\Repository\TaskRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Podaci za zajedničku listu zadataka (templates/components/_task_list.html.twig + _task_filters.html.twig),
 * koja se koristi na /tasks, na tabu "Zadaci" klijenta i na tabu "Zadaci" korisnika. Jedan upit (TaskRepository::createFilteredQueryBuilder).
 */
class TaskListView
{
    public const PER_PAGE = 15;

    public function __construct(
        private readonly TaskListFilters $filters,
        private readonly TaskRepository $tasks,
        private readonly ClientRepository $clients,
        private readonly PaginatorInterface $paginator,
        private readonly TaskService $taskService,
    ) {
    }

    /**
     * Brojači za tab klijenta/korisnika (otvoreni, kasne, na proveri, završeni ovog meseca) u opsegu koji korisnik sme da vidi.
     *
     * @return array{open: int, overdue: int, review: int, doneMonth: int}
     */
    public function summary(User $me, ?Client $client = null, ?User $assignee = null): array
    {
        return $this->tasks->countSummary($this->filters->resolve([], $me, $client, $assignee), $me);
    }

    /**
     * @param array<string, mixed> $routeParams parametri rute stranice (npr. ['id' => 5]), bez filtera
     *
     * @return array<string, mixed> promenljiva "list" za komponentu
     */
    public function build(Request $request, User $me, string $route, array $routeParams, ?Client $lockedClient = null, ?User $lockedAssignee = null): array
    {
        $f = $this->filters->resolve($request->query->all(), $me, $lockedClient, $lockedAssignee);
        $pagination = $this->paginator->paginate($this->filters->queryBuilder($f, $me), $request->query->getInt('page', 1), self::PER_PAGE);
        $this->tasks->hydrateListRelations($pagination);

        $admin = $this->taskService->isAdmin($me);
        $leader = $this->taskService->isLeader($me);
        $locked = null !== $lockedClient || null !== $lockedAssignee;

        // pri izvozu sa taba stranica šalje forClient / forUser, pa izvoz dobija isti opseg i ista prava
        $exportQuery = $f->toQuery();
        if (null !== $lockedClient) {
            $exportQuery['forClient'] = $lockedClient->getId();
        }
        if (null !== $lockedAssignee) {
            $exportQuery['forUser'] = $lockedAssignee->getId();
        }

        return [
            'filter' => $f,
            'pagination' => $pagination,
            'route' => $route,
            'routeParams' => $routeParams,
            'query' => $f->toQuery(),
            'exportQuery' => $exportQuery,
            'locked' => $locked,
            'hidden' => array_values(array_filter([null !== $lockedClient ? 'client' : null, null !== $lockedAssignee ? 'user' : null, $locked ? 'team' : null, $locked ? 'scope' : null])),
            'clients' => null === $lockedClient ? $this->clients->createVisibleQueryBuilder($me, $admin, null, 'active')->getQuery()->getResult() : [],
            'teams' => $locked ? [] : $this->filters->teamsFor($me),
            'people' => null !== $lockedClient ? $this->tasks->findAssigneesOf($f, $me) : (null !== $lockedAssignee ? [] : $this->filters->usersFor($me)),
            'jobs' => $this->filters->jobOptions($lockedClient),
            'packages' => $this->filters->packageOptions(),
            'canSeeAll' => $leader,
            'isAdmin' => $admin,
            'approvalCount' => !$locked && $leader ? $this->tasks->countAwaitingApproval($me, $admin) : 0,
            'summary' => $locked ? $this->tasks->countSummary($f, $me) : null,
        ];
    }
    /**
     * Lista stranice Posebne potvrde: isti oblik kao build(), ali tabovi su special_*, a opseg određuje TaskRepository::createSpecialQueryBuilder.
     * Brojači tabova se računaju bez ostalih filtera.
     *
     * @return array<string, mixed>
     */
    public function buildSpecial(Request $request, User $me, string $route): array
    {
        $f = $this->filters->resolveSpecial($request->query->all(), $me);
        $pagination = $this->paginator->paginate($this->filters->queryBuilder($f, $me), $request->query->getInt('page', 1), self::PER_PAGE);
        $this->tasks->hydrateListRelations($pagination);

        $count = fn (string $tab): int => (int) $this->tasks->createFilteredQueryBuilder(
            $f->with(['tab' => $tab, 'client' => null, 'job' => null, 'noJob' => false, 'assignee' => null, 'status' => null, 'priority' => null, 'from' => null, 'to' => null, 'q' => '', 'qRaw' => '', 'origin' => 'all']),
            $me,
        )->select('COUNT(t.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $query = $f->toQuery();
        unset($query['closed']);
        if ('special_waiting' === $f->tab) {
            unset($query['tab']);
        }

        return [
            'filter' => $f,
            'pagination' => $pagination,
            'route' => $route,
            'routeParams' => array_filter(['all' => $f->specialAll ? 1 : null]),
            'query' => $query,
            'exportQuery' => $f->toQuery(),
            'locked' => false,
            'hidden' => ['scope', 'team', 'status', 'origin'],
            'clients' => $this->clients->createVisibleQueryBuilder($me, true, null, 'active')->getQuery()->getResult(),
            'teams' => [],
            'people' => $this->filters->usersFor($me),
            'jobs' => $this->filters->jobOptions(),
            'packages' => $this->filters->packageOptions(),
            'canSeeAll' => false,
            'canSeeEverything' => Role::SUPERADMIN === $me->getHighestRole(),
            'isAdmin' => true,
            'approvalCount' => 0,
            'summary' => null,
            'counts' => ['waiting' => $count('special_waiting'), 'progress' => $count('special_progress')],
        ];
    }
}