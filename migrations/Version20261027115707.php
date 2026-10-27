<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027115707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE checklist_item (id INT AUTO_INCREMENT NOT NULL, text VARCHAR(255) NOT NULL, position INT NOT NULL, is_done TINYINT DEFAULT 0 NOT NULL, done_at DATETIME DEFAULT NULL, task_id INT NOT NULL, assigned_to_id INT DEFAULT NULL, done_by_id INT DEFAULT NULL, INDEX IDX_99EB20F98DB60186 (task_id), INDEX IDX_99EB20F9F4BD7827 (assigned_to_id), INDEX IDX_99EB20F935AE3EF9 (done_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE task (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, due_date DATE DEFAULT NULL, priority VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, completion_mode VARCHAR(3) NOT NULL, submitted_at DATETIME DEFAULT NULL, approved_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, client_id INT DEFAULT NULL, created_by_id INT NOT NULL, approved_by_id INT DEFAULT NULL, INDEX IDX_TASK_STATUS_DUE (status, due_date), INDEX IDX_527EDB2519EB6921 (client_id), INDEX IDX_527EDB25B03A8386 (created_by_id), INDEX IDX_527EDB252D234F6A (approved_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE task_assignee (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, completed_at DATETIME DEFAULT NULL, note LONGTEXT DEFAULT NULL, task_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_TASK_ASSIGNEE (task_id, user_id), INDEX IDX_3C5D16408DB60186 (task_id), INDEX IDX_3C5D1640A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE task_comment (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, type VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, edited_at DATETIME DEFAULT NULL, task_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_8B9578868DB60186 (task_id), INDEX IDX_8B957886F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE checklist_item ADD CONSTRAINT FK_99EB20F98DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE checklist_item ADD CONSTRAINT FK_99EB20F9F4BD7827 FOREIGN KEY (assigned_to_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE checklist_item ADD CONSTRAINT FK_99EB20F935AE3EF9 FOREIGN KEY (done_by_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2519EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB252D234F6A FOREIGN KEY (approved_by_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE task_assignee ADD CONSTRAINT FK_3C5D16408DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_assignee ADD CONSTRAINT FK_3C5D1640A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE task_comment ADD CONSTRAINT FK_8B9578868DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_comment ADD CONSTRAINT FK_8B957886F675F31B FOREIGN KEY (author_id) REFERENCES app_user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE checklist_item DROP FOREIGN KEY FK_99EB20F98DB60186');
        $this->addSql('ALTER TABLE checklist_item DROP FOREIGN KEY FK_99EB20F9F4BD7827');
        $this->addSql('ALTER TABLE checklist_item DROP FOREIGN KEY FK_99EB20F935AE3EF9');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB2519EB6921');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25B03A8386');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB252D234F6A');
        $this->addSql('ALTER TABLE task_assignee DROP FOREIGN KEY FK_3C5D16408DB60186');
        $this->addSql('ALTER TABLE task_assignee DROP FOREIGN KEY FK_3C5D1640A76ED395');
        $this->addSql('ALTER TABLE task_comment DROP FOREIGN KEY FK_8B9578868DB60186');
        $this->addSql('ALTER TABLE task_comment DROP FOREIGN KEY FK_8B957886F675F31B');
        $this->addSql('DROP TABLE checklist_item');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE task_assignee');
        $this->addSql('DROP TABLE task_comment');
    }
}
