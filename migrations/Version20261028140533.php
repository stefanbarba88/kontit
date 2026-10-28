<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028140533 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Google kalendar: veza sa nalogom (šifrovani tokeni) i mapiranje događaja';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE calendar_sync_link (id INT AUTO_INCREMENT NOT NULL, entity_type VARCHAR(20) NOT NULL, entity_id INT NOT NULL, google_event_id VARCHAR(255) NOT NULL, etag VARCHAR(100) DEFAULT NULL, synced_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_SYNC_ENTITY (entity_type, entity_id), UNIQUE INDEX UNIQ_SYNC_USER_ENTITY (user_id, entity_type, entity_id), INDEX IDX_2716BEF4A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE google_calendar_connection (id INT AUTO_INCREMENT NOT NULL, google_email VARCHAR(180) DEFAULT NULL, calendar_id VARCHAR(255) DEFAULT NULL, access_token LONGTEXT NOT NULL, refresh_token LONGTEXT DEFAULT NULL, expires_at DATETIME DEFAULT NULL, connected_at DATETIME NOT NULL, last_sync_at DATETIME DEFAULT NULL, last_error LONGTEXT DEFAULT NULL, failures INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_1ADCC4AAA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE calendar_sync_link ADD CONSTRAINT FK_2716BEF4A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE google_calendar_connection ADD CONSTRAINT FK_1ADCC4AAA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE calendar_sync_link DROP FOREIGN KEY FK_2716BEF4A76ED395');
        $this->addSql('ALTER TABLE google_calendar_connection DROP FOREIGN KEY FK_1ADCC4AAA76ED395');
        $this->addSql('DROP TABLE calendar_sync_link');
        $this->addSql('DROP TABLE google_calendar_connection');
    }
}
