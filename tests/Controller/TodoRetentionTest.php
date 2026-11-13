<?php

namespace App\Tests\Controller;

use App\Entity\TodoItem;
use App\Entity\User;
use App\Service\TodoClock;
use App\Service\TodoService;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/** Završene stavke Podsetnika se čuvaju 3 dana: lista ih ne prikazuje i komanda app:todo:cleanup ih briše. */
class TodoRetentionTest extends DatabaseTestCase
{
    private function todo(): TodoService
    {
        return self::getContainer()->get(TodoService::class);
    }

    /** Stavka (otvorena ili završena pre $daysAgo dana, UTC) */
    private function item(User $owner, string $title, ?float $daysAgo = null): TodoItem
    {
        $item = $this->todo()->add($owner, $title);
        if (null !== $daysAgo) {
            $clock = self::getContainer()->get(TodoClock::class);
            $item->markDone($clock->now()->modify(sprintf('-%d minutes', (int) ($daysAgo * 1440))));
            $this->em->flush();
        }

        return $item;
    }

    private function cleanup(): string
    {
        $app = new Application(self::$kernel);
        $tester = new CommandTester($app->find('app:todo:cleanup'));
        $tester->execute([], ['verbosity' => \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE]);
        self::assertSame(0, $tester->getStatusCode());

        return $tester->getDisplay();
    }

    /** @return list<string> */
    private function titles(): array
    {
        $this->em->clear();

        return array_map(static fn (TodoItem $i) => $i->getTitle(), $this->em->getRepository(TodoItem::class)->findBy([], ['id' => 'ASC']));
    }

    public function testCleanupDeletesOnlyDoneItemsOlderThanThreeDays(): void
    {
        $me = $this->user('Ja');
        $other = $this->user('Drugi');
        $this->em->flush();
        $this->item($me, 'Stara moja', 3.5);
        $this->item($me, 'Nova moja', 2.5);
        $this->item($me, 'Otvorena');
        $this->item($other, 'Stara tudja', 10);
        $this->item($other, 'Nova tudja', 0.1);

        self::assertStringContainsString('Obrisano završenih stavki: 2', $this->cleanup());
        self::assertSame(['Nova moja', 'Otvorena', 'Nova tudja'], $this->titles());
        // drugi put nema šta da se briše
        self::assertStringContainsString('Obrisano završenih stavki: 0', $this->cleanup());
    }

    public function testCleanupKeepsTaskCreatedFromDeletedItem(): void
    {
        $me = $this->user('Ja');
        $task = $this->task('Nastao iz stavke', $me, [$me]);
        $this->em->flush();
        $item = $this->item($me, 'Pretvorena', 5);
        $item->setCreatedTask($task);
        $this->em->flush();
        $taskId = $task->getId();

        $this->cleanup();
        $this->em->clear();
        self::assertSame([], $this->titles());
        self::assertNotNull($this->em->find(\App\Entity\Task::class, $taskId), 'zadatak ostaje');
    }

    public function testListAndSearchHideOldDoneItemsBeforeCron(): void
    {
        $me = $this->user('Ja');
        $this->em->flush();
        $this->item($me, 'Zastarela stavka', 4);
        $this->item($me, 'Skorasnja stavka', 1);
        $this->item($me, 'Otvorena stavka');

        $this->browser->loginUser($me);
        $crawler = $this->browser->request('GET', '/podsetnik');
        $csrf = (string) $crawler->filter('meta[name="csrf-token"]')->attr('content');
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertStringNotContainsString('Zastarela stavka', $html);
        self::assertStringContainsString('Skorasnja stavka', $html);
        self::assertStringContainsString('Završene stavke se brišu posle 3 dana.', $html);

        $this->browser->request('GET', '/podsetnik?q=stavka');
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertStringNotContainsString('Zastarela stavka', $html, 'ni pretraga ne vraća stare završene');
        self::assertStringContainsString('Skorasnja stavka', $html);
        self::assertStringContainsString('Otvorena stavka', $html);

        $this->browser->request('GET', '/ajax/todo', [], [], ['HTTP_X_CSRF_TOKEN' => $csrf]);
        self::assertStringNotContainsString('Zastarela stavka', (string) $this->browser->getResponse()->getContent());
    }

    public function testOldDoneItemOfOtherUserIsStillNotFoundForMe(): void
    {
        $me = $this->user('Ja');
        $other = $this->user('Drugi');
        $this->em->flush();
        $foreign = $this->item($other, 'Tudja', 1);

        $this->browser->loginUser($me);
        $crawler = $this->browser->request('GET', '/podsetnik');
        $csrf = (string) $crawler->filter('meta[name="csrf-token"]')->attr('content');
        $this->browser->request('DELETE', '/ajax/todo/'.$foreign->getId(), [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf], '{}');
        self::assertResponseStatusCodeSame(404);
        $this->browser->request('POST', '/ajax/todo/'.$foreign->getId().'/reopen', [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf], '{}');
        self::assertResponseStatusCodeSame(404);
        self::assertCount(1, $this->em->getRepository(TodoItem::class)->findBy(['owner' => $other]));
    }
}
