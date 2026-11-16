<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261116112327 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Indeks task(due_date, client_id) za matricu Pregled poslova po klijentima';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX IDX_TASK_DUE_CLIENT ON task (due_date, client_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_TASK_DUE_CLIENT ON task');
    }
}
