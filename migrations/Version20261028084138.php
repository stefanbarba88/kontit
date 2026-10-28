<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028084138 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Klijent: obavezan kraći naziv (do 40 karaktera), uklonjena pravna forma';
    }

    public function up(Schema $schema): void
    {
        // kraći naziv: prazan se popuni iz punog naziva, a sve se skrati na 40 karaktera (korisnik kasnije ispravlja ručno)
        $this->addSql("UPDATE client SET short_name = LEFT(TRIM(COALESCE(NULLIF(TRIM(short_name), ''), name)), 40)");
        $this->addSql('ALTER TABLE client DROP legal_form, CHANGE short_name short_name VARCHAR(40) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client ADD legal_form VARCHAR(20) DEFAULT "OSTALO" NOT NULL, CHANGE short_name short_name VARCHAR(150) DEFAULT NULL');
    }
}
