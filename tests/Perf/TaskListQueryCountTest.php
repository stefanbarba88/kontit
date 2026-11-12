<?php

namespace App\Tests\Perf;

use App\Entity\ChecklistItem;
use App\Entity\User;
use App\Enum\Role;
use App\Service\TaskListView;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/** Broj SQL upita za jednu stranu liste (15 zadataka, 2 učesnika i 3 stavke checkliste po zadatku): bez N+1. */
class TaskListQueryCountTest extends DatabaseTestCase
{
    public function testQueryCountDoesNotGrowWithTasks(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $w1 = $this->user('Ana');
        $w2 = $this->user('Ben');
        $client = $this->client('Alfa', $w1);
        for ($i = 1; $i <= 15; ++$i) {
            $t = $this->task('Zadatak '.$i, $admin, [$w1, $w2], $client);
            for ($k = 0; $k < 3; ++$k) {
                $t->addChecklistItem(new ChecklistItem($t, 'stavka '.$k, $k));
            }
        }
        $this->em->flush();
        $this->em->clear();

        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        $holder->reset();
        $admin = $this->em->getRepository(User::class)->findOneBy(['firstName' => 'Admin']);
        $request = Request::create('/tasks');
        self::getContainer()->get(RequestStack::class)->push($request);
        $list = self::getContainer()->get(TaskListView::class)->build($request, $admin, 'task_index', []);
        foreach ($list['pagination'] as $task) {
            $task->getClient()?->getShortName();
            foreach ($task->getAssignees() as $a) {
                $a->getUser()->getFullName();
            }
            $task->getChecklistDone();
            $task->getFinishedAssigneesCount();
        }
        $n = \count($holder->getData()['default'] ?? []);
        fwrite(\STDERR, "\nUPITA PO STRANI (15 zadataka): $n\n");
        self::assertCount(15, $list['pagination']);
        self::assertLessThanOrEqual(12, $n, 'upiti ne smeju da rastu sa brojem zadataka (bilo 36 pre batch učitavanja)');
    }
}
