<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112124502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pozicije zaposlenih: position, user_position_log, app_user.position_id + 4 početne pozicije (bez prenosa starih podataka: app:positions:migrate-legacy)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE position (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, color VARCHAR(7) NOT NULL, level INT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, monthly_capacity_hours INT DEFAULT NULL, suggested_access_role VARCHAR(20) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_462CE4F55E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_position_log (id INT AUTO_INCREMENT NOT NULL, from_position_name VARCHAR(100) DEFAULT NULL, to_position_name VARCHAR(100) DEFAULT NULL, changed_at DATETIME NOT NULL, note VARCHAR(255) DEFAULT NULL, user_id INT NOT NULL, from_position_id INT DEFAULT NULL, to_position_id INT DEFAULT NULL, changed_by_id INT DEFAULT NULL, INDEX IDX_POSITION_LOG_USER (user_id, changed_at), INDEX IDX_FB451CDFA76ED395 (user_id), INDEX IDX_FB451CDF43CB1F23 (from_position_id), INDEX IDX_FB451CDFF3171025 (to_position_id), INDEX IDX_FB451CDF828AD0A0 (changed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_position_log ADD CONSTRAINT FK_FB451CDFA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_position_log ADD CONSTRAINT FK_FB451CDF43CB1F23 FOREIGN KEY (from_position_id) REFERENCES position (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_position_log ADD CONSTRAINT FK_FB451CDFF3171025 FOREIGN KEY (to_position_id) REFERENCES position (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_position_log ADD CONSTRAINT FK_FB451CDF828AD0A0 FOREIGN KEY (changed_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        // početne pozicije (idempotentno: samo ako ime ne postoji); predlog prava je samo predlog u formi korisnika
        foreach ([['Entry level', '#14B8A6', 1, 'ROLE_RADNIK'], ['Junior', '#0089FF', 2, 'ROLE_RADNIK'], ['Samostalni knjigovođa', '#8B5CF6', 3, 'ROLE_RADNIK'], ['Šef', '#F59E0B', 4, 'ROLE_SEF']] as [$name, $color, $level, $role]) {
            $this->addSql('INSERT INTO position (name, color, level, is_active, suggested_access_role, created_at, updated_at) SELECT ?, ?, ?, 1, ?, NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM position WHERE name = ?)', [$name, $color, $level, $role, $name]);
        }
        $this->addSql('ALTER TABLE app_user ADD position_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD CONSTRAINT FK_88BDF3E9DD842E46 FOREIGN KEY (position_id) REFERENCES position (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_88BDF3E9DD842E46 ON app_user (position_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_position_log DROP FOREIGN KEY FK_FB451CDFA76ED395');
        $this->addSql('ALTER TABLE user_position_log DROP FOREIGN KEY FK_FB451CDF43CB1F23');
        $this->addSql('ALTER TABLE user_position_log DROP FOREIGN KEY FK_FB451CDFF3171025');
        $this->addSql('ALTER TABLE user_position_log DROP FOREIGN KEY FK_FB451CDF828AD0A0');
        $this->addSql('DROP TABLE position');
        $this->addSql('DROP TABLE user_position_log');
        $this->addSql('ALTER TABLE app_user DROP FOREIGN KEY FK_88BDF3E9DD842E46');
        $this->addSql('DROP INDEX IDX_88BDF3E9DD842E46 ON app_user');
        $this->addSql('ALTER TABLE app_user DROP position_id');
    }
}
