<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028093111 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ugovorene usluge: napomena na poslu klijenta, predložena cena paketa, izvor AD_HOC postaje INDIVIDUAL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_job ADD note LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE service_package ADD price NUMERIC(10, 2) DEFAULT NULL');
        // ad hoc posao se sada zove pojedinačan (SPEC 8h.1)
        $this->addSql("UPDATE client_job SET source = 'INDIVIDUAL' WHERE source = 'AD_HOC'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE client_job SET source = 'AD_HOC' WHERE source = 'INDIVIDUAL'");
        $this->addSql('ALTER TABLE client_job DROP note');
        $this->addSql('ALTER TABLE service_package DROP price');
    }
}
