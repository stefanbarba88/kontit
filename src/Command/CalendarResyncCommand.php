<?php

namespace App\Command;

use App\Google\CalendarSync;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsArgument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:calendar:resync', description: 'Ponovo šalje sve buduće događaje korisnika u njegov Google kalendar (email ili ID)')]
class CalendarResyncCommand extends Command
{
    public function __construct(private readonly CalendarSync $sync, private readonly UserRepository $users)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('user', InputArgument::REQUIRED, 'Email ili ID korisnika');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $key = (string) $input->getArgument('user');
        $user = ctype_digit($key) ? $this->users->find((int) $key) : $this->users->findOneBy(['email' => $key]);
        if (null === $user) {
            $io->error('Korisnik nije pronađen.');

            return Command::FAILURE;
        }
        if (!$this->sync->isActive()) {
            $io->warning('Google kalendar nije podešen (GOOGLE_CLIENT_ID/SECRET) ili je isključen u Podešavanjima.');

            return Command::SUCCESS;
        }
        $io->success(sprintf('Usklađeno zapisa za %s: %d.', $user->getFullName(), $this->sync->resyncUser($user)));

        return Command::SUCCESS;
    }
}
