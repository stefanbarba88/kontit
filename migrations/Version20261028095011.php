<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028095011 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Zadatak: jedinstven (client_job_id, period_key) umesto generation_key, taken_over_from (PREUZETO)';
    }

    public function up(Schema $schema): void
    {
        // jedinstven (posao klijenta, period) umesto kolone generation_key (SPEC 8i.1); zamena kad je glavni radnik odsutan
        $this->addSql('DROP INDEX UNIQ_527EDB257D18FFFD ON task');
        $this->addSql('ALTER TABLE task ADD taken_over_from_id INT DEFAULT NULL, DROP generation_key');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25E669DD40 FOREIGN KEY (taken_over_from_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TASK_JOB_PERIOD ON task (client_job_id, period_key)');
        $this->addSql('CREATE INDEX IDX_527EDB25E669DD40 ON task (taken_over_from_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25E669DD40');
        $this->addSql('DROP INDEX UNIQ_TASK_JOB_PERIOD ON task');
        $this->addSql('DROP INDEX IDX_527EDB25E669DD40 ON task');
        $this->addSql('ALTER TABLE task ADD generation_key VARCHAR(40) DEFAULT NULL, DROP taken_over_from_id');
        $this->addSql("UPDATE task SET generation_key = CONCAT(client_job_id, ':', period_key) WHERE client_job_id IS NOT NULL AND period_key IS NOT NULL");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_527EDB257D18FFFD ON task (generation_key)');
    }
}
