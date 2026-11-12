<?php

namespace App\Tests\Controller;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\Role;
use App\Export\TaskExport;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/** Stranice liste zadataka kroz HTTP (render šablona, prava, filteri u URL-u, izvoz). */
class TaskPagesTest extends DatabaseTestCase
{
    private function browser(User $as): KernelBrowser
    {
        $this->browser->loginUser($as);

        return $this->browser;
    }

    public function testPagesRenderAndRespectPermissions(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $sef = $this->user('Sef', Role::SEF);
        $member = $this->user('Clan');
        $outsider = $this->user('Tudj');
        $team = (new Team())->setName('Tim A')->setLeader($sef);
        $this->em->persist($team);
        $this->em->persist(new TeamMember($team, $member));
        $client = $this->client('Alfa', $member);
        $this->task('Račun Alfa', $admin, [$member], $client);
        $this->task('Tuđi zadatak', $admin, [$outsider]);
        $this->em->flush();

        // admin: /tasks sa filterima u URL-u, tab klijenta, tab korisnika
        $browser = $this->browser($admin);
        $browser->request('GET', '/tasks?q=racun');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Račun Alfa', (string) $browser->getResponse()->getContent());
        self::assertStringNotContainsString('Tuđi zadatak', (string) $browser->getResponse()->getContent());
        $browser->request('GET', '/clients/'.$client->getId().'/zadaci');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Račun Alfa', (string) $browser->getResponse()->getContent());
        $browser->request('GET', '/users/'.$member->getId().'/zadaci');
        self::assertResponseIsSuccessful();
        $browser->request('GET', '/admin/users/'.$member->getId());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('/users/'.$member->getId().'/zadaci', (string) $browser->getResponse()->getContent());

        // šef: svog člana vidi, tuđeg ne (403)
        $browser = $this->browser($sef);
        $browser->request('GET', '/users/'.$member->getId().'/zadaci');
        self::assertResponseIsSuccessful();
        $browser->request('GET', '/users/'.$outsider->getId().'/zadaci');
        self::assertResponseStatusCodeSame(403);

        // radnik: svoja stranica da, tuđa ne; na tabu klijenta ne vidi tuđe zadatke
        $browser = $this->browser($outsider);
        $browser->request('GET', '/users/'.$outsider->getId().'/zadaci');
        self::assertResponseIsSuccessful();
        $browser->request('GET', '/users/'.$member->getId().'/zadaci');
        self::assertResponseStatusCodeSame(403);
        $browser = $this->browser($member);
        $browser->request('GET', '/clients/'.$client->getId().'/zadaci?q=tudji');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Tuđi zadatak', (string) $browser->getResponse()->getContent());
    }

    public function testExportMatchesFilteredTab(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $member = $this->user('Clan');
        $client = $this->client('Alfa', $member);
        $this->task('Račun Alfa', $admin, [$member], $client);
        $this->task('Plata Alfa', $admin, [$member], $client);
        $this->task('Druga stvar', $admin, [$member]);
        $this->em->flush();

        $export = self::getContainer()->get(TaskExport::class);
        $titles = static fn (array $q) => array_map(static fn ($row) => $row[0], iterator_to_array($export->rows($q, $admin), false));
        self::assertSame(['Račun Alfa'], $titles(['forClient' => $client->getId(), 'q' => 'racun']));
        self::assertCount(2, iterator_to_array($export->rows(['forClient' => $client->getId()], $admin), false));
        self::assertCount(3, iterator_to_array($export->rows(['forUser' => $member->getId()], $admin), false));
        self::assertSame(2, $export->count(['forClient' => $client->getId()], $admin));
    }
}
