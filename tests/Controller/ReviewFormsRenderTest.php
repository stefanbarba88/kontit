<?php

namespace App\Tests\Controller;

use App\Entity\JobCategory;
use App\Entity\JobTemplate;
use App\Enum\Role;
use App\Tests\Support\DatabaseTestCase;

/** Render formi i stranica koje dobijaju polja provere: zadatak, katalog poslova, pozicije. */
class ReviewFormsRenderTest extends DatabaseTestCase
{
    public function testFormsRender(): void
    {
        $admin = $this->user('Admin', Role::ADMIN);
        $worker = $this->user('Radnik');
        $this->client('Alfa', $worker);
        $cat = $this->category('Kat');
        $tpl = (new JobTemplate())->setName('Posebni posao')->setCategory($cat)->setRequiresApproval(true)->setApprovers([$admin]);
        $this->em->persist($tpl);
        $this->em->flush();

        $this->browser->loginUser($admin);
        foreach (['/tasks/new', '/admin/positions', '/admin/catalog/jobs', '/admin/catalog/jobs/new', '/_design'] as $url) {
            $this->browser->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }
        $this->browser->request('GET', '/tasks/new');
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertStringContainsString('reviewMode', $html);
        self::assertStringContainsString('data-kt-me="'.$admin->getId().'"', $html);
        $this->browser->request('GET', '/admin/catalog/jobs');
        self::assertStringContainsString('Traži potvrdu', (string) $this->browser->getResponse()->getContent());
    }
}
