<?php

namespace App\Command;

use App\Enum\AssigneeStatus;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use App\Repository\TaskRepository;
use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Obaveštava učesnike o zadacima čiji rok ističe (TASK_DUE_SOON) ili je prošao (TASK_OVERDUE).
 * Svaka vrsta se šalje jednom po zadatku i korisniku, pa je bezbedno pokretati ga više puta dnevno.
 * Raspored: jednom dnevno, npr. cron "0 7 * * * php bin/console app:notifications:due".
 */
#[AsCommand(name: 'app:notifications:due', description: 'Šalje notifikacije o zadacima čiji rok ističe ili je prošao')]
class NotificationsDueCommand extends Command
{
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationService $notifications,
        private readonly \App\Service\SpecialApprovalService $specialApprovals,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Koliko dana unapred se rok smatra "uskoro"', '3');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $today = new \DateTimeImmutable('today');
        $sent = 0;

        foreach ($this->tasks->findWorkableDueUntil($today->modify(sprintf('+%d days', max(0, (int) $input->getOption('days'))))) as $task) {
            $overdue = $task->getDueDate() < $today;
            $type = $overdue ? NotificationType::TASK_OVERDUE : NotificationType::TASK_DUE_SOON;
            $route = ['task_show', ['id' => $task->getId()]];
            $link = $this->notifications->path($route);

            foreach ($task->getAssignees() as $assignee) {
                $user = $assignee->getUser();
                if (AssigneeStatus::ZAVRSIO === $assignee->getStatus() || !$user->isActive() || $this->notificationRepository->exists($user, $type, $link)) {
                    continue;
                }

                $this->notifications->notify(
                    $user,
                    $type,
                    $overdue ? 'Zadatak kasni' : 'Rok zadatka uskoro ističe',
                    sprintf($overdue ? 'Rok za zadatak „%s“ je bio %s.' : 'Rok za zadatak „%s“ je %s.', $task->getTitle(), $task->getDueDate()->format('d.m.Y.')),
                    $route,
                );
                ++$sent;
            }
        }

        // podsetnik proveravačima: posebna potvrda koja čeka duže od 2 radna dana (jednom dnevno po proveravaču)
        $reminded = $this->specialApprovals->remind();

        $io->success(sprintf('Poslato notifikacija: %d, podsetnika za posebne potvrde: %d.', $sent, $reminded));

        return Command::SUCCESS;
    }
}
