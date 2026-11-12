<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Dopuna Version20261112134053: završeni zadaci bez approved_at (stari ili ručno unošeni podaci)
 * dobijaju finished_at iz updated_at, da statistika po finished_at ne izgubi nijedan završen zadatak.
 */
final class Version20261112140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'task.finished_at: popuna za ZAVRSENO bez approved_at (iz updated_at)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE task SET finished_at = approved_at WHERE status = 'ZAVRSENO' AND finished_at IS NULL AND approved_at IS NOT NULL");
        $this->addSql("UPDATE task SET finished_at = updated_at WHERE status = 'ZAVRSENO' AND finished_at IS NULL");
    }

    public function down(Schema $schema): void
    {
    }
}
