<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261102094121 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Odsustvo: ko je i kada poslednji menjao (edited_by, edited_at)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_absence ADD edited_at DATETIME DEFAULT NULL, ADD edited_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user_absence ADD CONSTRAINT FK_2E57FEC3DD7B2EBC FOREIGN KEY (edited_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_2E57FEC3DD7B2EBC ON user_absence (edited_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_absence DROP FOREIGN KEY FK_2E57FEC3DD7B2EBC');
        $this->addSql('DROP INDEX IDX_2E57FEC3DD7B2EBC ON user_absence');
        $this->addSql('ALTER TABLE user_absence DROP edited_at, DROP edited_by_id');
    }
}
