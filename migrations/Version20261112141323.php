<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112141323 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Moja lista: todo_item (sva vremena u UTC)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE todo_item (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, note LONGTEXT DEFAULT NULL, due_at DATETIME DEFAULT NULL, due_has_time TINYINT DEFAULT 0 NOT NULL, remind_at DATETIME DEFAULT NULL, remind_by_email TINYINT DEFAULT 0 NOT NULL, reminder_sent_at DATETIME DEFAULT NULL, shown_at DATETIME DEFAULT NULL, repeat_mode VARCHAR(10) DEFAULT \'NONE\' NOT NULL, repeat_day SMALLINT DEFAULT NULL, is_done TINYINT DEFAULT 0 NOT NULL, done_at DATETIME DEFAULT NULL, position INT DEFAULT 0 NOT NULL, pinned TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, owner_id INT NOT NULL, client_id INT DEFAULT NULL, task_id INT DEFAULT NULL, created_task_id INT DEFAULT NULL, repeat_source_id INT DEFAULT NULL, INDEX IDX_TODO_OWNER (owner_id, is_done, due_at), INDEX IDX_TODO_REMIND (remind_at, reminder_sent_at), INDEX IDX_40CA43017E3C61F9 (owner_id), INDEX IDX_40CA430119EB6921 (client_id), INDEX IDX_40CA43018DB60186 (task_id), INDEX IDX_40CA4301CBDC13C0 (created_task_id), INDEX IDX_40CA4301FA512853 (repeat_source_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE todo_item ADD CONSTRAINT FK_40CA43017E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE todo_item ADD CONSTRAINT FK_40CA430119EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE todo_item ADD CONSTRAINT FK_40CA43018DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE todo_item ADD CONSTRAINT FK_40CA4301CBDC13C0 FOREIGN KEY (created_task_id) REFERENCES task (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE todo_item ADD CONSTRAINT FK_40CA4301FA512853 FOREIGN KEY (repeat_source_id) REFERENCES todo_item (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE todo_item DROP FOREIGN KEY FK_40CA43017E3C61F9');
        $this->addSql('ALTER TABLE todo_item DROP FOREIGN KEY FK_40CA430119EB6921');
        $this->addSql('ALTER TABLE todo_item DROP FOREIGN KEY FK_40CA43018DB60186');
        $this->addSql('ALTER TABLE todo_item DROP FOREIGN KEY FK_40CA4301CBDC13C0');
        $this->addSql('ALTER TABLE todo_item DROP FOREIGN KEY FK_40CA4301FA512853');
        $this->addSql('DROP TABLE todo_item');
    }
}
