<?php

namespace App\Controller\Client;

use App\Entity\Client;
use App\Entity\ClientJob;
use App\Entity\ClientPackage;
use App\Entity\User;
use App\Enum\JobSource;
use App\Form\ClientJobEditType;
use App\Form\ClientPackageType;
use App\Repository\JobTemplateRepository;
use App\Repository\ServicePackageRepository;
use App\Repository\UserRepository;
use App\Service\ClientPackageService;
use App\Service\ScheduleChangeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Ugovorene usluge klijenta (SPEC 8h.3): tab na kartici klijenta, modali "Dodaj paket" i "Dodaj pojedinačan posao".
 * Pakete, cene, isključenja i izmene radi admin+ (CATALOG_MANAGE); pojedinačan posao može da predloži svako ko vidi klijenta.
 */
class ClientPackageController extends AbstractController
{
    public function __construct(private readonly ClientPackageService $service, private readonly ScheduleChangeService $scheduleChanges)
    {
    }

    /**
     * Prilagođeni rasporedi iz forme: schedule[id posla] = JSON. Neispravni i prazni se izostavljaju (posao ostaje na podrazumevanom).
     *
     * @return array<int, \App\Entity\Schedule>
     */
    private function customSchedules(Request $request): array
    {
        $out = [];
        foreach ($request->request->all('schedule') as $id => $raw) {
            if (null !== ($schedule = $this->scheduleChanges->parse($raw))) {
                $out[(int) $id] = $schedule;
            }
        }

        return $out;
    }

    /**
     * Stara stranica "Paketi i poslovi" je sada tab na kartici klijenta.
     */
    #[Route('/clients/{id}/packages', name: 'client_packages', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function index(Client $client): Response
    {
        return $this->toTab($client);
    }

