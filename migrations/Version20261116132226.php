<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261116132226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE impersonation_log (id INT AUTO_INCREMENT NOT NULL, started_at DATETIME NOT NULL, last_activity_at DATETIME NOT NULL, ended_at DATETIME DEFAULT NULL, edit_started_at DATETIME DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, mode VARCHAR(10) NOT NULL, end_reason VARCHAR(10) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, actor_id INT NOT NULL, target_id INT NOT NULL, INDEX IDX_IMPERSONATION_TARGET (target_id, started_at), INDEX IDX_IMPERSONATION_OPEN (ended_at, last_activity_at), INDEX IDX_2177C39510DAF24A (actor_id), INDEX IDX_2177C395158E0B66 (target_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE impersonation_log ADD CONSTRAINT FK_2177C39510DAF24A FOREIGN KEY (actor_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE impersonation_log ADD CONSTRAINT FK_2177C395158E0B66 FOREIGN KEY (target_id) REFERENCES app_user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE impersonation_log DROP FOREIGN KEY FK_2177C39510DAF24A');
        $this->addSql('ALTER TABLE impersonation_log DROP FOREIGN KEY FK_2177C395158E0B66');
        $this->addSql('DROP TABLE impersonation_log');
    }
}
