<?php

namespace App\Controller\Client;

use App\Entity\ActivityLog;
use App\Entity\Client;
use App\Entity\ClientPackage;
use App\Form\ClientPackageType;
use App\Repository\ServicePackageRepository;
use App\Service\ContractedServicesOverview;
use App\Service\TaskListView;
use App\Entity\User;
use App\Form\ClientAssignmentType;
use App\Form\ClientType;
use App\Repository\ActivityLogRepository;
use App\Repository\ClientAssignmentLogRepository;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use App\Service\AbsenceService;
use App\Service\ClientImportService;
use App\Service\ClientService;
use App\Nbs\NbsCompanyLookupService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ClientController extends AbstractController
{
    public function __construct(
        private readonly ClientService $clients,
        private readonly UserRepository $users,
        private readonly NbsCompanyLookupService $nbs,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Lista klijenata u opsegu korisnika: admin sve, šef svoj tim. Radnik koristi /my-clients.
     */
    #[Route('/clients', name: 'client_index', methods: ['GET'])]
    #[IsGranted('ROLE_SEF')]
    public function index(Request $request, ClientRepository $repository, PaginatorInterface $paginator): Response
    {
        $filters = [
            'q' => $request->query->getString('q'),
            'status' => $request->query->getString('status', 'active'),
        ];

        $pagination = $paginator->paginate(
            $repository->createVisibleQueryBuilder($this->actor(), $this->clients->canAssignAnyone($this->actor()), $filters['q'], $filters['status']),
            $request->query->getInt('page', 1),
            15,
        );

        return $this->render('client/index.html.twig', ['pagination' => $pagination, 'filters' => $filters]);
    }

    #[Route('/my-clients', name: 'client_mine', methods: ['GET'])]
    public function mine(ClientRepository $repository, AbsenceService $absences): Response
    {
        $active = [];
        foreach ($absences->activeReplacements($this->actor()) as $row) {
            $active[$row['client']->getId()] = $row['absence'];
        }

        return $this->render('client/mine.html.twig', [
            'replacements' => $active,
            'primary' => $repository->findActivePrimaryFor($this->actor()),
            'backup' => $repository->findActiveBackupFor($this->actor()),
        ]);
    }

    #[Route('/clients/new', name: 'client_new', methods: ['GET', 'POST'])]
    #[IsGranted('CLIENT_CREATE')]
    public function new(Request $request): Response
    {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client, [
            'assignee_query_builder' => $this->assigneeQueryBuilder($client),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->clients->create($client, $form->get('primary')->getData(), $form->get('backup')->getData(), $this->actor());
                $this->addFlash('success', sprintf('Klijent „%s“ je kreiran.', $client->getShortName()));

                return $this->redirectToRoute('client_show', ['id' => $client->getId()]);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('client/form.html.twig', ['form' => $form, 'client' => $client, 'nbs_configured' => $this->nbs->isConfigured()]);
    }

    #[Route('/clients/import', name: 'client_import', methods: ['GET', 'POST'])]
    #[IsGranted('CLIENT_CREATE')]
    public function import(Request $request, ClientImportService $importer): Response
    {
        $builder = $this->createFormBuilder(null, ['csrf_token_id' => 'client_import'])
            ->add('pibs', \Symfony\Component\Form\Extension\Core\Type\TextareaType::class, [
                'label' => 'PIB-ovi',
                'attr' => ['rows' => 6, 'placeholder' => "100002119\n101234567"],
                'help' => sprintf('Jedan ili više PIB-ova (od 9 cifara), razdvojenih novim redom, razmakom ili zarezom. Najviše %d odjednom.', ClientImportService::LIMIT),
                'constraints' => [new \Symfony\Component\Validator\Constraints\NotBlank(message: 'Unesi bar jedan PIB.')],
            ]);
        ClientType::addAssignment($builder, $this->assigneeQueryBuilder(new Client()), new Client());
        $form = $builder->getForm();
        $form->handleRequest($request);

        $results = null;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $results = $importer->import($importer->parse((string) $form->get('pibs')->getData()), $form->get('primary')->getData(), $form->get('backup')->getData(), $this->actor());
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('client/import.html.twig', ['form' => $form, 'results' => $results, 'configured' => $this->nbs->isConfigured()]);
    }

    #[Route('/clients/{id}/nbs', name: 'client_nbs_refresh', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('CLIENT_EDIT', 'client')]
    public function nbsRefresh(Request $request, Client $client): Response
    {
        if (!$this->isCsrfTokenValid('client_nbs_'.$client->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Neispravan bezbednosni token. Pokušaj ponovo.');
        } else {
            try {
                $this->nbs->refresh($client);
                $this->em->flush();
                $this->addFlash('success', 'Podaci su osveženi iz NBS-a.');
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            } catch (\Throwable) {
                $this->addFlash('danger', 'Greška pri pozivu NBS servisa.');
            }
        }

        return $this->redirectToRoute('client_show', ['id' => $client->getId()]);
    }

    #[Route('/clients/{id}', name: 'client_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Route('/clients/{id}/zadaci', name: 'client_tasks', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('CLIENT_VIEW', 'client')]
    public function show(Request $request, Client $client, ClientAssignmentLogRepository $logs, ActivityLogRepository $activity, ContractedServicesOverview $services, ServicePackageRepository $packages, UserRepository $users, TaskListView $taskList): Response
    {
        // tabovi: Pregled, Ugovorene usluge (SPEC 8h.3) i Zadaci (/clients/{id}/zadaci) se učitavaju samo kad su otvoreni
        $tab = 'client_tasks' === $request->attributes->get('_route') ? 'zadaci' : ('usluge' === $request->query->get('tab') ? 'usluge' : 'pregled');
        $data = ['client' => $client, 'tab' => $tab];
        // brojač otvorenih zadataka na tabu i crvena tačka ako neki kasne (samo ono što korisnik sme da vidi)
        $me = $this->getUser();
        $data['taskCounts'] = $taskList->summary($me, $client);
        if ('zadaci' === $tab) {
            $data['list'] = $taskList->build($request, $me, 'client_tasks', ['id' => $client->getId()], $client);
        } elseif ('usluge' === $tab) {
            $data['services'] = $services->build($client);
            $data['assignees'] = $users->findBy(['isActive' => true], ['firstName' => 'ASC', 'lastName' => 'ASC']);
            if ($this->isGranted('CATALOG_MANAGE')) {
                $data['packageForm'] = $this->createForm(ClientPackageType::class, (new ClientPackage())->setStartDate(new \DateTimeImmutable('today')), [
                    'action' => $this->generateUrl('client_package_attach', ['id' => $client->getId()]),
                ])->createView();
                $data['packagesData'] = ClientPackageController::packagesData($packages);
            }
        } elseif ('pregled' === $tab) {
            $data['history'] = $logs->findByClient($client);
            $data['activity'] = $activity->findEntityPage(ActivityLog::CLIENT, $client->getId());
        }

        return $this->render('client/show.html.twig', $data);
    }

    #[Route('/clients/{id}/edit', name: 'client_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('CLIENT_EDIT', 'client')]
    public function edit(Request $request, Client $client): Response
    {
        $form = $this->createForm(ClientType::class, $client, [
            'assignee_query_builder' => $this->assigneeQueryBuilder($client),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->clients->update($client, $form->get('primary')->getData(), $form->get('backup')->getData(), $this->actor());
                $this->addFlash('success', 'Izmene su sačuvane.');

                return $this->redirectToRoute('client_show', ['id' => $client->getId()]);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('client/form.html.twig', ['form' => $form, 'client' => $client, 'nbs_configured' => $this->nbs->isConfigured()]);
    }

    #[Route('/clients/{id}/assign', name: 'client_assign', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('CLIENT_ASSIGN', 'client')]
    public function assign(Request $request, Client $client): Response
    {
        $form = $this->createForm(ClientAssignmentType::class, $client, [
            'assignee_query_builder' => $this->assigneeQueryBuilder($client),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->clients->assign($client, $form->get('primary')->getData(), $form->get('backup')->getData(), $this->actor());
                $this->addFlash('success', 'Dodela je sačuvana.');

                return $this->redirectToRoute('client_show', ['id' => $client->getId()]);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('client/assign.html.twig', ['form' => $form, 'client' => $client]);
    }

    #[Route('/clients/{id}/toggle', name: 'client_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('CLIENT_EDIT', 'client')]
    public function toggle(Request $request, Client $client): Response
    {
        if (!$this->isCsrfTokenValid('client_toggle_'.$client->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Nevažeći CSRF token.');
        }

        $activate = !$client->isActive();

        try {
            $this->clients->setActive($client, $activate);
            $this->addFlash('success', $activate ? 'Klijent je aktiviran.' : 'Klijent je deaktiviran.');
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('client_show', ['id' => $client->getId()]);
    }

    private function assigneeQueryBuilder(Client $client): QueryBuilder
    {
        $current = array_values(array_filter([$client->getPrimaryAccountant(), $client->getBackupAccountant()]));

        return $this->users->createAssignableQueryBuilder($this->actor(), $this->clients->canAssignAnyone($this->actor()), $current);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
