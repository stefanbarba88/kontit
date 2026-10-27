<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027150535 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_setting (setting_key VARCHAR(60) NOT NULL, setting_value VARCHAR(255) NOT NULL, PRIMARY KEY (setting_key)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE client_job (id INT AUTO_INCREMENT NOT NULL, source VARCHAR(20) NOT NULL, is_excluded TINYINT NOT NULL, excluded_reason LONGTEXT DEFAULT NULL, excluded_at DATETIME DEFAULT NULL, recurrence_override VARCHAR(20) DEFAULT NULL, due_day_override INT DEFAULT NULL, estimated_minutes_override INT DEFAULT NULL, price NUMERIC(10, 2) DEFAULT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, is_active TINYINT NOT NULL, is_approved TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, client_id INT NOT NULL, job_template_id INT NOT NULL, client_package_id INT DEFAULT NULL, excluded_by_id INT DEFAULT NULL, assignee_override_id INT DEFAULT NULL, proposed_by_id INT DEFAULT NULL, INDEX IDX_DC7C735C19EB6921 (client_id), INDEX IDX_DC7C735C66B614F1 (job_template_id), INDEX IDX_DC7C735C641896BE (client_package_id), INDEX IDX_DC7C735C5B30B80B (excluded_by_id), INDEX IDX_DC7C735CFC971889 (assignee_override_id), INDEX IDX_DC7C735CDAB5A938 (proposed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE client_package (id INT AUTO_INCREMENT NOT NULL, agreed_price NUMERIC(10, 2) NOT NULL, discount_percent NUMERIC(5, 2) DEFAULT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, is_active TINYINT NOT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, client_id INT NOT NULL, package_id INT NOT NULL, INDEX IDX_8C5523E819EB6921 (client_id), INDEX IDX_8C5523E8F44CABFF (package_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE client_package_price_log (id INT AUTO_INCREMENT NOT NULL, old_price NUMERIC(10, 2) DEFAULT NULL, new_price NUMERIC(10, 2) NOT NULL, effective_from DATE NOT NULL, created_at DATETIME NOT NULL, client_package_id INT NOT NULL, changed_by_id INT DEFAULT NULL, INDEX IDX_5AF64C90641896BE (client_package_id), INDEX IDX_5AF64C90828AD0A0 (changed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE holiday (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, name VARCHAR(150) NOT NULL, UNIQUE INDEX UNIQ_DC9AB234AA9E377A (date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE job_template (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, category VARCHAR(30) NOT NULL, recurrence VARCHAR(20) NOT NULL, due_day INT DEFAULT NULL, due_month INT DEFAULT NULL, generate_days_before INT NOT NULL, default_priority VARCHAR(20) NOT NULL, completion_mode VARCHAR(10) NOT NULL, estimated_minutes INT NOT NULL, default_price NUMERIC(10, 2) DEFAULT NULL, price_unit VARCHAR(20) NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE job_template_checklist_item (id INT AUTO_INCREMENT NOT NULL, text VARCHAR(255) NOT NULL, position INT NOT NULL, job_template_id INT NOT NULL, INDEX IDX_B2D2499266B614F1 (job_template_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE service_package (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, monthly_price NUMERIC(10, 2) NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE service_package_item (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, package_id INT NOT NULL, job_template_id INT NOT NULL, UNIQUE INDEX UNIQ_PACKAGE_JOB (package_id, job_template_id), INDEX IDX_7BFA3533F44CABFF (package_id), INDEX IDX_7BFA353366B614F1 (job_template_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE client_job ADD CONSTRAINT FK_DC7C735C19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE client_job ADD CONSTRAINT FK_DC7C735C66B614F1 FOREIGN KEY (job_template_id) REFERENCES job_template (id)');
        $this->addSql('ALTER TABLE client_job ADD CONSTRAINT FK_DC7C735C641896BE FOREIGN KEY (client_package_id) REFERENCES client_package (id)');
        $this->addSql('ALTER TABLE client_job ADD CONSTRAINT FK_DC7C735C5B30B80B FOREIGN KEY (excluded_by_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE client_job ADD CONSTRAINT FK_DC7C735CFC971889 FOREIGN KEY (assignee_override_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE client_job ADD CONSTRAINT FK_DC7C735CDAB5A938 FOREIGN KEY (proposed_by_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE client_package ADD CONSTRAINT FK_8C5523E819EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE client_package ADD CONSTRAINT FK_8C5523E8F44CABFF FOREIGN KEY (package_id) REFERENCES service_package (id)');
        $this->addSql('ALTER TABLE client_package_price_log ADD CONSTRAINT FK_5AF64C90641896BE FOREIGN KEY (client_package_id) REFERENCES client_package (id)');
        $this->addSql('ALTER TABLE client_package_price_log ADD CONSTRAINT FK_5AF64C90828AD0A0 FOREIGN KEY (changed_by_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE job_template_checklist_item ADD CONSTRAINT FK_B2D2499266B614F1 FOREIGN KEY (job_template_id) REFERENCES job_template (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_package_item ADD CONSTRAINT FK_7BFA3533F44CABFF FOREIGN KEY (package_id) REFERENCES service_package (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_package_item ADD CONSTRAINT FK_7BFA353366B614F1 FOREIGN KEY (job_template_id) REFERENCES job_template (id)');
        $this->addSql('ALTER TABLE app_user ADD capacity_hours INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client_job DROP FOREIGN KEY FK_DC7C735C19EB6921');
        $this->addSql('ALTER TABLE client_job DROP FOREIGN KEY FK_DC7C735C66B614F1');
        $this->addSql('ALTER TABLE client_job DROP FOREIGN KEY FK_DC7C735C641896BE');
        $this->addSql('ALTER TABLE client_job DROP FOREIGN KEY FK_DC7C735C5B30B80B');
        $this->addSql('ALTER TABLE client_job DROP FOREIGN KEY FK_DC7C735CFC971889');
        $this->addSql('ALTER TABLE client_job DROP FOREIGN KEY FK_DC7C735CDAB5A938');
        $this->addSql('ALTER TABLE client_package DROP FOREIGN KEY FK_8C5523E819EB6921');
        $this->addSql('ALTER TABLE client_package DROP FOREIGN KEY FK_8C5523E8F44CABFF');
        $this->addSql('ALTER TABLE client_package_price_log DROP FOREIGN KEY FK_5AF64C90641896BE');
        $this->addSql('ALTER TABLE client_package_price_log DROP FOREIGN KEY FK_5AF64C90828AD0A0');
        $this->addSql('ALTER TABLE job_template_checklist_item DROP FOREIGN KEY FK_B2D2499266B614F1');
        $this->addSql('ALTER TABLE service_package_item DROP FOREIGN KEY FK_7BFA3533F44CABFF');
        $this->addSql('ALTER TABLE service_package_item DROP FOREIGN KEY FK_7BFA353366B614F1');
        $this->addSql('DROP TABLE app_setting');
        $this->addSql('DROP TABLE client_job');
        $this->addSql('DROP TABLE client_package');
        $this->addSql('DROP TABLE client_package_price_log');
        $this->addSql('DROP TABLE holiday');
        $this->addSql('DROP TABLE job_template');
        $this->addSql('DROP TABLE job_template_checklist_item');
        $this->addSql('DROP TABLE service_package');
        $this->addSql('DROP TABLE service_package_item');
        $this->addSql('ALTER TABLE app_user DROP capacity_hours');
    }
}
