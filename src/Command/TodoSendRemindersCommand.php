<?php

namespace App\Command;

use App\Service\TodoReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Šalje podsetnike iz Moje liste čije je vreme stiglo. Bezbedno za preklapanje i višestruko pokretanje (svaki podsetnik tačno jednom).
 * Raspored: svakog minuta, cron "* * * * * php bin/console app:todo:send-reminders".
 */
#[AsCommand(name: 'app:todo:send-reminders', description: 'Šalje podsetnike iz Moje liste čije je vreme stiglo')]
class TodoSendRemindersCommand extends Command
{
    public function __construct(private readonly TodoReminderService $reminders)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sent = $this->reminders->sendDue();
        if ($sent > 0 || $output->isVerbose()) {
            $output->writeln(sprintf('Poslato podsetnika: %d', $sent));
        }

        return Command::SUCCESS;
    }
}
