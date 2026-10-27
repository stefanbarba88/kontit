<?php

namespace App\Controller\Client;

use App\Entity\Client;
use App\Entity\ClientJob;
use App\Entity\ClientPackage;
use App\Form\ClientJobType;
use App\Form\ClientPackageType;
use App\Repository\ClientJobRepository;
use App\Repository\ClientPackageRepository;
use App\Repository\ClientPackagePriceLogRepository;
use App\Service\ClientPackageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Paketi i poslovi klijenta. Pregled: svako ko vidi klijenta (cene samo admin+).
 * Dodela/ukidanje paketa, isključenje, cene i odobravanje: CATALOG_MANAGE (admin+).
 * Ad hoc posao može da predloži svako ko vidi klijenta; admin ga odobrava.
 */
class ClientPackageController extends AbstractController
{
    public function __construct(private readonly ClientPackageService $service)
    {
    }

    #[Route('/clients/{id}/packages', name: 'client_packages', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function index(Client $client, ClientPackageRepository $packages, ClientJobRepository $jobs, ClientPackagePriceLogRepository $priceLog): Response
    {
        $this->denyAccessUnlessGranted('CLIENT_VIEW', $client);

        $clientPackages = $packages->findByClient($client);

        return $this->render('client/packages.html.twig', [
            'client' => $client,
            'clientPackages' => $clientPackages,
            'jobs' => $jobs->findBy(['client' => $client], ['isActive' => 'DESC', 'startDate' => 'DESC']),
            'priceLog' => $this->isGranted('PRICE_VIEW') ? $priceLog->findBy(['clientPackage' => $clientPackages], ['effectiveFrom' => 'DESC', 'id' => 'DESC']) : [],
        ]);
    }

    #[Route('/clients/{id}/packages/attach', name: 'client_package_attach', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function attach(Request $request, Client $client): Response
    {
        $this->denyAccessUnlessGranted('CATALOG_MANAGE');

        $clientPackage = (new ClientPackage())->setClient($client)->setStartDate(new \DateTimeImmutable('today'));
        $form = $this->createForm(ClientPackageType::class, $clientPackage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $warnings = $this->service->attach($clientPackage, $this->getUser());
            $this->addFlash('success', sprintf('Paket „%s“ je dodeljen klijentu.', $clientPackage->getPackage()->getName()));
            foreach ($warnings as $warning) {
                $this->addFlash('warning', $warning);
            }

            return $this->redirectToRoute('client_packages', ['id' => $client->getId()]);
        }

        return $this->render('client/package_attach.html.twig', ['client' => $client, 'form' => $form]);
    }

