<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027145753 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client ADD postal_code VARCHAR(10) DEFAULT NULL, ADD short_name VARCHAR(150) DEFAULT NULL, ADD municipality VARCHAR(100) DEFAULT NULL, ADD region VARCHAR(100) DEFAULT NULL, ADD activity_name VARCHAR(255) DEFAULT NULL, ADD registration_date DATE DEFAULT NULL, ADD founding_date DATE DEFAULT NULL, ADD nbs_company_type_id INT DEFAULT NULL, ADD nbs_status_id INT DEFAULT NULL, ADD nbs_synced_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client DROP postal_code, DROP short_name, DROP municipality, DROP region, DROP activity_name, DROP registration_date, DROP founding_date, DROP nbs_company_type_id, DROP nbs_status_id, DROP nbs_synced_at');
    }
}
