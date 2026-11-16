<?php

namespace App\Command;

use App\Service\ImpersonationState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Zatvara evidenciju "Prikaži kao korisnik" kojoj je poslednja aktivnost starija od `impersonation.idle_minutes`
 * (zatvoren tab ili pregledač bez odjave). Kraj je vreme poslednje aktivnosti, razlog "Istek sesije".
 * Raspored: na svakih 15 minuta (cron izraz je u DEPLOY.md).
 */
#[AsCommand(name: 'app:impersonation:close-stale', description: 'Zatvara zapise impersonacije bez aktivnosti duže od praga')]
class ImpersonationCloseStaleCommand extends Command
{
    public function __construct(private readonly ImpersonationState $state)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $closed = $this->state->closeStale();
        if ($closed > 0 || $output->isVerbose()) {
            $output->writeln(sprintf('Zatvoreno zapisa: %d', $closed));
        }

        return Command::SUCCESS;
    }
}
