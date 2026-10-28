<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Repository\UserRepository;
use App\Service\FinanceService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Finansijska analitika i podešavanja. Samo admin+ (PRICE_VIEW).
 */
#[Route('/admin/finance')]
#[IsGranted('PRICE_VIEW')]
class FinanceController extends AbstractController
{
    public function __construct(private readonly FinanceService $finance)
    {
    }

    #[Route('', name: 'admin_finance', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/finance/index.html.twig', ['data' => $this->finance->overview()]);
    }

    #[Route('/client/{id}', name: 'admin_finance_client', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function client(Client $client): Response
    {
        return $this->render('admin/finance/client.html.twig', ['client' => $client, 'a' => $this->finance->clientAnalytics($client)]);
    }

    #[Route('/billing', name: 'admin_finance_billing', methods: ['GET'])]
    public function billing(Request $request): Response
    {
        $month = $this->parseMonth($request);

        return $this->render('admin/finance/billing.html.twig', [
            'month' => $month,
            'rows' => $this->finance->billing($month),
        ]);
    }

    #[Route('/billing.csv', name: 'admin_finance_billing_csv', methods: ['GET'])]
    public function billingCsv(Request $request): Response
    {
        $month = $this->parseMonth($request);
        $rows = $this->finance->billing($month);

        $response = new StreamedResponse(static function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Klijent', 'PIB', 'Paket', 'Pojedinačni', 'Ukupno', 'Stavke'], ';');
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['client']->getName(),
                    $row['client']->getPib(),
                    number_format($row['package'], 2, ',', ''),
                    number_format($row['adHoc'], 2, ',', ''),
                    number_format($row['total'], 2, ',', ''),
                    implode(', ', $row['items']),
                ], ';');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="za-naplatu-'.$month->format('Y-m').'.csv"');

        return $response;
    }

    #[Route('/settings', name: 'admin_finance_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, SettingsService $settings, UserRepository $users, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('finance_settings', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Neispravan CSRF token.');
            }
            $min = str_replace(',', '.', (string) $request->request->get('min_hourly_rate'));
            $capacity = (string) $request->request->get('default_capacity_hours');
            if (is_numeric($min) && (float) $min >= 0 && ctype_digit($capacity) && (int) $capacity > 0) {
                $settings->set(SettingsService::MIN_HOURLY_RATE, (string) (float) $min);
                $settings->set(SettingsService::DEFAULT_CAPACITY_HOURS, $capacity);
                foreach ((array) $request->request->all('capacity') as $userId => $hours) {
                    $user = $users->find((int) $userId);
                    if (null !== $user) {
                        $user->setCapacityHours(ctype_digit((string) $hours) && (int) $hours > 0 ? (int) $hours : null);
                    }
                }
                $em->flush();
                $this->addFlash('success', 'Podešavanja su sačuvana.');
            } else {
                $this->addFlash('danger', 'Unesi ispravne vrednosti.');
            }

            return $this->redirectToRoute('admin_finance_settings');
        }

        return $this->render('admin/finance/settings.html.twig', [
            'minRate' => $settings->get(SettingsService::MIN_HOURLY_RATE),
            'capacity' => $settings->get(SettingsService::DEFAULT_CAPACITY_HOURS),
            'users' => $users->findBy(['isActive' => true], ['lastName' => 'ASC']),
        ]);
    }

    private function parseMonth(Request $request): \DateTimeImmutable
    {
        $value = (string) $request->query->get('month', '');
        $month = \DateTimeImmutable::createFromFormat('!Y-m', $value);

        return false !== $month ? $month : new \DateTimeImmutable('first day of this month midnight');
    }
}
