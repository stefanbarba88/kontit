<?php

namespace App\Controller\Admin;

use App\Entity\JobTemplate;
use App\Entity\ServicePackage;
use App\Form\JobTemplateType;
use App\Form\ServicePackageType;
use App\Repository\JobTemplateRepository;
use App\Repository\ServicePackageRepository;
use App\Service\CatalogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/catalog')]
#[IsGranted('CATALOG_MANAGE')]
class CatalogController extends AbstractController
{
    public function __construct(private readonly CatalogService $catalog)
    {
    }

    #[Route('/jobs', name: 'admin_job_index', methods: ['GET'])]
    public function jobs(JobTemplateRepository $repository): Response
    {
        return $this->render('admin/catalog/jobs.html.twig', ['jobs' => $repository->findBy([], ['category' => 'ASC', 'name' => 'ASC'])]);
    }

    #[Route('/jobs/new', name: 'admin_job_new', methods: ['GET', 'POST'])]
    public function newJob(Request $request): Response
    {
        return $this->jobForm($request, new JobTemplate());
    }

    #[Route('/jobs/{id}/edit', name: 'admin_job_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editJob(Request $request, JobTemplate $job): Response
    {
        return $this->jobForm($request, $job);
    }

    #[Route('/packages', name: 'admin_package_index', methods: ['GET'])]
    public function packages(ServicePackageRepository $repository): Response
    {
        $packages = $repository->findBy([], ['name' => 'ASC']);
        $counts = [];
        foreach ($packages as $package) {
            $counts[$package->getId()] = $this->catalog->countActiveClients($package);
        }

        return $this->render('admin/catalog/packages.html.twig', ['packages' => $packages, 'clientCounts' => $counts]);
    }

    #[Route('/packages/new', name: 'admin_package_new', methods: ['GET', 'POST'])]
    public function newPackage(Request $request): Response
    {
        return $this->packageForm($request, new ServicePackage());
    }

    #[Route('/packages/{id}/edit', name: 'admin_package_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editPackage(Request $request, ServicePackage $package): Response
    {
        return $this->packageForm($request, $package);
    }

    private function jobForm(Request $request, JobTemplate $job): Response
    {
        $form = $this->createForm(JobTemplateType::class, $job);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->catalog->saveJob($job, (string) $form->get('checklist')->getData());
            $this->addFlash('success', sprintf('Posao „%s“ je sačuvan.', $job->getName()));

            return $this->redirectToRoute('admin_job_index');
        }

        return $this->render('admin/catalog/job_form.html.twig', ['form' => $form, 'job' => $job]);
    }

    private function packageForm(Request $request, ServicePackage $package): Response
    {
        $isNew = null === $package->getId();
        $clients = $isNew ? 0 : $this->catalog->countActiveClients($package);
        $form = $this->createForm(ServicePackageType::class, $package);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $package->setJobTemplates($form->get('jobTemplates')->getData());
            $apply = $request->request->getBoolean('apply_to_clients');
            $created = $this->catalog->savePackage($package, $apply, $this->getUser());
            $this->addFlash('success', sprintf('Paket „%s“ je sačuvan.', $package->getName()).($apply ? sprintf(' Dodato poslova klijentima: %d.', $created) : ''));

            return $this->redirectToRoute('admin_package_index');
        }

        return $this->render('admin/catalog/package_form.html.twig', ['form' => $form, 'package' => $package, 'clients' => $clients]);
    }
}
