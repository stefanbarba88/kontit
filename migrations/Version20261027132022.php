<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027132022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_absence (id INT AUTO_INCREMENT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, reason VARCHAR(20) NOT NULL, note LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, decided_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, created_by_id INT NOT NULL, decided_by_id INT DEFAULT NULL, INDEX IDX_ABSENCE_USER_DATES (user_id, start_date, end_date), INDEX IDX_2E57FEC3A76ED395 (user_id), INDEX IDX_2E57FEC3B03A8386 (created_by_id), INDEX IDX_2E57FEC3E26B496B (decided_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_absence ADD CONSTRAINT FK_2E57FEC3A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE user_absence ADD CONSTRAINT FK_2E57FEC3B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE user_absence ADD CONSTRAINT FK_2E57FEC3E26B496B FOREIGN KEY (decided_by_id) REFERENCES app_user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_absence DROP FOREIGN KEY FK_2E57FEC3A76ED395');
        $this->addSql('ALTER TABLE user_absence DROP FOREIGN KEY FK_2E57FEC3B03A8386');
        $this->addSql('ALTER TABLE user_absence DROP FOREIGN KEY FK_2E57FEC3E26B496B');
        $this->addSql('DROP TABLE user_absence');
    }
}
