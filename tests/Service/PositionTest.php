<?php

namespace App\Tests\Service;

use App\Entity\Position;
use App\Entity\UserPositionLog;
use App\Enum\Role;
use App\Repository\PositionRepository;
use App\Service\CapacityResolver;
use App\Service\LegacyPositionMigrator;
use App\Service\SettingsService;
use App\Service\TextNormalizer;
use App\Service\UserService;
use App\Tests\Support\DatabaseTestCase;

/**
 * Pozicije zaposlenih: pozicija je nivo i NE menja prava pristupa.
 */
class PositionTest extends DatabaseTestCase
{
    private function position(string $name, int $level = 1): Position
    {
        $p = new Position($name, '#0089FF', $level);
        $this->em->persist($p);

        return $p;
    }

    private function runLegacy(array $legacy, bool $dryRun = false): array
    {
        return self::getContainer()->get(LegacyPositionMigrator::class)->migrate($legacy, $dryRun);
    }

    public function testLegacyCommandMapsCaseAndDiacriticsAndCreatesNew(): void
    {
        $this->em->getConnection()->executeStatement('DELETE FROM position');
        $sef = $this->position('Šef', 4);
        $a = $this->user('Ana');
        $b = $this->user('Bora');
        $c = $this->user('Cica');
        $d = $this->user('Dora'); // već ima poziciju, ne dira se
        $d->setPosition($sef);
        $this->em->flush();
        $legacy = [$a->getId() => 'sef', $b->getId() => '  ŠEF ', $c->getId() => 'Nova Pozicija', $d->getId() => 'Nesto drugo'];

        // dry-run ništa ne menja
        $this->runLegacy($legacy, true);
        $this->em->clear();
        self::assertNull($this->em->find($a::class, $a->getId())->getPosition());
        self::assertSame(1, $this->em->getRepository(Position::class)->count([]));

        $report = $this->runLegacy($legacy);
        self::assertCount(1, $report['created']);
        self::assertSame(1, $report['skipped']);
        $this->em->clear();
        $users = $this->em->getRepository($a::class);
        self::assertSame('Šef', $users->find($a->getId())->getPosition()->getName());
        self::assertSame($users->find($a->getId())->getPosition(), $users->find($b->getId())->getPosition());
        self::assertSame('Nova Pozicija', $users->find($c->getId())->getPosition()->getName());
        self::assertSame('Šef', $users->find($d->getId())->getPosition()->getName());
        self::assertSame(2, $this->em->getRepository(Position::class)->count([]));
        self::assertSame(0, $this->em->getRepository(UserPositionLog::class)->count([]), 'Početno mapiranje ne pravi istoriju.');

        // idempotentno
        $this->runLegacy($legacy);
        self::assertSame(2, $this->em->getRepository(Position::class)->count([]));
    }

    public function testDeleteOnlyIfNeverUsed(): void
    {
        /** @var PositionRepository $repo */
        $repo = self::getContainer()->get(PositionRepository::class);
        $free = $this->position('Slobodna');
        $withUser = $this->position('SaKorisnikom');
        $inLog = $this->position('UIstoriji');
        $u = $this->user('Ena');
        $u->setPosition($withUser);
        $this->em->persist(new UserPositionLog($u, $inLog, null, null));
        $this->em->flush();

        self::assertFalse($repo->isUsed($free));
        self::assertTrue($repo->isUsed($withUser));
        self::assertTrue($repo->isUsed($inLog), 'Pozicija iz istorije (from/to) se ne briše.');
    }

    public function testCapacityPrecedence(): void
    {
        /** @var CapacityResolver $resolver */
        $resolver = self::getContainer()->get(CapacityResolver::class);
        self::getContainer()->get(SettingsService::class)->set(SettingsService::DEFAULT_CAPACITY_HOURS, '160');
        $position = $this->position('Junior')->setMonthlyCapacityHours(120);
        $u = $this->user('Fedja');

        self::assertSame([160.0, CapacityResolver::SOURCE_DEFAULT], $resolver->resolveWithSource($u));
        $u->setPosition($position);
        self::assertSame([120.0, CapacityResolver::SOURCE_POSITION], $resolver->resolveWithSource($u));
        $u->setCapacityHours(100);
        self::assertSame([100.0, CapacityResolver::SOURCE_USER], $resolver->resolveWithSource($u));
        self::assertSame(120.0, $resolver->inherited($u));
    }

