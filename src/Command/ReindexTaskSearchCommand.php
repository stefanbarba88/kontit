<?php

namespace App\Command;

use App\Service\TextNormalizer;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Ponovo popunjava task.title_search (normalizovan naziv za pretragu) za sve zadatke.
 * Treba samo ako se nešto razišlo (npr. masovni SQL UPDATE naziva ili promena pravila u TextNormalizer-u).
 * Piše direktno u bazu, pa ne menja updated_at i ne pravi zapise u istoriji.
 */
#[AsCommand(name: 'app:tasks:reindex-search', description: 'Ponovo popunjava task.title_search (pretraga zadataka po nazivu) za sve zadatke')]
class ReindexTaskSearchCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TextNormalizer $normalizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Samo prebroj zadatke čija se kolona razlikuje, bez upisa');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $total = 0;
        $changed = 0;

        foreach ($this->connection->iterateAssociative('SELECT id, title, title_search FROM task') as $row) {
            ++$total;
            $search = $this->normalizer->normalize($row['title']);
            if ($search === $row['title_search']) {
                continue;
            }
            ++$changed;
            if (!$dryRun) {
                $this->connection->update('task', ['title_search' => $search], ['id' => $row['id']]);
            }
        }

        $io->success(sprintf('%s: %d od %d zadataka.', $dryRun ? 'Razlikuje se' : 'Ažurirano', $changed, $total));

        return Command::SUCCESS;
    }
}
