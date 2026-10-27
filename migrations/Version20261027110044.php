<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027110044 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE team (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, color VARCHAR(7) NOT NULL, deleted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, leader_id INT NOT NULL, INDEX IDX_C4E0A61F73154ED4 (leader_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE team_leader_log (id INT AUTO_INCREMENT NOT NULL, started_at DATETIME NOT NULL, ended_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, team_id INT NOT NULL, user_id INT NOT NULL, changed_by_id INT DEFAULT NULL, INDEX IDX_5BFF7110296CD8AE (team_id), INDEX IDX_5BFF7110A76ED395 (user_id), INDEX IDX_5BFF7110828AD0A0 (changed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE team_member (id INT AUTO_INCREMENT NOT NULL, joined_at DATETIME NOT NULL, left_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, team_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_TEAM_MEMBER_ACTIVE (user_id, left_at), INDEX IDX_6FFBDA1296CD8AE (team_id), INDEX IDX_6FFBDA1A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F73154ED4 FOREIGN KEY (leader_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE team_leader_log ADD CONSTRAINT FK_5BFF7110296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE team_leader_log ADD CONSTRAINT FK_5BFF7110A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE team_leader_log ADD CONSTRAINT FK_5BFF7110828AD0A0 FOREIGN KEY (changed_by_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE team_member ADD CONSTRAINT FK_6FFBDA1296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE team_member ADD CONSTRAINT FK_6FFBDA1A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE app_user ADD phone VARCHAR(50) DEFAULT NULL, ADD address VARCHAR(255) DEFAULT NULL, ADD city VARCHAR(100) DEFAULT NULL, ADD postal_code VARCHAR(20) DEFAULT NULL, ADD position VARCHAR(100) DEFAULT NULL, ADD employed_at DATE DEFAULT NULL, ADD internal_note LONGTEXT DEFAULT NULL, ADD email_notifications JSON NOT NULL, ADD avatar_name VARCHAR(255) DEFAULT NULL');
        $this->addSql("UPDATE app_user SET email_notifications = '[]'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61F73154ED4');
        $this->addSql('ALTER TABLE team_leader_log DROP FOREIGN KEY FK_5BFF7110296CD8AE');
        $this->addSql('ALTER TABLE team_leader_log DROP FOREIGN KEY FK_5BFF7110A76ED395');
        $this->addSql('ALTER TABLE team_leader_log DROP FOREIGN KEY FK_5BFF7110828AD0A0');
        $this->addSql('ALTER TABLE team_member DROP FOREIGN KEY FK_6FFBDA1296CD8AE');
        $this->addSql('ALTER TABLE team_member DROP FOREIGN KEY FK_6FFBDA1A76ED395');
        $this->addSql('DROP TABLE team');
        $this->addSql('DROP TABLE team_leader_log');
        $this->addSql('DROP TABLE team_member');
        $this->addSql('ALTER TABLE app_user DROP phone, DROP address, DROP city, DROP postal_code, DROP position, DROP employed_at, DROP internal_note, DROP email_notifications, DROP avatar_name');
    }
}
