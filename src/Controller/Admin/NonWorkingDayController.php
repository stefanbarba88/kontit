<?php

namespace App\Controller\Admin;

use App\Entity\NonWorkingDay;
use App\Form\NonWorkingDayType;
use App\Repository\NonWorkingDayRepository;
use App\Repository\WeeklyNonWorkingRuleRepository;
use App\Service\NonWorkingDayService;
use App\Service\SerbianHolidays;
use App\Service\WorkingDayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Podešavanja → Neradni dani (SPEC 8k): dani u nedelji, praznici, kolektivni odmor, godišnji kalendar.
 */
#[Route('/admin/non-working-days')]
#[IsGranted('CATALOG_MANAGE')]
class NonWorkingDayController extends AbstractController
{
    public function __construct(
        private readonly NonWorkingDayService $service,
        private readonly WorkingDayService $workingDays,
    ) {
    }

    #[Route('', name: 'admin_non_working_index', methods: ['GET'])]
    public function index(Request $request, NonWorkingDayRepository $days, WeeklyNonWorkingRuleRepository $rules): Response
    {
        $year = $this->year($request);

        return $this->render('admin/non_working/index.html.twig', [
            'year' => $year,
            'entries' => $days->findForYear($year),
            'weekly' => $rules->get()->getDays(),
            'special' => $this->workingDays->specialDays($year),
            'workingCount' => $this->workingDays->countWorkingDays(new \DateTimeImmutable($year.'-01-01'), new \DateTimeImmutable($year.'-12-31')),
        ]);
    }

    #[Route('/weekly', name: 'admin_non_working_weekly', methods: ['POST'])]
    public function weekly(Request $request): Response
    {
        $this->csrf($request, 'non_working_weekly');
        $this->service->saveWeekly(array_map('intval', (array) $request->request->all('days')));
        $this->addFlash('success', 'Neradni dani u nedelji su sačuvani.');

        return $this->redirectToRoute('admin_non_working_index', ['year' => $request->request->getInt('year') ?: null]);
    }

    #[Route('/new', name: 'admin_non_working_new', methods: ['GET', 'POST'])]
    #[Route('/{id}/edit', name: 'admin_non_working_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function form(Request $request, ?NonWorkingDay $day = null): Response
    {
        $isNew = null === $day;
        $day ??= new NonWorkingDay();
        $form = $this->createForm(NonWorkingDayType::class, $day);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->save($day);
            $this->addFlash('success', sprintf('„%s“ je sačuvan.', $day->getName()));

            return $this->redirectToRoute('admin_non_working_index', ['year' => $day->getDateFrom()->format('Y')]);
        }

        return $this->render('admin/non_working/form.html.twig', ['form' => $form, 'day' => $day, 'is_new' => $isNew]);
    }

    #[Route('/{id}/delete', name: 'admin_non_working_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, NonWorkingDay $day): Response
    {
        $this->csrf($request, 'non_working_delete_'.$day->getId());
        $year = $day->getDateFrom()->format('Y');
        $this->service->delete($day);
        $this->addFlash('success', 'Neradni dan je obrisan.');

        return $this->redirectToRoute('admin_non_working_index', ['year' => $year]);
    }

    /**
     * "Učitaj praznike Srbije za godinu": predlog sa štikliranjem, pa čuvanje izabranih.
     */
    #[Route('/load', name: 'admin_non_working_load', methods: ['GET', 'POST'])]
    public function load(Request $request): Response
    {
        $year = $this->year($request);
        $proposal = $this->service->proposal($year);

        if ($request->isMethod('POST')) {
            $this->csrf($request, 'non_working_load');
            $picked = array_map('intval', (array) $request->request->all('pick'));
            $created = $this->service->saveProposal(array_values(array_intersect_key($proposal, array_flip($picked))));
            $this->addFlash('success', sprintf('Dodato neradnih dana: %d.', $created));

            return $this->redirectToRoute('admin_non_working_index', ['year' => $year]);
        }

        return $this->render('admin/non_working/load.html.twig', ['year' => $year, 'proposal' => $proposal]);
    }

    private function year(Request $request): int
    {
        $year = $request->query->getInt('year') ?: $request->request->getInt('year') ?: (int) date('Y');

        return max(2000, min(2100, $year));
    }

    private function csrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Neispravan CSRF token.');
        }
    }
}
