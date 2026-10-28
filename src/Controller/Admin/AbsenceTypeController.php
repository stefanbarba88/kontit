<?php

namespace App\Controller\Admin;

use App\Entity\AbsenceType;
use App\Form\AbsenceTypeFormType;
use App\Repository\AbsenceTypeRepository;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Podešavanja → Vrste odsustva (SPEC 8j.1), podrazumevan godišnji odmor i rokovi za automatsko odobravanje (8m).
 * Vrsta se ne briše (odsustva je koriste), nego deaktivira.
 */
#[Route('/admin/absence-types')]
#[IsGranted('CATALOG_MANAGE')]
class AbsenceTypeController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly SettingsService $settings)
    {
    }

    #[Route('', name: 'admin_absence_type_index', methods: ['GET'])]
    public function index(AbsenceTypeRepository $types): Response
    {
        return $this->render('admin/absence_type/index.html.twig', [
            'types' => $types->findOrdered(),
            'leaveDays' => $this->settings->get(SettingsService::ANNUAL_LEAVE_DAYS),
            'approvalHours' => $this->settings->get(SettingsService::ABSENCE_APPROVAL_HOURS),
            'taskAuto' => '1' === $this->settings->get(SettingsService::TASK_AUTO_APPROVE),
            'taskAutoDays' => $this->settings->get(SettingsService::TASK_AUTO_APPROVE_DAYS),
        ]);
    }

    #[Route('/new', name: 'admin_absence_type_new', methods: ['GET', 'POST'])]
    #[Route('/{id}/edit', name: 'admin_absence_type_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function form(Request $request, ?AbsenceType $type = null): Response
    {
        $type ??= new AbsenceType();
        $form = $this->createForm(AbsenceTypeFormType::class, $type);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($type);
            $this->em->flush();
            $this->addFlash('success', sprintf('Vrsta „%s“ je sačuvana.', $type->getName()));

            return $this->redirectToRoute('admin_absence_type_index');
        }

        return $this->render('admin/absence_type/form.html.twig', ['form' => $form, 'type' => $type]);
    }

    #[Route('/settings', name: 'admin_absence_settings', methods: ['POST'])]
    public function settings(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('absence_settings', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Neispravan CSRF token.');
        }
        $int = static fn (string $v, int $min, int $max): string => (string) max($min, min($max, (int) $v));
        $this->settings->set(SettingsService::ANNUAL_LEAVE_DAYS, $int($request->request->getString('leaveDays'), 0, 60));
        $this->settings->set(SettingsService::ABSENCE_APPROVAL_HOURS, $int($request->request->getString('approvalHours'), 1, 720));
        $this->settings->set(SettingsService::TASK_AUTO_APPROVE, $request->request->getBoolean('taskAuto') ? '1' : '0');
        $this->settings->set(SettingsService::TASK_AUTO_APPROVE_DAYS, $int($request->request->getString('taskAutoDays'), 1, 30));
        $this->addFlash('success', 'Podešavanja su sačuvana.');

        return $this->redirectToRoute('admin_absence_type_index');
    }
}
