<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028134856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tajni token za ICS pretplatu na kalendar';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD ics_token VARCHAR(40) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9F1E0A2BB ON app_user (ics_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_88BDF3E9F1E0A2BB ON app_user');
        $this->addSql('ALTER TABLE app_user DROP ics_token');
    }
}
