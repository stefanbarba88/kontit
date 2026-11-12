<?php

namespace App\Tests\Service;

use App\Enum\Role;
use App\Enum\TaskStatus;
use App\Service\StatisticsService;
use App\Tests\Support\DatabaseTestCase;

/** Backfill finished_at (migracije 134053 + 140000) daje statistici prošlog meseca iste brojeve kao stari izračun po approved_at. */
class FinishedAtBackfillTest extends DatabaseTestCase
{
    public function testBackfillKeepsLastMonthCounts(): void
    {
        $worker = $this->user('Radnik');
        $admin = $this->user('Admin', Role::ADMIN);
        $mid = new \DateTimeImmutable('first day of last month 12:00');
        $this->em->flush();
        $db = $this->em->getConnection();
        $ids = [];
        foreach ([[$mid, true], [$mid->modify('+3 days'), true], [$mid->modify('+5 days'), false]] as $i => [$when, $hasApproved]) {
            $t = $this->task('T'.$i, $admin, [$worker], null, null, TaskStatus::ZAVRSENO);
            $this->em->flush();
            $ids[] = $t->getId();
            $db->executeStatement('UPDATE task SET approved_at = :a, updated_at = :u, finished_at = NULL WHERE id = :id', [
                'a' => $hasApproved ? $when->format('Y-m-d H:i:s') : null, 'u' => $when->format('Y-m-d H:i:s'), 'id' => $t->getId(),
            ]);
        }
        $this->em->clear();

        $first = new \DateTimeImmutable('first day of this month midnight');
        $from = $first->modify('-1 month')->format('Y-m-d');
        $to = $first->format('Y-m-d');
        // stari izračun: approved_at (nepotvrđeni red se ranije nije računao uopšte)
        $before = (int) $db->fetchOne("SELECT COUNT(*) FROM task WHERE id IN (".implode(',', $ids).") AND approved_at >= :f AND approved_at < :t", ['f' => $from, 't' => $to]);

        // migracije
        $db->executeStatement("UPDATE task SET finished_at = approved_at WHERE status = 'ZAVRSENO' AND approved_at IS NOT NULL AND finished_at IS NULL");
        $db->executeStatement("UPDATE task SET finished_at = updated_at WHERE status = 'ZAVRSENO' AND finished_at IS NULL");

        $after = (int) $db->fetchOne("SELECT COUNT(*) FROM task WHERE id IN (".implode(',', $ids).") AND finished_at >= :f AND finished_at < :t", ['f' => $from, 't' => $to]);
        self::assertSame(2, $before);
        self::assertSame(3, $after, 'zadatak bez approved_at sada ulazi preko updated_at');

        self::assertSame(3, self::getContainer()->get(StatisticsService::class)->forWorker($this->em->find(\App\Entity\User::class, $worker->getId()))['doneLastMonth']);
    }
}
