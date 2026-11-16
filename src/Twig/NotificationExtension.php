<?php

namespace App\Twig;

use App\Controller\Ajax\NotificationController;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\ImpersonationState;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Podaci za zvonce u navigaciji (prvo iscrtavanje; posle ga osvežava AJAX poll).
 */
class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly NotificationRepository $notifications,
        private readonly ImpersonationState $impersonation,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_notification_count', $this->unreadCount(...)),
            new TwigFunction('latest_notifications', $this->latest(...)),
        ];
    }

    public function unreadCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->notifications->countUnread($user, $this->impersonation->isActive()) : 0;
    }

    /**
     * @return list<Notification>
     */
    public function latest(int $limit = NotificationController::MENU_SIZE): array
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->notifications->findLatest($user, $limit, $this->impersonation->isActive()) : [];
    }
}
