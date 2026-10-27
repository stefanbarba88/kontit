<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027112149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, legal_form VARCHAR(20) NOT NULL, pib VARCHAR(9) DEFAULT NULL, maticni_broj VARCHAR(8) DEFAULT NULL, pdv_obveznik TINYINT DEFAULT 0 NOT NULL, address VARCHAR(255) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, contact_person VARCHAR(150) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, start_date DATE DEFAULT NULL, note LONGTEXT DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, primary_accountant_id INT NOT NULL, backup_accountant_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_CLIENT_PIB (pib), INDEX IDX_C7440455FAA8C6C9 (primary_accountant_id), INDEX IDX_C7440455D5597660 (backup_accountant_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE client_assignment_log (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, started_at DATETIME NOT NULL, ended_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, client_id INT NOT NULL, user_id INT NOT NULL, changed_by_id INT DEFAULT NULL, INDEX IDX_769FABF519EB6921 (client_id), INDEX IDX_769FABF5A76ED395 (user_id), INDEX IDX_769FABF5828AD0A0 (changed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455FAA8C6C9 FOREIGN KEY (primary_accountant_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455D5597660 FOREIGN KEY (backup_accountant_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE client_assignment_log ADD CONSTRAINT FK_769FABF519EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE client_assignment_log ADD CONSTRAINT FK_769FABF5A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE client_assignment_log ADD CONSTRAINT FK_769FABF5828AD0A0 FOREIGN KEY (changed_by_id) REFERENCES app_user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455FAA8C6C9');
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455D5597660');
        $this->addSql('ALTER TABLE client_assignment_log DROP FOREIGN KEY FK_769FABF519EB6921');
        $this->addSql('ALTER TABLE client_assignment_log DROP FOREIGN KEY FK_769FABF5A76ED395');
        $this->addSql('ALTER TABLE client_assignment_log DROP FOREIGN KEY FK_769FABF5828AD0A0');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE client_assignment_log');
    }
}
