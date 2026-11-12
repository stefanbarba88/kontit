<?php

namespace App\Tests\Service;

use App\Entity\Position;
use App\Entity\Task;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\CompletionMode;
use App\Enum\ReviewMode;
use App\Enum\Role;
use App\Enum\TaskStatus;
use App\Service\ReviewChoice;
use App\Service\TaskCompletionService;
use App\Service\TaskReviewPolicy;
use App\Service\TaskService;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Provera zadataka: TaskReviewPolicy (pozicija, poseban posao, izbor na zadatku) i TASK_APPROVE.
 */
class TaskReviewTest extends DatabaseTestCase
{
    private function position(string $name, bool $review): Position
    {
        $p = (new Position($name))->setRequiresTaskReview($review);
        $this->em->persist($p);

        return $p;
    }

    private function team(User $leader, User ...$members): Team
    {
        $team = (new Team())->setName('Tim '.$leader->getFirstName())->setLeader($leader);
        $this->em->persist($team);
        foreach ($members as $m) {
            $this->em->persist(new TeamMember($team, $m));
        }
        $this->em->flush();

        return $team;
    }

    private function can(User $user, string $attribute, mixed $subject): bool
    {
        $storage = self::getContainer()->get('security.token_storage');
        $storage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        return self::getContainer()->get('security.authorization_checker')->isGranted($attribute, $subject);
    }

    private function done(Task $task, User $user): void
    {
        self::getContainer()->get(TaskCompletionService::class)->markDone($task, $user);
    }

    /** posao koji traži posebnu potvrdu + ugovoreni posao na klijentu */
    private function specialJob(User $worker, User ...$approvers): \App\Entity\ClientJob
    {
        $template = $this->template('Poseban', $this->category('K'))->setRequiresApproval(true)->setApprovers($approvers);
        $this->em->flush();

        return $this->clientJob($this->client('Kl', $worker), $template);
    }

    public function testEntryLevelGoesToLeaderReviewAndSamostalniFinishesDirectly(): void
    {
        $leader = $this->user('Sef', Role::SEF);
        $entry = $this->user('Entry')->setPosition($this->position('Entry level', true));
        $senior = $this->user('Senior')->setPosition($this->position('Samostalni', false));
        $this->team($leader, $entry, $senior);

        $a = $this->task('A', $leader, [$entry]);
        $this->done($a, $entry);
        self::assertSame(TaskStatus::NA_PROVERI, $a->getStatus());
        self::assertFalse($a->isCompletedWithoutReview());
        self::assertTrue($this->can($leader, 'TASK_APPROVE', $a));

        $b = $this->task('B', $leader, [$senior]);
        $this->done($b, $senior);
        self::assertSame(TaskStatus::ZAVRSENO, $b->getStatus());
        self::assertTrue($b->isCompletedWithoutReview());
        self::assertNull($b->getApprovedBy());
        self::assertNull($b->getApprovedAt());
    }

    public function testPromotionMidTaskIsReadAtSubmission(): void
    {
        $junior = $this->position('Junior', true);
        $senior = $this->position('Samostalni', false);
        $u = $this->user('Napredni')->setPosition($junior);
        $task = $this->task('T', $u, [$u]);
        $u->setPosition($senior); // napredovao pre slanja
        $this->em->flush();
        $this->done($task, $u);

        self::assertSame(TaskStatus::ZAVRSENO, $task->getStatus());
    }

    public function testMultipleAssigneesAndAnyMode(): void
    {
        $leader = $this->user('Sef2', Role::SEF);
        $junior = $this->user('Jun')->setPosition($this->position('Junior', true));
        $senior = $this->user('Sen')->setPosition($this->position('Samostalni', false));
        $this->team($leader, $junior, $senior);

        $all = $this->task('ALL', $leader, [$junior, $senior]);
        $this->done($all, $junior);
        $this->done($all, $senior);
        self::assertSame(TaskStatus::NA_PROVERI, $all->getStatus());

        $any = $this->task('ANY', $leader, [$junior, $senior])->setCompletionMode(CompletionMode::ANY);
        $this->em->flush();
        $this->done($any, $senior); // završio samo Samostalni, Junior nije
        self::assertSame(TaskStatus::ZAVRSENO, $any->getStatus());
    }

