<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027150722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task ADD period_key VARCHAR(20) DEFAULT NULL, ADD generation_key VARCHAR(40) DEFAULT NULL, ADD estimated_minutes INT DEFAULT NULL, ADD generated_automatically TINYINT NOT NULL, ADD client_job_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB255E46051A FOREIGN KEY (client_job_id) REFERENCES client_job (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_527EDB257D18FFFD ON task (generation_key)');
        $this->addSql('CREATE INDEX IDX_527EDB255E46051A ON task (client_job_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB255E46051A');
        $this->addSql('DROP INDEX UNIQ_527EDB257D18FFFD ON task');
        $this->addSql('DROP INDEX IDX_527EDB255E46051A ON task');
        $this->addSql('ALTER TABLE task DROP period_key, DROP generation_key, DROP estimated_minutes, DROP generated_automatically, DROP client_job_id');
    }
}
