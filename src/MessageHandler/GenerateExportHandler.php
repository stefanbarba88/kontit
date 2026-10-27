<?php

namespace App\MessageHandler;

use App\Enum\NotificationType;
use App\Export\ExportService;
use App\Message\GenerateExport;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateExportHandler
{
    public function __construct(
        private readonly ExportService $exports,
        private readonly UserRepository $users,
        private readonly NotificationService $notifications,
        #[Autowire('%kernel.project_dir%/var/exports')] private readonly string $dir,
    ) {
    }

    public function __invoke(GenerateExport $message): void
    {
        $user = $this->users->find($message->userId);
        $list = $this->exports->getList($message->list);
        if (null === $user || null === $list || !$user->isActive() || !$list->isAllowed($user)) {
            return;
        }

        $this->removeExpired();

        // fajl je u folderu korisnika, pa ga preuzima samo on (ExportController::download)
        $folder = sprintf('%s/%d/%s', $this->dir, $user->getId(), $message->token);
        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            throw new \RuntimeException('Folder za izvoz nije napravljen.');
        }
        $filename = $this->exports->filename($list, $message->format);
        $count = $this->exports->write($list, $message->format, $message->query, $user, $folder.'/'.$filename);

        $this->notifications->notify(
            $user,
            NotificationType::EXPORT_READY,
            'Izvoz je spreman',
            sprintf('%s: %d redova (%s). Fajl je dostupan 7 dana.', $list->getTitle(), $count, strtoupper($message->format)),
            ['export_download', ['token' => $message->token]],
        );
    }

    /**
     * Izvozi stariji od 7 dana se brišu (link u notifikaciji tada više ne radi).
     */
    private function removeExpired(): void
    {
        foreach (glob($this->dir.'/*/*/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < strtotime('-7 days')) {
                unlink($file);
                @rmdir(\dirname($file));
            }
        }
    }
}
