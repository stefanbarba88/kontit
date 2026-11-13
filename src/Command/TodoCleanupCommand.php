<?php

namespace App\Command;

use App\Service\TodoService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Trajno briše završene stavke Podsetnika starije od `todo.done_retention_days` (3 dana, UTC). Otvorene stavke se ne diraju,
 * zadatak nastao iz stavke ostaje, a u istoriju izmena se ništa ne upisuje.
 * Raspored: jednom dnevno, cron "30 3 * * * php bin/console app:todo:cleanup".
 */
#[AsCommand(name: 'app:todo:cleanup', description: 'Briše završene stavke Podsetnika starije od roka čuvanja')]
class TodoCleanupCommand extends Command
{
    public function __construct(private readonly TodoService $todo)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deleted = $this->todo->purgeOldDone();
        if ($deleted > 0 || $output->isVerbose()) {
            $output->writeln(sprintf('Obrisano završenih stavki: %d', $deleted));
        }

        return Command::SUCCESS;
    }
}