    public function testSpecialJobOnlyApproversCanApproveNotLeader(): void
    {
        $leader = $this->user('Sef3', Role::SEF);
        $entry = $this->user('Ent3')->setPosition($this->position('Entry level', true));
        $senior = $this->user('Sen3')->setPosition($this->position('Samostalni', false));
        $this->team($leader, $entry, $senior);
        $approver = $this->user('Adm', Role::ADMIN);
        $otherAdmin = $this->user('Adm2', Role::ADMIN);
        $super = $this->user('Sup', Role::SUPERADMIN);
        $job = $this->specialJob($senior, $approver);

        $service = self::getContainer()->get(TaskService::class);
        foreach ([$senior, $entry] as $worker) {
            $task = (new Task())->setTitle('S '.$worker->getFirstName())->setClient($job->getClient())->linkJob($job);
            $service->create($task, [$worker], $worker === $senior ? $senior : $leader, false, true);
            $this->em->flush();
            self::assertSame(ReviewMode::SPECIAL, $task->getReviewMode());
            $this->done($task, $worker);
            self::assertSame(TaskStatus::NA_PROVERI, $task->getStatus(), 'Pozicija se ne gleda.');
            self::assertTrue($this->can($approver, 'TASK_APPROVE', $task));
            self::assertTrue($this->can($super, 'TASK_APPROVE', $task));
            self::assertFalse($this->can($leader, 'TASK_APPROVE', $task), 'Šef ne potvrđuje posebnu potvrdu.');
            self::assertFalse($this->can($otherAdmin, 'TASK_APPROVE', $task), 'Admin koji nije izabran ne potvrđuje.');
        }
    }

    public function testApproverDoesNotApproveOwnTaskAndSnapshotSurvivesJobChange(): void
    {
        $approver = $this->user('Adm4', Role::ADMIN);
        $second = $this->user('Adm5', Role::ADMIN);
        $super = $this->user('Sup4', Role::SUPERADMIN);
        $job = $this->specialJob($approver, $approver, $second);
        $task = (new Task())->setTitle('Moj')->setClient($job->getClient())->linkJob($job);
        self::getContainer()->get(TaskService::class)->create($task, [$approver], $approver, false, true);
        $this->em->flush();
        $this->done($task, $approver);

        self::assertFalse($this->can($approver, 'TASK_APPROVE', $task), 'Niko ne potvrđuje sopstveni zadatak.');
        self::assertTrue($this->can($second, 'TASK_APPROVE', $task));
        self::assertTrue($this->can($super, 'TASK_APPROVE', $task));

        // izmena posla posle kreiranja ne menja postojeći zadatak
        $job->getJobTemplate()->setRequiresApproval(false)->setApprovers([]);
        $this->em->flush();
        self::assertSame(ReviewMode::SPECIAL, $task->getReviewMode());
        self::assertCount(2, $task->getRequiredApprovers());
    }

    public function testInactiveApproverFallsBackToSuperadmin(): void
    {
        $approver = $this->user('Adm6', Role::ADMIN);
        $super = $this->user('Sup6', Role::SUPERADMIN);
        $worker = $this->user('Rad6');
        $job = $this->specialJob($worker, $approver);
        $task = (new Task())->setTitle('F')->setClient($job->getClient())->linkJob($job);
        self::getContainer()->get(TaskService::class)->create($task, [$worker], $worker, false, true);
        $this->em->flush();
        $this->done($task, $worker);
        $approver->setIsActive(false);
        $this->em->flush();

        $policy = self::getContainer()->get(TaskReviewPolicy::class);
        self::assertTrue($policy->fallsBackToSuperadmin($task));
        self::assertSame([$super], array_values(array_filter($policy->specialApprovers($task), static fn (User $u) => $u === $super)));
        self::assertFalse($this->can($approver, 'TASK_APPROVE', $task));
        self::assertTrue($this->can($super, 'TASK_APPROVE', $task));
    }

