<?php

namespace App\Command;

use App\Entity\Position;
use App\Service\LegacyPositionMigrator;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prenosi staru slobodnu tekstualnu poziciju (app_user.position) u entitet Position (app_user.position_id).
 * Poređenje ide preko TextNormalizer-a (velika/mala slova, kvačice, ćirilica). Vrednost koja se ne poklapa ni sa jednom
 * pozicijom pravi NOVU poziciju (navedena u izveštaju). Idempotentna: ne dira korisnike koji već imaju position_id.
 * Ne pravi zapise u UserPositionLog (početno mapiranje nije napredovanje). Logika je u LegacyPositionMigrator.
 * Redosled na produkciji: migracija A → ova komanda sa --dry-run → pregled → komanda → migracija B (DEPLOY.md).
 */
#[AsCommand(name: 'app:positions:migrate-legacy', description: 'Prenosi staru tekstualnu poziciju korisnika u entitet Position (--dry-run samo ispisuje izveštaj)')]
class MigrateLegacyPositionsCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LegacyPositionMigrator $migrator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Ništa ne upisuje, samo ispisuje izveštaj');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        // stara kolona se čita direktno (iz User entiteta je uklonjena); posle migracije B više ne postoji
        if (!$this->connection->createSchemaManager()->introspectTable('app_user')->hasColumn('position')) {
            $io->success('Stara kolona app_user.position više ne postoji (migracija B je izvršena), nema šta da se prenosi.');

            return Command::SUCCESS;
        }
        $legacy = $this->connection->fetchAllKeyValue('SELECT id, position FROM app_user WHERE position IS NOT NULL ORDER BY id');
        ['rows' => $rows, 'created' => $created, 'skipped' => $skipped] = $this->migrator->migrate($legacy, $dryRun);

        $io->title($dryRun ? 'Prenos pozicija: PREGLED (ništa nije upisano)' : 'Prenos pozicija');
        if ([] === $rows) {
            $io->writeln('Nema korisnika za prenos.');
        } else {
            $io->table(['ID', 'Korisnik', 'Stara vrednost', 'Pozicija'], $rows);
        }
        if ([] !== $created) {
            $io->section('Nove pozicije');
            $io->listing(array_map(static fn (Position $p) => $p->getName(), $created));
        }
        $io->success(sprintf('%s: korisnika %d, novih pozicija %d, preskočeno (već imaju poziciju) %d.', $dryRun ? 'Bilo bi preneseno' : 'Preneseno', \count($rows), \count($created), $skipped));

        return Command::SUCCESS;
    }
}
