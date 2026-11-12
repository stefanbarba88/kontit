<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112132735 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Provera zadataka: position.requires_task_review, job_template posebna potvrda, task review_mode/snapshot proveravača';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE job_template_approver (job_template_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_D3D39DCA66B614F1 (job_template_id), INDEX IDX_D3D39DCAA76ED395 (user_id), PRIMARY KEY (job_template_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE task_required_approver (task_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_FA3512868DB60186 (task_id), INDEX IDX_FA351286A76ED395 (user_id), PRIMARY KEY (task_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE job_template_approver ADD CONSTRAINT FK_D3D39DCA66B614F1 FOREIGN KEY (job_template_id) REFERENCES job_template (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_template_approver ADD CONSTRAINT FK_D3D39DCAA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_required_approver ADD CONSTRAINT FK_FA3512868DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_required_approver ADD CONSTRAINT FK_FA351286A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_template ADD requires_approval TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE position ADD requires_task_review TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE task ADD review_mode VARCHAR(10) DEFAULT \'AUTO\' NOT NULL, ADD review_decided_at DATETIME DEFAULT NULL, ADD review_from_job TINYINT DEFAULT 0 NOT NULL, ADD completed_without_review TINYINT DEFAULT 0 NOT NULL, ADD review_decided_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25C85DAF04 FOREIGN KEY (review_decided_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_527EDB25C85DAF04 ON task (review_decided_by_id)');
        // početno: Entry level i Junior traže proveru, Samostalni knjigovođa i Šef ne (idempotentno, po imenu)
        $this->addSql("UPDATE position SET requires_task_review = 1 WHERE name IN ('Entry level', 'Junior')");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE job_template_approver DROP FOREIGN KEY FK_D3D39DCA66B614F1');
        $this->addSql('ALTER TABLE job_template_approver DROP FOREIGN KEY FK_D3D39DCAA76ED395');
        $this->addSql('ALTER TABLE task_required_approver DROP FOREIGN KEY FK_FA3512868DB60186');
        $this->addSql('ALTER TABLE task_required_approver DROP FOREIGN KEY FK_FA351286A76ED395');
        $this->addSql('DROP TABLE job_template_approver');
        $this->addSql('DROP TABLE task_required_approver');
        $this->addSql('ALTER TABLE job_template DROP requires_approval');
        $this->addSql('ALTER TABLE position DROP requires_task_review');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25C85DAF04');
        $this->addSql('DROP INDEX IDX_527EDB25C85DAF04 ON task');
        $this->addSql('ALTER TABLE task DROP review_mode, DROP review_decided_at, DROP review_from_job, DROP completed_without_review, DROP review_decided_by_id');
    }
}
