<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028134059 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dokumenti korisnika: kategorije (6 početnih), dokumenti, podsetnik za doznaku na odsustvu';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE document_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, position INT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_898DE8985E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_document (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(150) NOT NULL, stored_name VARCHAR(80) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, valid_until DATE DEFAULT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, expiry_notified_days INT DEFAULT NULL, user_id INT NOT NULL, category_id INT NOT NULL, uploaded_by_id INT NOT NULL, absence_id INT DEFAULT NULL, INDEX IDX_DOC_USER_DELETED (user_id, deleted_at), INDEX IDX_DOC_VALID_UNTIL (valid_until), INDEX IDX_38E46E76A76ED395 (user_id), INDEX IDX_38E46E7612469DE2 (category_id), INDEX IDX_38E46E76A2B28FE8 (uploaded_by_id), INDEX IDX_38E46E762DFF238F (absence_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_document ADD CONSTRAINT FK_38E46E76A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE user_document ADD CONSTRAINT FK_38E46E7612469DE2 FOREIGN KEY (category_id) REFERENCES document_category (id)');
        $this->addSql('ALTER TABLE user_document ADD CONSTRAINT FK_38E46E76A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE user_document ADD CONSTRAINT FK_38E46E762DFF238F FOREIGN KEY (absence_id) REFERENCES user_absence (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_absence ADD document_reminder_sent TINYINT DEFAULT 0 NOT NULL');
        // početne kategorije (SPEC 8l)
        $this->addSql("INSERT INTO document_category (name, position, is_active, created_at, updated_at) VALUES ('Ugovor', 0, 1, NOW(), NOW())");
        $this->addSql("INSERT INTO document_category (name, position, is_active, created_at, updated_at) VALUES ('Rešenje', 1, 1, NOW(), NOW())");
        $this->addSql("INSERT INTO document_category (name, position, is_active, created_at, updated_at) VALUES ('Potvrda', 2, 1, NOW(), NOW())");
        $this->addSql("INSERT INTO document_category (name, position, is_active, created_at, updated_at) VALUES ('Izvod', 3, 1, NOW(), NOW())");
        $this->addSql("INSERT INTO document_category (name, position, is_active, created_at, updated_at) VALUES ('Doznaka', 4, 1, NOW(), NOW())");
        $this->addSql("INSERT INTO document_category (name, position, is_active, created_at, updated_at) VALUES ('Ostalo', 5, 1, NOW(), NOW())");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_document DROP FOREIGN KEY FK_38E46E76A76ED395');
        $this->addSql('ALTER TABLE user_document DROP FOREIGN KEY FK_38E46E7612469DE2');
        $this->addSql('ALTER TABLE user_document DROP FOREIGN KEY FK_38E46E76A2B28FE8');
        $this->addSql('ALTER TABLE user_document DROP FOREIGN KEY FK_38E46E762DFF238F');
        $this->addSql('DROP TABLE document_category');
        $this->addSql('DROP TABLE user_document');
        $this->addSql('ALTER TABLE user_absence DROP document_reminder_sent');
    }
}
