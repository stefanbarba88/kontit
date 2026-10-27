<?php

namespace App\Command;

use App\Service\TaskGenerationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:tasks:generate', description: 'Generiše ponavljajuće zadatke iz poslova klijenata (cron, svako jutro u 06:00)')]
class GenerateTasksCommand extends Command
{
    public function __construct(private readonly TaskGenerationService $generator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('date', null, InputOption::VALUE_REQUIRED, 'Generiši kao da je ovaj dan (YYYY-MM-DD), za proveru');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $actor = $this->generator->systemActor();
        if (null === $actor) {
            $io->error('Nema aktivnog administratora u čije ime se prave zadaci.');

            return Command::FAILURE;
        }

        $result = $this->generator->generate($actor, $input->getOption('date') ? new \DateTimeImmutable((string) $input->getOption('date')) : null);
        $io->success(sprintf('Napravljeno: %d, preskočeno (već postoje): %d.', $result['created'], $result['skipped']));
        foreach ($result['errors'] as $error) {
            $io->warning($error);
        }

        return Command::SUCCESS;
    }
}
