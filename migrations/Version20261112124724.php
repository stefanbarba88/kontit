<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migracija B: ukida staru tekstualnu kolonu app_user.position (zamenjena je app_user.position_id).
 * Pre toga mora da se pokrene app:positions:migrate-legacy; ako neki korisnik ima staru vrednost, a nema position_id, migracija staje.
 */
final class Version20261112124724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pozicije zaposlenih: ukida staru tekstualnu kolonu app_user.position (posle app:positions:migrate-legacy)';
    }

    public function up(Schema $schema): void
    {
        $unmapped = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM app_user WHERE position IS NOT NULL AND TRIM(position) <> '' AND position_id IS NULL");
        $this->abortIf($unmapped > 0, sprintf('%d korisnika ima staru tekstualnu poziciju bez position_id. Prvo pokreni: php bin/console app:positions:migrate-legacy', $unmapped));

        $this->addSql('ALTER TABLE app_user DROP COLUMN position');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD position VARCHAR(100) DEFAULT NULL');
        $this->addSql('UPDATE app_user u INNER JOIN position p ON p.id = u.position_id SET u.position = p.name');
    }
}
