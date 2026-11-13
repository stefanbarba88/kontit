<?php

namespace App\Controller;

use App\Entity\User;
use App\Export\ExportService;
use App\Message\GenerateExport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Izvoz liste (SPEC 8e): isti filteri iz query stringa, isti upit i Voteri kao lista.
 * Do 2.000 redova odmah, a veći izvoz u pozadini sa obaveštenjem.
 */
class ExportController extends AbstractController
{
    public function __construct(
        private readonly ExportService $exports,
        #[Autowire('%kernel.project_dir%/var/exports')] private readonly string $dir,
    ) {
    }

    #[Route('/export/{list}/{format}', name: 'export_list', requirements: ['list' => '[a-z]+', 'format' => 'xlsx|csv|pdf'], methods: ['GET'])]
    public function export(Request $request, string $list, string $format, MessageBusInterface $bus): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $export = $this->exports->getList($list);
        if (null === $export) {
            throw $this->createNotFoundException();
        }
        if (!$export->isAllowed($user)) {
            throw $this->createAccessDeniedException();
        }

        $query = $request->query->all();
        unset($query['page']);

        if ($export->count($query, $user) > ExportService::LIMIT_SYNC) {
            $bus->dispatch(new GenerateExport($list, $format, $query, (int) $user->getId(), bin2hex(random_bytes(12))));
            $this->addFlash('info', 'Izvoz je veliki, pa se priprema u pozadini. Dobićeš obaveštenje sa linkom kad bude spreman.');

            return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_dashboard'));
        }

        $path = tempnam(sys_get_temp_dir(), 'kt-export-');
        $this->exports->write($export, $format, $query, $user, $path);

        return $this->file($path, $this->exports->filename($export, $format), ResponseHeaderBag::DISPOSITION_ATTACHMENT)
            ->deleteFileAfterSend();
    }

    #[Route('/exports/{token}', name: 'export_download', requirements: ['token' => '[a-f0-9]{24}'], methods: ['GET'])]
    public function download(string $token): BinaryFileResponse
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        // fajl je u folderu prijavljenog korisnika, pa tuđi izvoz nije dostupan ni sa tačnim tokenom
        $files = glob(sprintf('%s/%d/%s/*', $this->dir, $user->getId(), $token)) ?: [];
        if ([] === $files || filemtime($files[0]) < strtotime('-7 days')) {
            throw $this->createNotFoundException('Izvoz više nije dostupan. Pokreni ga ponovo.');
        }

        return $this->file($files[0], basename($files[0]));
    }
}
