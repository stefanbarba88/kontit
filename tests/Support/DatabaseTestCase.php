<?php

namespace App\Tests\Support;

use App\Entity\Client;
use App\Entity\ClientJob;
use App\Entity\JobCategory;
use App\Entity\JobTemplate;
use App\Entity\Task;
use App\Entity\TaskAssignee;
use App\Entity\User;
use App\Enum\Role;
use App\Enum\TaskStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test sa bazom (kontit_test, šema: php bin/console doctrine:schema:create --env=test).
 * Svaki test radi u transakciji koja se poništava, pa baza ostaje prazna.
 */
abstract class DatabaseTestCase extends WebTestCase
{
    protected EntityManagerInterface $em;
    protected KernelBrowser $browser;
    private int $seq = 0;

    protected function setUp(): void
    {
        // jedno jezgro i jedna konekcija za ceo test (i za HTTP zahteve), pa transakcija vidi sve podatke
        $this->browser = static::createClient();
        $this->browser->disableReboot();
        $this->em = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        $this->em->clear();
        parent::tearDown();
        self::ensureKernelShutdown();
    }

    protected function user(string $first, Role $role = Role::RADNIK): User
    {
        $u = (new User())->setFirstName($first)->setLastName('Test')->setEmail(strtolower($first).(++$this->seq).'@test.local')
            ->setPassword('x')->setHighestRole($role);
        $this->em->persist($u);

        return $u;
    }

    protected function client(string $short, User $accountant): Client
    {
        $c = (new Client())->setName($short.' d.o.o.')->setShortName($short)->setPrimaryAccountant($accountant);
        $this->em->persist($c);

        return $c;
    }

    protected function category(string $name): JobCategory
    {
        $c = new JobCategory($name);
        $this->em->persist($c);

        return $c;
    }

    protected function template(string $name, ?JobCategory $category = null): JobTemplate
    {
        $t = (new JobTemplate())->setName($name)->setCategory($category);
        $this->em->persist($t);

        return $t;
    }

    protected function clientJob(Client $client, JobTemplate $template): ClientJob
    {
        $j = (new ClientJob())->setClient($client)->setJobTemplate($template)->setStartDate(new \DateTimeImmutable('today'));
        $this->em->persist($j);

        return $j;
    }

    /** @param list<User> $assignees */
    protected function task(string $title, User $creator, array $assignees = [], ?Client $client = null, ?ClientJob $job = null, TaskStatus $status = TaskStatus::NOVO, bool $auto = false): Task
    {
        $t = (new Task())->setTitle($title)->setCreatedBy($creator)->setClient($client)->setStatus($status);
        if (null !== $job) {
            $t->setClientJob($job);
        }
        $this->em->persist($t);
        foreach ($assignees as $a) {
            $ta = new TaskAssignee($t, $a);
            $t->getAssignees()->add($ta);
            $this->em->persist($ta);
        }
        if ($auto) {
            (new \ReflectionProperty(Task::class, 'generatedAutomatically'))->setValue($t, true);
        }
        $this->em->flush();

        return $t;
    }
}