    #[Route('/client-packages/{id}/remove', name: 'client_package_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function remove(Request $request, ClientPackage $clientPackage): Response
    {
        $this->denyAccessUnlessGranted('CATALOG_MANAGE');
        $this->checkToken($request, 'client_package_remove_'.$clientPackage->getId());

        try {
            $cancelled = $this->service->remove($clientPackage, (string) $request->request->get('reason'), $request->request->getBoolean('cancel_tasks'), $this->getUser());
            $this->addFlash('success', 'Paket je ukinut.'.($cancelled ? sprintf(' Otkazano zadataka: %d.', $cancelled) : ''));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('client_packages', ['id' => $clientPackage->getClient()->getId()]);
    }

    #[Route('/client-packages/{id}/price', name: 'client_package_price', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function price(Request $request, ClientPackage $clientPackage): Response
    {
        $this->denyAccessUnlessGranted('CATALOG_MANAGE');
        $this->checkToken($request, 'client_package_price_'.$clientPackage->getId());

        $price = str_replace(',', '.', trim((string) $request->request->get('price')));
        try {
            $from = new \DateTimeImmutable((string) ($request->request->get('from') ?: 'today'));
        } catch (\Exception) {
            $from = new \DateTimeImmutable('today');
        }
        if (!is_numeric($price) || (float) $price < 0) {
            $this->addFlash('danger', 'Unesite ispravnu cenu.');
        } else {
            $this->service->changePrice($clientPackage, number_format((float) $price, 2, '.', ''), $from, $this->getUser());
            $this->addFlash('success', 'Cena paketa je izmenjena.');
        }

        return $this->redirectToRoute('client_packages', ['id' => $clientPackage->getClient()->getId()]);
    }

    #[Route('/client-jobs/{id}/exclude', name: 'client_job_exclude', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function exclude(Request $request, ClientJob $job): Response
    {
        $this->denyAccessUnlessGranted('CATALOG_MANAGE');
        $this->checkToken($request, 'client_job_'.$job->getId());

        try {
            $cancelled = $this->service->exclude($job, (string) $request->request->get('reason'), $request->request->getBoolean('cancel_tasks'), $this->getUser());
            $this->addFlash('success', 'Posao je isključen za ovog klijenta.'.($cancelled ? sprintf(' Otkazano zadataka: %d.', $cancelled) : ''));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('client_packages', ['id' => $job->getClient()->getId()]);
    }

    #[Route('/client-jobs/{id}/include', name: 'client_job_include', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function include(Request $request, ClientJob $job): Response
    {
        $this->denyAccessUnlessGranted('CATALOG_MANAGE');
        $this->checkToken($request, 'client_job_'.$job->getId());

        $this->service->include($job);
        $this->addFlash('success', 'Posao je ponovo uključen.');

        return $this->redirectToRoute('client_packages', ['id' => $job->getClient()->getId()]);
    }

    #[Route('/client-jobs/{id}/end', name: 'client_job_end', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function end(Request $request, ClientJob $job): Response
    {
        $this->denyAccessUnlessGranted('CATALOG_MANAGE');
        $this->checkToken($request, 'client_job_'.$job->getId());

        $cancelled = $this->service->endJob($job, (string) $request->request->get('reason'), $request->request->getBoolean('cancel_tasks'), $this->getUser());
        $this->addFlash('success', 'Posao je ukinut.'.($cancelled ? sprintf(' Otkazano zadataka: %d.', $cancelled) : ''));

        return $this->redirectToRoute('client_packages', ['id' => $job->getClient()->getId()]);
    }

    #[Route('/client-jobs/{id}/approve', name: 'client_job_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approve(Request $request, ClientJob $job): Response
    {
        $this->denyAccessUnlessGranted('CATALOG_MANAGE');
        $this->checkToken($request, 'client_job_'.$job->getId());

        $price = str_replace(',', '.', trim((string) $request->request->get('price')));
        $this->service->approve($job, is_numeric($price) ? number_format((float) $price, 2, '.', '') : null);
        $this->addFlash('success', 'Predlog posla je odobren.');

        return $this->redirectToRoute('client_packages', ['id' => $job->getClient()->getId()]);
    }

    #[Route('/clients/{id}/jobs/new', name: 'client_job_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function newJob(Request $request, Client $client): Response
    {
        $this->denyAccessUnlessGranted('CLIENT_VIEW', $client);

        $isAdmin = $this->isGranted('CATALOG_MANAGE');
        $job = (new ClientJob())->setStartDate(new \DateTimeImmutable('today'));
        $form = $this->createForm(ClientJobType::class, $job, ['show_price' => $isAdmin]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->addAdHoc($job, $client, $isAdmin, $this->getUser());
            $this->addFlash('success', $isAdmin ? 'Posao je dodat klijentu.' : 'Predlog je poslat administratoru na odobravanje.');

            return $this->redirectToRoute('client_packages', ['id' => $client->getId()]);
        }

        return $this->render('client/job_form.html.twig', ['client' => $client, 'form' => $form, 'isAdmin' => $isAdmin]);
    }

    private function checkToken(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Neispravan CSRF token.');
        }
    }
}
