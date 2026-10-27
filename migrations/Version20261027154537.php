<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027154537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE job_template DROP default_price');
        $this->addSql('ALTER TABLE service_package DROP monthly_price');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE job_template ADD default_price NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE service_package ADD monthly_price NUMERIC(10, 2) NOT NULL');
    }
}
