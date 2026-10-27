<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * In-app notifikacije (SPEC 7) i opcioni mail. Mail ide preko MailerInterface, a Messenger
 * ga šalje asinhrono (SendEmailMessage je rutiran na "async"), pa akcija korisnika ne čeka SMTP.
 */
class NotificationService
{
    private const MAIL_FROM = 'no-reply@kontit.local';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $router,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Obaveštava jednog korisnika. Ne obaveštava aktera o sopstvenoj akciji ni neaktivne korisnike.
     * Mail se šalje ako je $email (checkbox "Pošalji i na mail") ili ako je korisnik u profilu
     * izabrao ovu vrstu obaveštenja za mail.
     *
     * @param array{0: string, 1?: array<string, mixed>}|null $route [ime rute, parametri]
     */
    public function notify(
        User $recipient,
        NotificationType $type,
        string $title,
        string $message,
        ?array $route = null,
        ?User $actor = null,
        bool $email = false,
    ): ?Notification {
        if ($recipient === $actor || !$recipient->isActive()) {
            return null;
        }

        $notification = new Notification($recipient, $type, $title, $message, null !== $route ? $this->path($route) : null);
        $this->em->persist($notification);

        if (($email || \in_array($type->value, $recipient->getEmailNotifications(), true)) && $recipient->getEmail()) {
            $notification->setEmailSent($this->sendMail($recipient, $title, $message, $type, $route));
        }

        $this->em->flush();

        return $notification;
    }

    /**
     * @param iterable<User>                                   $recipients
     * @param array{0: string, 1?: array<string, mixed>}|null $route
     */
    public function notifyMany(iterable $recipients, NotificationType $type, string $title, string $message, ?array $route = null, ?User $actor = null, bool $email = false): void
    {
        $seen = [];
        foreach ($recipients as $recipient) {
            if (isset($seen[spl_object_id($recipient)])) {
                continue;
            }
            $seen[spl_object_id($recipient)] = true;
            $this->notify($recipient, $type, $title, $message, $route, $actor, $email);
        }
    }

    /**
     * @param array{0: string, 1?: array<string, mixed>} $route
     */
    public function path(array $route): string
    {
        return $this->router->generate($route[0], $route[1] ?? [], UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * @param array{0: string, 1?: array<string, mixed>}|null $route
     */
    private function sendMail(User $recipient, string $title, string $message, NotificationType $type, ?array $route): bool
    {
        try {
            $this->mailer->send((new TemplatedEmail())
                ->from(new Address(self::MAIL_FROM, 'KontIT app'))
                ->to(new Address((string) $recipient->getEmail(), $recipient->getFullName()))
                ->subject($title)
                ->htmlTemplate('emails/notification.html.twig')
                ->context([
                    'title' => $title,
                    'message' => $message,
                    'type' => $type,
                    'url' => null !== $route ? $this->router->generate($route[0], $route[1] ?? [], UrlGeneratorInterface::ABSOLUTE_URL) : null,
                ]));

            return true;
        } catch (\Throwable $e) {
            // notifikacija u aplikaciji ostaje, a greška mejla ne sme da obori akciju korisnika
            $this->logger->error('Slanje notifikacije na mail nije uspelo: '.$e->getMessage());

            return false;
        }
    }
}
