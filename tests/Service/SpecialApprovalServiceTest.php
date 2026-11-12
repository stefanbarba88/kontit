<?php

namespace App\Tests\Service;

use App\Entity\Notification;
use App\Enum\NotificationType;
use App\Enum\ReviewMode;
use App\Enum\Role;
use App\Enum\TaskStatus;
use App\Service\ApprovalService;
use App\Service\SpecialApprovalService;
use App\Tests\Support\DatabaseTestCase;

/** Posebne potvrde: jedini proveravač (upozorenje), dnevni podsetnik bez duplikata, automatsko odobravanje ih preskače. */
class SpecialApprovalServiceTest extends DatabaseTestCase
{
    private function overdueSpecial(string $title, \App\Entity\User $worker, \App\Entity\User ...$approvers): \App\Entity\Task
    {
        $t = $this->task($title, $approvers[0], [$worker], null, null, TaskStatus::NA_PROVERI);
        $t->setReviewMode(ReviewMode::SPECIAL)->setRequiredApprovers($approvers);
        $t->setSubmittedAt(new \DateTimeImmutable('-30 days'));

        return $t;
    }

    public function testSoleApproverWarningCountsJobsAndOpenTasks(): void
    {
        $sole = $this->user('Jedini', Role::ADMIN);
        $other = $this->user('Drugi', Role::ADMIN);
        $worker = $this->user('Radnik');
        $template = $this->template('Poseban', $this->category('K'))->setRequiresApproval(true)->setApprovers([$sole]);
        $shared = $this->template('Zajednicki', $this->category('K2'))->setRequiresApproval(true)->setApprovers([$sole, $other]);
        $t = $this->task('Otvoren', $sole, [$worker]);
        $t->setReviewMode(ReviewMode::SPECIAL)->setRequiredApprovers([$sole]);
        $this->em->flush();

        $service = self::getContainer()->get(SpecialApprovalService::class);
        self::assertSame(['jobs' => 1, 'tasks' => 1], $service->soleApproverImpact($sole));
        self::assertStringContainsString('1 posebnih poslova i 1 otvorenih zadataka', (string) $service->soleApproverWarning($sole));
        self::assertNull($service->soleApproverWarning($other));
        self::assertNull($service->soleApproverWarning($worker));
    }

    public function testReminderOncePerDayAndAutoApproveSkipsSpecial(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $worker = $this->user('Radnik');
        $task = $this->overdueSpecial('Dugo čeka', $worker, $admin);
        $this->em->flush();

        $service = self::getContainer()->get(SpecialApprovalService::class);
        self::assertSame(1, $service->remind());
        self::assertSame(0, $service->remind(), 'drugi poziv istog dana ne šalje ponovo');
        $count = $this->em->getRepository(Notification::class)->count(['user' => $admin, 'type' => NotificationType::SPECIAL_APPROVAL_REMINDER]);
        self::assertSame(1, $count);

        self::getContainer()->get(ApprovalService::class)->autoApproveTasks(new \DateTimeImmutable());
        $this->em->refresh($task);
        self::assertSame(TaskStatus::NA_PROVERI, $task->getStatus(), 'posebna potvrda se nikad ne odobrava automatski');
    }
}