    public function testCreatorRulesOnTask(): void
    {
        $service = self::getContainer()->get(TaskService::class);
        $leader = $this->user('Sef7', Role::SEF);
        $entry = $this->user('Ent7')->setPosition($this->position('Entry level', true));
        $senior = $this->user('Sen7')->setPosition($this->position('Samostalni', false));
        $admin = $this->user('Adm7', Role::ADMIN);
        $this->team($leader, $entry, $senior);

        // Entry level pravi sebi i šalje NONE: server forsira AUTO
        $t1 = (new Task())->setTitle('t1');
        $service->create($t1, [$entry], $entry, false, false, new ReviewChoice(ReviewMode::NONE));
        self::assertSame(ReviewMode::AUTO, $t1->getReviewMode());

        // Samostalni sebi: NONE nije ponuđen (forsira AUTO), SPECIAL jeste
        $t2 = (new Task())->setTitle('t2');
        $service->create($t2, [$senior], $senior, false, false, new ReviewChoice(ReviewMode::NONE));
        self::assertSame(ReviewMode::AUTO, $t2->getReviewMode());
        $t3 = (new Task())->setTitle('t3');
        $service->create($t3, [$senior], $senior, false, false, new ReviewChoice(ReviewMode::SPECIAL, [$admin]));
        self::assertSame(ReviewMode::SPECIAL, $t3->getReviewMode());
        self::assertSame($senior, $t3->getReviewDecidedBy());
        $this->em->flush();
        $this->done($t3, $senior);
        self::assertSame(TaskStatus::NA_PROVERI, $t3->getStatus());

        // šef pravi Entry levelu sa NONE: završava se bez provere
        $t4 = (new Task())->setTitle('t4');
        $service->create($t4, [$entry], $leader, false, false, new ReviewChoice(ReviewMode::NONE));
        $this->em->flush();
        self::assertSame(ReviewMode::NONE, $t4->getReviewMode());
        $this->done($t4, $entry);
        self::assertSame(TaskStatus::ZAVRSENO, $t4->getStatus());
        self::assertTrue($t4->isCompletedWithoutReview());

        // SPECIAL bez proveravača se odbija
        $this->expectException(\DomainException::class);
        $service->create((new Task())->setTitle('t5'), [$senior], $senior, false, false, new ReviewChoice(ReviewMode::SPECIAL, []));
    }

    public function testReviewModeChangeRules(): void
    {
        $service = self::getContainer()->get(TaskService::class);
        $leader = $this->user('Sef8', Role::SEF);
        $otherLeader = $this->user('Sef9', Role::SEF);
        $entry = $this->user('Ent8')->setPosition($this->position('Entry level', true));
        $admin = $this->user('Adm8', Role::ADMIN);
        $this->team($leader, $entry);
        $this->team($otherLeader);
        $policy = self::getContainer()->get(TaskReviewPolicy::class);

        $task = $this->task('M', $leader, [$entry]);
        self::assertTrue($policy->canChangeMode($task, $leader));
        self::assertTrue($policy->canChangeMode($task, $admin));
        self::assertFalse($policy->canChangeMode($task, $otherLeader), 'Šef iz drugog tima ne menja.');
        self::assertFalse($policy->canChangeMode($task, $entry), 'Radnik koji nije kreirao ne menja.');

        $service->update($task, [$entry], $leader, false, new ReviewChoice(ReviewMode::NONE));
        self::assertSame(ReviewMode::NONE, $task->getReviewMode());
        self::assertSame($leader, $task->getReviewDecidedBy());

        // posle slanja na proveru izmena je odbijena
        $task->setReviewMode(ReviewMode::AUTO)->setReviewDecision(null);
        $this->done($task, $entry);
        self::assertSame(TaskStatus::NA_PROVERI, $task->getStatus());
        self::assertFalse($policy->canChangeMode($task, $leader));
        $this->expectException(\DomainException::class);
        $service->update($task, [$entry], $leader, false, new ReviewChoice(ReviewMode::NONE));
    }

    public function testJobLockedTaskCannotBeSwitchedToNone(): void
    {
        $approver = $this->user('Adm10', Role::ADMIN);
        $leader = $this->user('Sef10', Role::SEF);
        $worker = $this->user('Rad10');
        $this->team($leader, $worker);
        $job = $this->specialJob($worker, $approver);
        $task = (new Task())->setTitle('L')->setClient($job->getClient())->linkJob($job);
        $service = self::getContainer()->get(TaskService::class);
        $service->create($task, [$worker], $leader, false, false, new ReviewChoice(ReviewMode::NONE));
        $this->em->flush();

        self::assertSame(ReviewMode::SPECIAL, $task->getReviewMode());
        self::assertTrue($task->isReviewFromJob());
        self::assertFalse(self::getContainer()->get(TaskReviewPolicy::class)->canChangeMode($task, $leader));
        $this->expectException(\DomainException::class);
        $service->update($task, [$worker], $leader, false, new ReviewChoice(ReviewMode::NONE));
    }
}
