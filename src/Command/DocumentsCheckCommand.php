<?php

namespace App\Command;

use App\Enum\AbsenceStatus;
use App\Enum\NotificationType;
use App\Repository\UserAbsenceRepository;
use App\Repository\UserDocumentRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Dnevna provera dokumenata (SPEC 8l, 8j.3): adminima obaveštenje 30 i 7 dana pre isteka dokumenta (validUntil),
 * a za odsustvo koje traži dokument (doznaka) i posle 3 dana ga nema, obaveštenje korisniku i adminima (jednom).
 */
#[AsCommand(name: 'app:documents:check', description: 'Obaveštenja o dokumentima koji ističu i obaveštenje za doznaku (cron jednom dnevno)')]
class DocumentsCheckCommand extends Command
{
    private const STEPS = [30, 7];

    public function __construct(
        private readonly UserDocumentRepository $documents,
        private readonly UserAbsenceRepository $absences,
        private readonly UserRepository $users,
        private readonly NotificationService $notifications,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('date', null, InputOption::VALUE_REQUIRED, 'Pokreni kao da je ovaj dan (YYYY-MM-DD)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = new \DateTimeImmutable($input->getOption('date') ?: 'today');
        $admins = $this->users->findActiveAdmins();

        $expiring = 0;
        foreach ($this->documents->findExpiringBefore($today->modify('+30 days')) as $doc) {
            $days = (int) $today->diff($doc->getValidUntil())->format('%r%a');
            // najmanji prag koji je dostignut, a za koji još nije poslato (30, pa 7)
            $step = null;
            foreach (self::STEPS as $s) {
                if ($days <= $s && (null === $doc->getExpiryNotifiedDays() || $doc->getExpiryNotifiedDays() > $s)) {
                    $step = $s;
                }
            }
            if (null === $step) {
                continue;
            }
            $this->notifications->notifyMany($admins, NotificationType::DOCUMENT_EXPIRING, 'Dokument uskoro ističe', sprintf('%s: „%s“ (%s) važi do %s (još %d dana).', $doc->getUser()->getFullName(), $doc->getTitle(), $doc->getCategory()->getName(), $doc->getValidUntil()->format('d.m.Y.'), $days), ['user_documents', ['id' => $doc->getUser()->getId()]]);
            $doc->setExpiryNotifiedDays($step);
            ++$expiring;
        }

        $reminders = 0;
        $missing = $this->absences->createQueryBuilder('a')
            ->join('a.type', 't')
            ->andWhere('t.requiresDocument = true AND a.status IN (:ok) AND a.startDate <= :limit AND a.documentReminderSent = false')
            ->setParameter('ok', AbsenceStatus::approved())
            ->setParameter('limit', $today->modify('-3 days'))
            ->getQuery()->getResult();
        foreach ($missing as $absence) {
            if ($this->documents->countForAbsence($absence) > 0) {
                $absence->setDocumentReminderSent(true);
                continue;
            }
            $route = ['user_documents', ['id' => $absence->getUser()->getId(), 'absence' => $absence->getId()]];
            $this->notifications->notify($absence->getUser(), NotificationType::DOCUMENT_EXPIRING, 'Nedostaje dokument uz odsustvo', sprintf('Za odsustvo %s nije priložen dokument (npr. doznaka). Dodaj ga u Dokumentima.', $absence->summary()), $route);
            $this->notifications->notifyMany($admins, NotificationType::DOCUMENT_EXPIRING, 'Nedostaje doznaka', sprintf('%s nije priložio dokument za odsustvo %s.', $absence->getUser()->getFullName(), $absence->summary()), $route);
            $absence->setDocumentReminderSent(true);
            ++$reminders;
        }
        $this->em->flush();

        (new SymfonyStyle($input, $output))->success(sprintf('Obaveštenja o isteku: %d, obaveštenja za dokument: %d.', $expiring, $reminders));

        return Command::SUCCESS;
    }
}
