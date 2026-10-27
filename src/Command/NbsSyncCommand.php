<?php

namespace App\Command;

use App\Repository\ClientRepository;
use App\Service\NbsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:nbs:sync', description: 'Osvežava podatke aktivnih klijenata iz NBS-a (po PIB-u)')]
class NbsSyncCommand extends Command
{
    public function __construct(
        private readonly ClientRepository $clients,
        private readonly NbsService $nbs,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('only-missing', null, InputOption::VALUE_NONE, 'Samo klijenti koji još nikad nisu povučeni iz NBS-a');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->nbs->isConfigured()) {
            $io->error('NBS pristupni podaci nisu podešeni.');

            return Command::FAILURE;
        }

        $done = $failed = 0;
        foreach ($this->clients->findBy(['isActive' => true], ['name' => 'ASC']) as $client) {
            if (null === $client->getPib() || ($input->getOption('only-missing') && null !== $client->getNbsSyncedAt())) {
                continue;
            }
            try {
                $this->nbs->refresh($client);
                $this->em->flush();
                ++$done;
            } catch (\DomainException $e) {
                ++$failed;
                $io->warning(sprintf('%s (%s): %s', $client->getName(), $client->getPib(), $e->getMessage()));
            }
        }
        $io->success(sprintf('Osveženo: %d, neuspešno: %d.', $done, $failed));

        return Command::SUCCESS;
    }
}
