<?php

namespace App\Tests\Controller;

use App\Entity\Task;
use App\Entity\User;
use App\Enum\ReviewMode;
use App\Enum\Role;
use App\Enum\TaskStatus;
use App\Tests\Support\DatabaseTestCase;

/** Stranica /tasks/special-approvals: prava, opseg po proveravaču, prekidač "Sve posebne potvrde", brojač i kartica na dashboardu. */
class SpecialApprovalsPageTest extends DatabaseTestCase
{
    private function special(string $title, User $worker, User $creator, TaskStatus $status, User ...$approvers): Task
    {
        $t = $this->task($title, $creator, [$worker], null, null, $status);
        $t->setReviewMode(ReviewMode::SPECIAL)->setRequiredApprovers($approvers);
        if (TaskStatus::NA_PROVERI === $status) {
            $t->setSubmittedAt(new \DateTimeImmutable('-5 days'));
        }

        return $t;
    }

    public function testAccessScopeAndToggle(): void
    {
        $adminA = $this->user('AdminA', Role::ADMIN);
        $adminB = $this->user('AdminB', Role::ADMIN);
        $super = $this->user('Super', Role::SUPERADMIN);
        $sef = $this->user('Sef', Role::SEF);
        $worker = $this->user('Radnik');
        $this->special('Za A', $worker, $adminA, TaskStatus::NA_PROVERI, $adminA);
        $this->special('Za B', $worker, $adminA, TaskStatus::NA_PROVERI, $adminB);
        $this->special('A u radu', $worker, $adminA, TaskStatus::U_RADU, $adminA);
        $this->em->flush();

        // šef i radnik: 403
        foreach ([$sef, $worker] as $denied) {
            $this->browser->loginUser($denied);
            $this->browser->request('GET', '/tasks/special-approvals');
            self::assertResponseStatusCodeSame(403);
        }

        // admin A: vidi samo svoje; bez prekidača "Sve"
        $this->browser->loginUser($adminA);
        $this->browser->request('GET', '/tasks/special-approvals');
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Za A', $html);
        self::assertStringNotContainsString('Za B', $html);
        self::assertStringNotContainsString('Sve posebne potvrde', $html);
        // parametar all ne važi za običnog admina
        $this->browser->request('GET', '/tasks/special-approvals?all=1');
        self::assertStringNotContainsString('Za B', (string) $this->browser->getResponse()->getContent());
        // tab "U radu"
        $this->browser->request('GET', '/tasks/special-approvals?tab=special_progress');
        self::assertStringContainsString('A u radu', (string) $this->browser->getResponse()->getContent());

        // superadmin: svoje (nijedno), a sa prekidačem sve
        $this->browser->loginUser($super);
        $this->browser->request('GET', '/tasks/special-approvals');
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertStringContainsString('Sve posebne potvrde', $html);
        self::assertStringNotContainsString('Za A', $html);
        $this->browser->request('GET', '/tasks/special-approvals?all=1');
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertStringContainsString('Za A', $html);
        self::assertStringContainsString('Za B', $html);
    }

    public function testMenuCounterMatchesWaitingTabAndDashboardCard(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $worker = $this->user('Radnik');
        $this->special('Prvi', $worker, $admin, TaskStatus::NA_PROVERI, $admin);
        $this->special('Drugi', $worker, $admin, TaskStatus::NA_PROVERI, $admin);
        $this->em->flush();

        $this->browser->loginUser($admin);
        $this->browser->request('GET', '/');
        self::assertResponseIsSuccessful();
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertStringContainsString('Posebne potvrde', $html);
        self::assertStringContainsString('Čeka tvoju potvrdu: <strong>2</strong>', $html);
        // samo prvi red (Zadaci + Podsetnik) je u mreži; Posebne potvrde i ostalo su i dalje jedna kartica ispod druge
        self::assertStringNotContainsString('kt-dash-special', $html);
        self::assertStringNotContainsString('kt-dash--', $html);
        self::assertStringContainsString('/tasks/special-approvals', $html);

        $this->browser->request('GET', '/tasks/special-approvals');
        $page = (string) $this->browser->getResponse()->getContent();
        self::assertStringContainsString('Prvi', $page);
        self::assertStringContainsString('Drugi', $page);
        // čeka 5 kalendarskih dana, preko 2 radna: crveno
        self::assertStringContainsString('text-danger', $page);
        self::assertMatchesRegularExpression('/ms-2">2<\/span>/', $page);
    }

    public function testEmptyDashboardCardAndExport(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $this->em->flush();
        $this->browser->loginUser($admin);
        $this->browser->request('GET', '/');
        self::assertStringContainsString('Nema posebnih potvrda.', (string) $this->browser->getResponse()->getContent());
        $this->browser->request('GET', '/tasks/special-approvals?tab=special_done');
        self::assertResponseIsSuccessful();
    }
}