    #[Route('/clients/{id}/packages/attach', name: 'client_package_attach', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function attach(Request $request, Client $client, ServicePackageRepository $packages): Response
    {
        $this->denyAccessUnlessGranted('CATALOG_MANAGE');

        $clientPackage = (new ClientPackage())->setClient($client)->setStartDate(new \DateTimeImmutable('today'));
        $form = $this->createForm(ClientPackageType::class, $clientPackage, ['action' => $this->generateUrl('client_package_attach', ['id' => $client->getId()])]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // štiklirani poslovi su include[], a odštiklirani se čuvaju kao isključeni, sa opcionim razlogom
            $included = array_map('intval', $request->request->all('include'));
            $excluded = [];
            foreach ($clientPackage->getPackage()->getJobTemplates() as $template) {
                if (!\in_array($template->getId(), $included, true)) {
                    $excluded[$template->getId()] = trim((string) ($request->request->all('reason')[$template->getId()] ?? ''));
                }
            }
            $warnings = $this->service->attach($clientPackage, $this->actor(), $excluded, $this->customSchedules($request));
            $this->addFlash('success', sprintf('Paket „%s“ je dodat klijentu.%s', $clientPackage->getPackage()->getName(), $excluded ? sprintf(' Isključeno poslova: %d.', \count($excluded)) : ''));
            foreach ($warnings as $warning) {
                $this->addFlash('warning', $warning);
            }

            return $this->toTab($client);
        }

        return $this->render('client/package_attach.html.twig', [
            'client' => $client,
            'form' => $form,
            'packagesData' => self::packagesData($packages),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/client-packages/{id}/remove', name: 'client_package_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function remove(Request $request, ClientPackage $clientPackage): Response
    {
        $this->denyAccessUnlessGranted('CATALOG_MANAGE');
        $this->checkToken($request, 'client_package_remove_'.$clientPackage->getId());

        try {
            $cancelled = $this->service->remove($clientPackage, (string) $request->request->get('reason'), $request->request->getBoolean('cancel_tasks'), $this->actor());
            $this->addFlash('success', 'Paket je uklonjen.'.($cancelled ? sprintf(' Otkazano zadataka: %d.', $cancelled) : ''));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->toTab($clientPackage->getClient());
    }

    #[Route('/client-packages/{id}/price', name: 'client_package_price', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function price(Request $request, ClientPackage $clientPackage): Response
    {
        $this->denyAccessUnlessGranted('CATALOG_MANAGE');
        $this->checkToken($request, 'client_package_price_'.$clientPackage->getId());

        $price = ClientPackageService::parseMoney((string) $request->request->get('price'));
        try {
            $from = new \DateTimeImmutable((string) ($request->request->get('from') ?: 'today'));
        } catch (\Exception) {
            $from = new \DateTimeImmutable('today');
        }
        if (null === $price) {
            $this->addFlash('danger', 'Unesi ispravnu cenu.');
        } else {
            $this->service->changePrice($clientPackage, $price, $from, $this->actor());
            $this->addFlash('success', 'Cena paketa je izmenjena.');
        }

        return $this->toTab($clientPackage->getClient());
    }

    #[Route('/client-jobs/{id}/exclude', name: 'client_job_exclude', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function exclude(Request $request, ClientJob $job): Response
    {
        $this->denyAccessUnlessGranted('CATALOG_MANAGE');
        $this->checkToken($request, 'client_job_'.$job->getId());

        try {
            $cancelled = $this->service->exclude($job, (string) $request->request->get('reason'), $request->request->getBoolean('cancel_tasks'), $this->actor());
            $this->addFlash('success', 'Posao je isključen za ovog klijenta.'.($cancelled ? sprintf(' Otkazano zadataka: %d.', $cancelled) : ''));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->toTab($job->getClient());
    }

    #[Route('/client-jobs/{id}/include', name: 'client_job_include', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function include(Request $request, ClientJob $job): Response
    {
        $this->denyAccessUnlessGranted('CATALOG_MANAGE');
        $this->checkToken($request, 'client_job_'.$job->getId());

        $this->service->include($job);
        $this->addFlash('success', 'Posao je ponovo uključen.');

        return $this->toTab($job->getClient());
    }

    #[Route('/client-jobs/{id}/end', name: 'client_job_end', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function end(Request $request, ClientJob $job): Response
    {
        $this->denyAccessUnlessGranted('CATALOG_MANAGE');
        $this->checkToken($request, 'client_job_'.$job->getId());

        $cancelled = $this->service->endJob($job, (string) $request->request->get('reason'), $request->request->getBoolean('cancel_tasks'), $this->actor());
        $this->addFlash('success', 'Posao je završen.'.($cancelled ? sprintf(' Otkazano zadataka: %d.', $cancelled) : ''));

        return $this->toTab($job->getClient());
    }

    #[Route('/client-jobs/{id}/approve', name: 'client_job_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approve(Request $request, ClientJob $job): Response
    {
        $this->denyAccessUnlessGranted('CATALOG_MANAGE');
        $this->checkToken($request, 'client_job_'.$job->getId());

        $this->service->approve($job, ClientPackageService::parseMoney((string) $request->request->get('price')));
        $this->addFlash('success', 'Predlog posla je odobren.');

        return $this->toTab($job->getClient());
    }

    #[Route('/client-jobs/{id}/edit', name: 'client_job_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, ClientJob $job): Response
    {
        $this->denyAccessUnlessGranted('CATALOG_MANAGE');

        $form = $this->createForm(ClientJobEditType::class, $job, ['show_price' => JobSource::INDIVIDUAL === $job->getSource()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->save($job);
            $this->addFlash('success', sprintf('Posao „%s“ je izmenjen.', $job->getJobTemplate()->getName()));

            return $this->toTab($job->getClient());
        }

        return $this->render('client/job_edit.html.twig', ['job' => $job, 'client' => $job->getClient(), 'form' => $form]);
    }

    /**
     * Pojedinačni poslovi (jedan ili više iz job picker-a). Admin upisuje cenu i dodaje odmah; ostali predlažu.
     */
    #[Route('/clients/{id}/jobs/new', name: 'client_job_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function newJob(Request $request, Client $client, JobTemplateRepository $templates, UserRepository $users): Response
    {
        $this->denyAccessUnlessGranted('CLIENT_VIEW', $client);
        $isAdmin = $this->isGranted('CATALOG_MANAGE');

        if ($request->isMethod('POST')) {
            $this->checkToken($request, 'client_jobs_new_'.$client->getId());
            $ids = array_values(array_unique(array_map('intval', $request->request->all('jobs'))));
            $prices = $request->request->all('price');
            $notes = $request->request->all('note');
            $assignees = $request->request->all('assignee');
            $schedules = $isAdmin ? $this->customSchedules($request) : [];
            $items = [];
            foreach ($templates->findBy(['id' => $ids, 'isActive' => true]) as $template) {
                $id = $template->getId();
                $items[] = [
                    'template' => $template,
                    'price' => $isAdmin ? ClientPackageService::parseMoney((string) ($prices[$id] ?? '')) : null,
                    'note' => (string) ($notes[$id] ?? ''),
                    'assignee' => $isAdmin && !empty($assignees[$id]) ? $users->findOneBy(['id' => (int) $assignees[$id], 'isActive' => true]) : null,
                    'schedule' => $schedules[$id] ?? null,
                ];
            }
            try {
                $start = new \DateTimeImmutable((string) ($request->request->get('startDate') ?: 'today'));
            } catch (\Exception) {
                $start = new \DateTimeImmutable('today');
            }

            if ([] === $items) {
                $this->addFlash('warning', 'Izaberi bar jedan posao.');
            } else {
                $warnings = $this->service->addIndividual($client, $items, $start, $isAdmin, $this->actor());
                $this->addFlash('success', $isAdmin ? sprintf('Dodato poslova: %d.', \count($items) - \count($warnings)) : 'Predlog je poslat administratoru na odobravanje.');
                foreach ($warnings as $warning) {
                    $this->addFlash('warning', $warning);
                }
            }

            return $this->toTab($client);
        }

        return $this->render('client/job_form.html.twig', [
            'client' => $client,
            'isAdmin' => $isAdmin,
            'assignees' => $users->findBy(['isActive' => true], ['firstName' => 'ASC', 'lastName' => 'ASC']),
        ]);
    }

    /**
     * Paketi za modal "Dodaj paket": poslovi sa opisom rasporeda i predložena cena (JS ih prikazuje po izboru paketa).
     *
     * @return array<int, array{price: ?string, jobs: list<\App\Entity\JobTemplate>}>
     */
    public static function packagesData(ServicePackageRepository $packages): array
    {
        $out = [];
        foreach ($packages->findBy(['isActive' => true], ['name' => 'ASC']) as $package) {
            $out[$package->getId()] = [
                'price' => $package->getPrice(),
                'jobs' => $package->getJobTemplates(),
            ];
        }

        return $out;
    }

    private function toTab(Client $client): Response
    {
        return $this->redirectToRoute('client_show', ['id' => $client->getId(), 'tab' => 'usluge']);
    }

    private function actor(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    private function checkToken(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Neispravan CSRF token.');
        }
    }
}
