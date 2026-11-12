<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112110352 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Zadaci: title_search (pretraga bez kvačica) + indeksi za listu';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task ADD title_search VARCHAR(255) DEFAULT \'\' NOT NULL');
        $this->addSql('CREATE INDEX IDX_TASK_CLIENT_STATUS ON task (client_id, status)');
        $this->addSql('CREATE INDEX IDX_TASK_ASSIGNEE_USER_TASK ON task_assignee (user_id, task_id)');
    }

    public function postUp(Schema $schema): void
    {
        // popuni title_search za postojeće zadatke istim TextNormalizer-om koji koristi i aplikacija
        $normalizer = new \App\Service\TextNormalizer();
        foreach ($this->connection->fetchAllAssociative('SELECT id, title FROM task') as $row) {
            $this->connection->update('task', ['title_search' => $normalizer->normalize($row['title'])], ['id' => $row['id']]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_TASK_CLIENT_STATUS ON task');
        $this->addSql('ALTER TABLE task DROP title_search');
        $this->addSql('DROP INDEX IDX_TASK_ASSIGNEE_USER_TASK ON task_assignee');
    }
}