    public function testPositionChangeLogsAndKeepsAccessRole(): void
    {
        /** @var UserService $service */
        $service = self::getContainer()->get(UserService::class);
        $admin = $this->user('Gaga', Role::ADMIN);
        $junior = $this->position('Junior', 1);
        $sef = $this->position('Šef', 2)->setSuggestedAccessRole(Role::SEF);
        $u = $this->user('Hana');
        $u->setPosition($junior);
        $this->em->flush();

        $before = $u->getPosition();
        $u->setPosition($sef);
        $service->logPositionChange($u, $before, $admin, 'Napredovala');
        $service->logPositionChange($u, $sef, $admin); // bez promene: nema zapisa

        $logs = $this->em->getRepository(UserPositionLog::class)->findBy(['user' => $u]);
        self::assertCount(1, $logs);
        self::assertSame('Junior', $logs[0]->getFromPositionName());
        self::assertSame('Šef', $logs[0]->getToPositionName());
        self::assertSame('Napredovala', $logs[0]->getNote());
        self::assertSame(Role::RADNIK, $u->getHighestRole(), 'Pozicija i predlog prava ne menjaju ROLE_*.');

        // snapshot ostaje čitljiv posle preimenovanja
        $sef->setName('Rukovodilac');
        $this->em->flush();
        self::assertSame('Šef', $logs[0]->getToPositionName());
    }

    public function testSeedIsIdempotent(): void
    {
        $this->em->getConnection()->executeStatement('DELETE FROM position');
        $sql = 'INSERT INTO position (name, color, level, is_active, suggested_access_role, created_at, updated_at) SELECT ?, ?, ?, 1, ?, NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM position WHERE name = ?)';
        for ($i = 0; $i < 2; ++$i) {
            $this->em->getConnection()->executeStatement($sql, ['Junior', '#0089FF', 2, 'ROLE_RADNIK', 'Junior']);
        }
        self::assertSame(1, $this->em->getRepository(Position::class)->count([]));
    }

    public function testNormalizerMatchesPositionNames(): void
    {
        $n = new TextNormalizer();
        self::assertSame($n->normalize('Šef'), $n->normalize(' sef '));
        self::assertSame($n->normalize('Samostalni knjigovođa'), $n->normalize('samostalni knjigovodja'));
    }

    public function testWorkerGets403OnPositions(): void
    {
        $worker = $this->user('Ivo');
        $this->em->flush();
        $this->browser->loginUser($worker);
        $this->browser->request('GET', '/admin/positions');
        self::assertResponseStatusCodeSame(403);

        $admin = $this->user('Jana', Role::ADMIN);
        $this->em->flush();
        $this->browser->loginUser($admin);
        $this->browser->request('GET', '/admin/positions');
        self::assertResponseIsSuccessful();
    }

    public function testUserPagesRenderWithPosition(): void
    {
        $admin = $this->user('Kaca', Role::ADMIN);
        $pos = $this->position('Junior', 1);
        $u = $this->user('Lara');
        $u->setPosition($pos);
        $this->em->persist(new UserPositionLog($u, null, $pos, $admin, 'Prva'));
        $this->em->flush();
        $this->browser->loginUser($admin);
        foreach (['/admin/users', '/admin/users?position='.$pos->getId(), '/admin/users/'.$u->getId(), '/admin/users/'.$u->getId().'/edit', '/admin/finance/settings'] as $url) {
            $this->browser->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }
        $this->browser->request('GET', '/admin/users/'.$u->getId());
        self::assertSelectorTextContains('body', 'Istorija pozicija');
    }}
