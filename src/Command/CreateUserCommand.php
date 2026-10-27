<?php

namespace App\Command;

use App\Entity\User;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Kreira korisnika (npr. prvog superadmina)')]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email (login)')
            ->addArgument('password', InputArgument::REQUIRED, 'Lozinka')
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'Ime', 'Super')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Prezime', 'Admin')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'SUPERADMIN|ADMIN|SEF|RADNIK', 'SUPERADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $role = Role::tryFrom('ROLE_'.strtoupper($input->getOption('role')));
        if (null === $role) {
            $io->error('Nepoznata rola.');

            return Command::FAILURE;
        }

        if ($this->em->getRepository(User::class)->findOneBy(['email' => $input->getArgument('email')])) {
            $io->error('Korisnik sa tim emailom već postoji.');

            return Command::FAILURE;
        }

        $user = (new User())
            ->setEmail($input->getArgument('email'))
            ->setFirstName($input->getOption('first-name'))
            ->setLastName($input->getOption('last-name'))
            ->setRoles([$role->value]);
        $user->setPassword($this->hasher->hashPassword($user, $input->getArgument('password')));

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Kreiran korisnik %s (%s).', $user->getEmail(), $role->value));

        return Command::SUCCESS;
    }
}
