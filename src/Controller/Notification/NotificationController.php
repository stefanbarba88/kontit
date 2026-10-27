<?php

namespace App\Controller\Notification;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notifications')]
class NotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'notification_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $onlyUnread = $request->query->getBoolean('unread');

        return $this->render('notification/index.html.twig', [
            'pagination' => $paginator->paginate(
                $this->notifications->createListQueryBuilder($this->actor(), $onlyUnread),
                $request->query->getInt('page', 1),
                20,
            ),
            'onlyUnread' => $onlyUnread,
            'unreadCount' => $this->notifications->countUnread($this->actor()),
        ]);
    }

    /**
     * Otvara notifikaciju: označava je pročitanom i vodi na link. Samo vlasnik.
     */
    #[Route('/{id}/open', name: 'notification_open', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function open(Notification $notification): Response
    {
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $notification->markRead();
        $this->em->flush();

        return $this->redirect($notification->getLink() ?? $this->generateUrl('notification_index'));
    }

    #[Route('/read-all', name: 'notification_read_all', methods: ['POST'])]
    public function readAll(Request $request): Response
    {
        if ($this->isCsrfTokenValid('notifications_read_all', $request->request->getString('_token'))) {
            $this->notifications->markAllRead($this->actor());
            $this->addFlash('success', 'Sve notifikacije su označene kao pročitane.');
        }

        return $this->redirectToRoute('notification_index');
    }

    private function actor(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
