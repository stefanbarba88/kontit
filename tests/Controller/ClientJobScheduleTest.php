<?php

namespace App\Tests\Controller;

use App\Entity\ClientJob;
use App\Entity\ClientPackage;
use App\Entity\Schedule;
use App\Entity\ServicePackage;
use App\Entity\User;
use App\Enum\Role;
use App\Repository\TaskRepository;
use App\Service\ClientPackageService;
use App\Service\TaskGenerationService;
use App\Tests\Support\DatabaseTestCase;

/** Prilagođen raspored posla za klijenta: ekran, AJAX pregled/primena/vraćanje, dodela paketa i prava (SPEC 8g.4). */
class ClientJobScheduleTest extends DatabaseTestCase
{
    private string $csrf = '';

    private function login(User $user): void
    {
        $this->browser->loginUser($user);
        $crawler = $this->browser->request('GET', '/moja-lista');
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

    /** posao sa mesečnim rasporedom (3.), zadatak za mart 2027 generisan */
    private function jobWithTask(User $worker): ClientJob
    {
        $template = $this->template('PDV', $this->category('K'))->setSchedule(Schedule::monthly(3, 5));
        $job = $this->clientJob($this->client('Alfa', $worker), $template)->setStartDate($this->day('2027-03-01'));
        $this->em->flush();
        self::getContainer()->get(TaskGenerationService::class)->generate($this->user('Sys', Role::ADMIN), $this->day('2027-03-01'));
        $this->em->flush();

        return $job;
    }

    /** HTTP zahtev radi nad istim EM-om, ali može da ga isprazni; pročitaj sveže stanje iz baze */
    private function fresh(object $entity): object
    {
        $id = $entity->getId();
        $this->em->clear();

        return $this->em->find($entity::class, $id);
    }

    private function monthly(int $day): array
    {
        return Schedule::monthly($day, 5)->toArray();
    }

    public function testServicesTabShowsDialogAndCustomizeActions(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $job = $this->jobWithTask($this->user('Radnik'));
        $this->login($admin);

        $this->browser->request('GET', '/clients/'.$job->getClient()->getId().'?tab=usluge');
        self::assertResponseIsSuccessful();
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertStringContainsString('id="kt-schedule-dialog"', $html);
        self::assertStringContainsString('data-kt-sched-job="customize"', $html);
        self::assertStringContainsString('Prilagodi za ovog klijenta', $html);
        self::assertStringNotContainsString('data-kt-sched-job="revert"', $html);

        $this->browser->request('GET', '/client-jobs/'.$job->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Podrazumevano (iz posla)', (string) $this->browser->getResponse()->getContent());
    }

    public function testImpactThenApplyThenRevert(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $job = $this->jobWithTask($this->user('Radnik'));
        $task = self::getContainer()->get(TaskRepository::class)->findOneBy(['clientJob' => $job]);
        $this->login($admin);

        $impact = $this->api('/ajax/client-jobs/'.$job->getId().'/schedule/impact', ['schedule' => $this->monthly(10)]);
        self::assertTrue($impact['success']);
        self::assertSame(1, $impact['data']['moved']);
        self::assertSame('2027-03-03', $task->getDueDate()->format('Y-m-d'), 'pregled ništa ne menja');

        $applied = $this->api('/ajax/client-jobs/'.$job->getId().'/schedule', ['schedule' => $this->monthly(10)]);
        self::assertTrue($applied['success'], json_encode($applied));
        self::assertSame('2027-03-10', $this->fresh($task)->getDueDate()->format('Y-m-d'));
        self::assertTrue($this->fresh($job)->getScheduleOverride()->isAutomatic());

        $this->browser->request('GET', '/clients/'.$job->getClient()->getId().'?tab=usluge');
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertStringContainsString('Prilagođeno', $html);
        self::assertStringContainsString('data-kt-sched-job="revert"', $html);

        $reverted = $this->api('/ajax/client-jobs/'.$job->getId().'/schedule', ['schedule' => null]);
        self::assertTrue($reverted['success']);
        self::assertSame('2027-03-03', $this->fresh($task)->getDueDate()->format('Y-m-d'));
        self::assertTrue($this->fresh($job)->getScheduleOverride()->isEmpty());
    }

    public function testInvalidScheduleIsRejected(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $job = $this->jobWithTask($this->user('Radnik'));
        $this->login($admin);

        $response = $this->api('/ajax/client-jobs/'.$job->getId().'/schedule', ['schedule' => ['type' => 'MONTHLY']]);
        self::assertFalse($response['success']);
        self::assertResponseStatusCodeSame(422);
        self::assertTrue($job->getScheduleOverride()->isEmpty());
    }

    public function testWorkerWithoutRightsGetsForbidden(): void
    {
        $worker = $this->user('Radnik');
        $job = $this->jobWithTask($worker);
        $task = self::getContainer()->get(TaskRepository::class)->findOneBy(['clientJob' => $job]);
        $this->login($worker);

        foreach (['/schedule', '/schedule/impact'] as $suffix) {
            $this->api('/ajax/client-jobs/'.$job->getId().$suffix, ['schedule' => $this->monthly(10)]);
            self::assertResponseStatusCodeSame(403);
        }
        self::assertTrue($job->getScheduleOverride()->isEmpty());
        self::assertSame('2027-03-03', $task->getDueDate()->format('Y-m-d'));

        $this->browser->request('GET', '/clients/'.$job->getClient()->getId().'?tab=usluge');
        self::assertStringNotContainsString('data-kt-sched-job', (string) $this->browser->getResponse()->getContent());
    }

    public function testPackageAttachStoresOnlyRealCustomizations(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $category = $this->category('K');
        $custom = $this->template('PDV', $category)->setSchedule(Schedule::monthly(3, 5));
        $same = $this->template('Bilans', $category)->setSchedule(Schedule::monthly(15, 5));
        $this->em->flush();
        $package = (new ServicePackage())->setName('Paket')->setPrice('1000')->setJobTemplates([$custom, $same]);
        $this->em->persist($package);
        $client = $this->client('Beta', $this->user('Radnik'));
        $this->em->flush();

        $clientPackage = (new ClientPackage())->setClient($client)->setPackage($package)->setAgreedPrice('1000')->setStartDate($this->day('2027-03-01'));
        self::getContainer()->get(ClientPackageService::class)->attach($clientPackage, $admin, [], [
            $custom->getId() => Schedule::monthly(6, 5),
            $same->getId() => Schedule::monthly(15, 5), // isto kao podrazumevani: ne čuva se
        ]);

        $jobs = $this->em->getRepository(ClientJob::class)->findBy(['client' => $client]);
        $byName = [];
        foreach ($jobs as $j) {
            $byName[$j->getJobTemplate()->getName()] = $j;
        }
        self::assertSame(6, $byName['PDV']->getScheduleOverride()->getDayOfMonth());
        self::assertTrue($byName['Bilans']->getScheduleOverride()->isEmpty());
        // paket za druge klijente se ne menja
        self::assertSame(3, $custom->getSchedule()->getDayOfMonth());
    }
}
