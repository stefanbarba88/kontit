<?php

namespace App\Tests\Controller;

use App\Entity\ClientJob;
use App\Entity\Schedule;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\Role;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use App\Service\ScheduleCalculator;
use App\Service\TaskGenerationService;
use App\Tests\Support\DatabaseTestCase;

/** "Rok i raspored" na zadatku (SPEC 8g.5): ručni rok, primena na buduće periode, prava i oznaka. */
class TaskDueTest extends DatabaseTestCase
{
    private string $csrf = '';
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('Admin', Role::ADMIN);
    }

    private function login(User $user): void
    {
        $this->browser->loginUser($user);
        $crawler = $this->browser->request('GET', '/podsetnik');
        $this->csrf = (string) $crawler->filter('meta[name="csrf-token"]')->attr('content');
    }

    /** @return array<string, mixed> */
    private function api(string $url, array $payload): array
    {
        $this->browser->request('POST', $url, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $this->csrf], json_encode($payload));

        return json_decode((string) $this->browser->getResponse()->getContent(), true) ?? [];
    }

    private function day(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }

    private function fresh(object $entity): object
    {
        $id = $entity->getId();
        $this->em->clear();

        return $this->em->find($entity::class, $id);
    }

    /** posao (3. u mesecu, 5 dana unapred), zadaci za mart i april 2027 generisani */
    private function job(?User $worker = null): ClientJob
    {
        $template = $this->template('PDV', $this->category('K'))->setSchedule(Schedule::monthly(3, 5));
        $job = $this->clientJob($this->client('Alfa', $worker ?? $this->user('Radnik')), $template)->setStartDate($this->day('2027-03-01'));
        $this->em->flush();
        $generator = self::getContainer()->get(TaskGenerationService::class);
        $generator->generate($this->admin, $this->day('2027-03-01'));
        $generator->generate($this->admin, $this->day('2027-04-01'));
        $this->em->flush();

        return $job;
    }

    private function taskFor(ClientJob $job, string $key): Task
    {
        return self::getContainer()->get(TaskRepository::class)->findOneBy(['clientJob' => $job, 'periodKey' => $key]);
    }

    private function due(Task $task): string
    {
        return $task->getDueDate()->format('Y-m-d');
    }

    public function testChangingDueOnlyForThisTaskSetsFlagAndSurvivesScheduleChanges(): void
    {
        $job = $this->job();
        $march = $this->taskFor($job, '2027-03');
        $this->login($this->admin);

        $result = $this->api('/ajax/tasks/'.$march->getId().'/due-change', ['dueDate' => '2027-03-17']);
        self::assertTrue($result['success'], json_encode($result));

        $march = $this->fresh($march);
        self::assertSame('2027-03-17', $this->due($march));
        self::assertTrue($march->isDueDateManuallySet());
        $job = $this->fresh($job);
        self::assertTrue($job->getScheduleOverride()->isEmpty(), 'bez kvačice raspored posla se ne menja');

        // izmena šablona i preračun ne diraju ručni rok, a april (nije ručan) prati šablon
        $template = $job->getJobTemplate();
        $before = self::getContainer()->get(\App\Service\ScheduleChangeService::class)->persistedSchedule($template);
        $template->setSchedule(Schedule::monthly(4, 5));
        $this->em->flush();
        self::getContainer()->get(\App\Service\ScheduleChangeService::class)->templateChanged($template, $before, $this->em->getReference(User::class, $this->admin->getId()));
        self::assertSame('2027-03-17', $this->due($march));
        self::assertSame('2027-04-05', $this->due($this->taskFor($job, '2027-04')), '4.4.2027. je nedelja pa je rok 5.4.');
    }

    public function testApplyToFuturePeriodsCustomizesJobAndLeavesThisPastAndFinishedAlone(): void
    {
        $job = $this->job();
        $march = $this->taskFor($job, '2027-03');
        $april = $this->taskFor($job, '2027-04');
        $done = $this->task('Februar', $this->admin, [], $job->getClient(), $job, TaskStatus::ZAVRSENO);
        $done->markGenerated($job, '2027-02', 30)->setDueDate($this->day('2027-02-03'));
        $this->em->flush();
        $this->login($this->admin);

        $preview = $this->api('/ajax/tasks/'.$march->getId().'/due-preview', ['dueDate' => '2027-03-10']);
        self::assertTrue($preview['data']['canFuture']);
        self::assertSame('Od sledećeg perioda: svakog 10. u mesecu', $preview['data']['future']);
        self::assertSame(1, $preview['data']['impact']['moved'], 'april se menja, mart je ručan');

        $result = $this->api('/ajax/tasks/'.$march->getId().'/due-change', ['dueDate' => '2027-03-10', 'future' => true]);
        self::assertTrue($result['success'], json_encode($result));

        $job = $this->fresh($job);
        $override = $job->getScheduleOverride();
        self::assertSame(10, $override->getDayOfMonth());
        self::assertSame(5, $override->getLeadDays(), 'leadDays ostaje iz šablona');
        self::assertSame(\App\Enum\NonWorkingDayShift::NEXT, $override->getNonWorkingDayShift());
        self::assertSame('2027-03-10', $this->due($this->taskFor($job, '2027-03')));
        self::assertSame('2027-04-12', $this->due($this->taskFor($job, '2027-04')), '10.4.2027. je subota pa se pomera na ponedeljak');
        self::assertSame('2027-02-03', $this->due($this->taskFor($job, '2027-02')), 'završen zadatak se ne dira');
    }

    public function testNonWorkingPickedDateOffersExactDayAndKeepsShiftByDefault(): void
    {
        $job = $this->job();
        $march = $this->taskFor($job, '2027-03');
        $this->login($this->admin);

        // 13.3.2027. je subota: nudi se izbor, a podrazumevano ostaje pomeranje
        $preview = $this->api('/ajax/tasks/'.$march->getId().'/due-preview', ['dueDate' => '2027-03-13']);
        self::assertTrue($preview['data']['exactOffer']);
        self::assertSame('Subota', $preview['data']['nonWorkingReason']);
        self::assertStringNotContainsString('tačno tog dana', $preview['data']['future']);
        $exact = $this->api('/ajax/tasks/'.$march->getId().'/due-preview', ['dueDate' => '2027-03-13', 'exact' => true]);
        self::assertStringContainsString('tačno tog dana', $exact['data']['future']);

        // radni dan: nema ponude, a exact se ignoriše
        $working = $this->api('/ajax/tasks/'.$march->getId().'/due-preview', ['dueDate' => '2027-03-12', 'exact' => true]);
        self::assertFalse($working['data']['exactOffer']);
        self::assertStringNotContainsString('tačno tog dana', $working['data']['future']);
    }

    public function testApplyToFutureWithExactDayStoresExactRule(): void
    {
        $job = $this->job();
        $march = $this->taskFor($job, '2027-03');
        $this->login($this->admin);

        $result = $this->api('/ajax/tasks/'.$march->getId().'/due-change', ['dueDate' => '2027-03-13', 'future' => true, 'exact' => true]);
        self::assertTrue($result['success'], json_encode($result));

        $override = $this->fresh($job)->getScheduleOverride();
        self::assertSame(13, $override->getDayOfMonth());
        self::assertSame(\App\Enum\NonWorkingDayShift::EXACT, $override->getNonWorkingDayShift());
        self::assertSame('2027-03-13', $this->due($this->fresh($march)), 'ručni rok je baš izabrani neradni dan');
    }

    public function testApplyToFutureWithoutExactKeepsShiftForNonWorkingDate(): void
    {
        $job = $this->job();
        $march = $this->taskFor($job, '2027-03');
        $this->login($this->admin);

        $result = $this->api('/ajax/tasks/'.$march->getId().'/due-change', ['dueDate' => '2027-03-13', 'future' => true]);
        self::assertTrue($result['success'], json_encode($result));
        self::assertSame(\App\Enum\NonWorkingDayShift::NEXT, $this->fresh($job)->getScheduleOverride()->getNonWorkingDayShift());
    }

    public function testApplyToFutureForNthWorkdayDerivesOrdinal(): void
    {
        $job = $this->job();
        $job->setScheduleOverride(Schedule::fromArray(['type' => 'NTH_WORKDAY', 'nthWorkingDay' => 5, 'leadDays' => 5]));
        $this->em->flush();
        $march = $this->taskFor($job, '2027-03');
        $this->login($this->admin);

        // 12.3.2027. (petak) je 10. radni dan marta (1-5, 8-12)
        $result = $this->api('/ajax/tasks/'.$march->getId().'/due-change', ['dueDate' => '2027-03-12', 'future' => true]);
        self::assertTrue($result['success'], json_encode($result));
        self::assertSame(10, $this->fresh($job)->getScheduleOverride()->getNthWorkingDay());

        // neradni dan nema redni broj: za buduće se ne nudi
        $saturday = $this->api('/ajax/tasks/'.$march->getId().'/due-preview', ['dueDate' => '2027-03-13']);
        self::assertFalse($saturday['data']['canFuture']);
    }

    public function testLastDayOfferAndShortMonthsIncludingFebruary(): void
    {
        $job = $this->job();
        $march = $this->taskFor($job, '2027-03');
        $this->login($this->admin);

        $preview = $this->api('/ajax/tasks/'.$march->getId().'/due-preview', ['dueDate' => '2027-03-31']);
        self::assertTrue($preview['data']['lastDayOffer']);
        self::assertSame('Od sledećeg perioda: svakog 31. u mesecu', $preview['data']['future']);
        self::assertStringContainsString('poslednji dan meseca', (string) $preview['data']['warning']);

        $last = $this->api('/ajax/tasks/'.$march->getId().'/due-preview', ['dueDate' => '2027-03-31', 'lastDay' => true]);
        self::assertSame('Od sledećeg perioda: poslednjeg dana u mesecu', $last['data']['future']);
        self::assertNull($last['data']['warning']);

        $this->api('/ajax/tasks/'.$march->getId().'/due-change', ['dueDate' => '2027-03-31', 'future' => true]);
        $calculator = self::getContainer()->get(ScheduleCalculator::class);
        $schedule = $this->fresh($job)->getScheduleOverride();
        $feb = $calculator->occurrencesBetween($schedule, $this->day('2028-02-01'), $this->day('2028-02-29'), $this->day('2027-03-01'));
        self::assertSame('2028-02-29', $feb[0]['nominal']->format('Y-m-d'), '31. u februaru je poslednji dan meseca (prestupna)');
        $feb = $calculator->occurrencesBetween($schedule, $this->day('2027-02-25'), $this->day('2027-03-02'), $this->day('2026-01-01'));
        self::assertSame('2027-02-28', $feb[0]['nominal']->format('Y-m-d'));
        // 28.2.2027. je nedelja, pa se rok pomera na prvi sledeći radni dan
        self::assertSame('2027-03-01', $feb[0]['due']->format('Y-m-d'));

        $schedule->setDayOfMonth(Schedule::LAST_DAY);
        $feb = $calculator->occurrencesBetween($schedule, $this->day('2027-02-25'), $this->day('2027-03-02'), $this->day('2026-01-01'));
        self::assertSame('2027-02-28', $feb[0]['nominal']->format('Y-m-d'));
    }

    public function testFutureRequiresRightToManageClientJobs(): void
    {
        $worker = $this->user('Kreator');
        $stranger = $this->user('Strani');
        $job = $this->job($worker);
        $march = $this->taskFor($job, '2027-03');
        $march->setCreatedBy($worker);
        $this->em->flush();
        [$jobId, $marchId] = [$job->getId(), $march->getId()];
        $this->login($worker);

        $own = $this->api('/ajax/tasks/'.$marchId.'/due-change', ['dueDate' => '2027-03-12']);
        self::assertTrue($own['success'], 'kreator sme da menja rok zadatka (TASK_EDIT)');

        $future = $this->api('/ajax/tasks/'.$marchId.'/due-change', ['dueDate' => '2027-03-13', 'future' => true]);
        self::assertFalse($future['success']);
        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertTrue($this->em->find(ClientJob::class, $jobId)->getScheduleOverride()->isEmpty());
        self::assertSame('2027-03-12', $this->due($this->em->find(Task::class, $marchId)));

        $this->login($this->em->find(User::class, $stranger->getId()));
        $this->api('/ajax/tasks/'.$marchId.'/due-change', ['dueDate' => '2027-03-20']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testMarkerTooltipAndReset(): void
    {
        $job = $this->job();
        $march = $this->taskFor($job, '2027-03');
        $this->login($this->admin);
        $this->api('/ajax/tasks/'.$march->getId().'/due-change', ['dueDate' => '2027-03-17']);

        $this->browser->request('GET', '/tasks/'.$march->getId());
        self::assertResponseIsSuccessful();
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertStringContainsString('Rok ručno promenjen, podrazumevano je 3.', $html);
        self::assertStringContainsString('data-kt-task-due="reset"', $html);

        $reset = $this->api('/ajax/tasks/'.$march->getId().'/due-change', ['reset' => true]);
        self::assertTrue($reset['success']);
        $march = $this->fresh($march);
        self::assertSame('2027-03-03', $this->due($march));
        self::assertFalse($march->isDueDateManuallySet());
    }

    public function testEditingDueInTaskFormMarksGeneratedTaskAsManual(): void
    {
        $job = $this->job();
        $march = $this->taskFor($job, '2027-03');
        $worker = $job->getClient()->getPrimaryAccountant();
        $march->setDueDate($this->day('2027-03-20'));

        self::getContainer()->get(\App\Service\TaskService::class)->update($march, $march->getAssigneeUsers() ?: [$worker], $this->admin);
        self::assertTrue($march->isDueDateManuallySet());

        $april = $this->taskFor($job, '2027-04');
        self::getContainer()->get(\App\Service\TaskService::class)->update($april, $april->getAssigneeUsers() ?: [$worker], $this->admin);
        self::assertFalse($april->isDueDateManuallySet(), 'bez izmene roka zadatak ostaje nepodešen');
    }
}
