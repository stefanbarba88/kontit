<?php

namespace App\Command;

use App\Service\ApprovalService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:approvals:auto-approve', description: 'Automatski odobrava zahteve kojima je istekao rok i šalje podsetnike (cron na 15 minuta)')]
class AutoApproveCommand extends Command
{
    public function __construct(private readonly ApprovalService $approvals)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('now', null, InputOption::VALUE_REQUIRED, 'Pokreni kao da je ovaj trenutak (YYYY-MM-DD HH:MM), za proveru');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = $input->getOption('now') ? new \DateTimeImmutable((string) $input->getOption('now')) : null;
        $result = $this->approvals->run($now);
        (new SymfonyStyle($input, $output))->success(sprintf('Automatski odobreno odsustava: %d, podsetnika: %d, potvrđenih zadataka: %d.', $result['approved'], $result['reminded'], $result['tasks']));

        return Command::SUCCESS;
    }
}
