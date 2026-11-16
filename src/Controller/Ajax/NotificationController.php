<?php

namespace App\Controller\Ajax;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use App\Service\ImpersonationState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Zvonce u navigaciji: broj nepročitanih i poslednjih 10 (osvežava se AJAX poll-om na 60 s).
 */
class NotificationController extends AbstractAjaxController
{
    public const MENU_SIZE = 10;

    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly EntityManagerInterface $em,
        private readonly ImpersonationState $impersonation,
    ) {
    }

    #[Route('/ajax/notifications', name: 'ajax_notifications', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->ok($this->state());
    }

    #[Route('/ajax/notifications/{id}/read', name: 'ajax_notification_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function read(Notification $notification): JsonResponse
    {
        if ($notification->getUser() !== $this->getUser()) {
            return $this->fail('Nemaš pravo na ovu akciju.', 403);
        }

        $notification->markRead();
        $this->em->flush();

        return $this->ok($this->state());
    }

    #[Route('/ajax/notifications/read-all', name: 'ajax_notifications_read_all', methods: ['POST'])]
    public function readAll(): JsonResponse
    {
        $this->notifications->markAllRead($this->actor());

        return $this->ok($this->state(), 'Sve je označeno kao pročitano.');
    }

    /**
     * @return array{count: int, html: string}
     */
    private function state(): array
    {
        $user = $this->actor();
        $this->em->clear(Notification::class);
        $count = $this->notifications->countUnread($user, $this->impersonation->isActive());

        return [
            'count' => $count,
            'html' => $this->renderView('notification/_menu.html.twig', [
                'items' => $this->notifications->findLatest($user, self::MENU_SIZE, $this->impersonation->isActive()),
                'count' => $count,
            ]),
        ];
    }
}
