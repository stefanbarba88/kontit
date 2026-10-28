<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * P6 (SPEC 8j): vrste odsustva (umesto enuma reason), radni dani, pola dana, rok za odobrenje, godišnji odmor (pravo, prenos).
 * Status NA_CEKANJU postaje NA_ODOBRENJU, a notifikacija ABSENCE_DECIDED se deli na ABSENCE_APPROVED i ABSENCE_REJECTED.
 */
final class Version20261028132118 extends AbstractMigration
{
    /** Početne vrste: [naziv, boja, ikonica, odobrenje, troši godišnji, dokument, stari razlog] */
    private const TYPES = [
        ['Godišnji odmor', '#0089FF', 'palmtree', 1, 1, 0, 'GODISNJI'],
        ['Slava', '#8B5CF6', 'church', 1, 0, 0, null],
        ['Bolovanje', '#F43F5E', 'heart-pulse', 0, 0, 1, 'BOLOVANJE'],
        ['Plaćeno odsustvo', '#14B8A6', 'wallet', 1, 0, 0, null],
        ['Neplaćeno odsustvo', '#F59E0B', 'briefcase', 1, 0, 0, null],
        ['Ostalo', '#64748B', 'circle-ellipsis', 1, 0, 0, 'OSTALO'],
    ];

    public function getDescription(): string
    {
        return 'Odsustva: vrste odsustva, radni dani, pola dana, rok za odobrenje, godišnji odmor (pravo i prenos)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE absence_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, color VARCHAR(7) NOT NULL, icon VARCHAR(40) DEFAULT NULL, requires_approval TINYINT NOT NULL, counts_against_leave TINYINT NOT NULL, requires_document TINYINT NOT NULL, max_days_per_year INT DEFAULT NULL, position INT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_FBCF99B65E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        foreach (self::TYPES as $i => [$name, $color, $icon, $approval, $leave, $doc]) {
            $this->addSql('INSERT INTO absence_type (name, color, icon, requires_approval, counts_against_leave, requires_document, position, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())', [$name, $color, $icon, $approval, $leave, $doc, $i]);
        }

        $this->addSql('CREATE TABLE leave_balance (id INT AUTO_INCREMENT NOT NULL, year INT NOT NULL, entitled NUMERIC(5, 1) NOT NULL, carried_over NUMERIC(5, 1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_LEAVE_USER_YEAR (user_id, year), INDEX IDX_EAAB6719A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE leave_balance ADD CONSTRAINT FK_EAAB6719A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_user ADD annual_leave_days INT DEFAULT NULL');

        $this->addSql('ALTER TABLE user_absence ADD half_day TINYINT DEFAULT 0 NOT NULL, ADD working_days NUMERIC(5, 1) DEFAULT 0 NOT NULL, ADD decision_note LONGTEXT DEFAULT NULL, ADD approval_deadline DATETIME DEFAULT NULL, ADD reminder_sent TINYINT DEFAULT 0 NOT NULL, ADD type_id INT DEFAULT NULL');
        foreach (self::TYPES as [$name, , , , , , $reason]) {
            if (null !== $reason) {
                $this->addSql('UPDATE user_absence SET type_id = (SELECT id FROM absence_type WHERE name = ?) WHERE reason = ?', [$name, $reason]);
            }
        }
        $this->addSql("UPDATE user_absence SET type_id = (SELECT id FROM absence_type WHERE name = 'Ostalo') WHERE type_id IS NULL");
        // radni dani postojećih odsustava: dani od ponedeljka do petka (praznici se ne oduzimaju za stare zapise)
        $this->addSql('UPDATE user_absence SET working_days = 5 * FLOOR((DATEDIFF(end_date, start_date) + 1) / 7)
            + (SELECT COUNT(*) FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) d
               WHERE d.n < MOD(DATEDIFF(end_date, start_date) + 1, 7) AND WEEKDAY(DATE_ADD(start_date, INTERVAL d.n + 7 * FLOOR((DATEDIFF(end_date, start_date) + 1) / 7) DAY)) < 5)');
        $this->addSql("UPDATE user_absence SET status = 'NA_ODOBRENJU', approval_deadline = DATE_ADD(created_at, INTERVAL 2 DAY) WHERE status = 'NA_CEKANJU'");
        $this->addSql('ALTER TABLE user_absence DROP reason, CHANGE type_id type_id INT NOT NULL');
        $this->addSql('ALTER TABLE user_absence ADD CONSTRAINT FK_2E57FEC3C54C8C93 FOREIGN KEY (type_id) REFERENCES absence_type (id)');
        $this->addSql('CREATE INDEX IDX_ABSENCE_STATUS_DEADLINE ON user_absence (status, approval_deadline)');
        $this->addSql('CREATE INDEX IDX_2E57FEC3C54C8C93 ON user_absence (type_id)');

        // notifikacije: odluka o odsustvu je sada odobreno ili odbijeno
        $this->addSql("UPDATE notification SET type = IF(title LIKE '%odbij%' OR title LIKE '%otkaz%', 'ABSENCE_REJECTED', 'ABSENCE_APPROVED') WHERE type = 'ABSENCE_DECIDED'");
        $this->addSql("UPDATE app_user SET email_notifications = REPLACE(email_notifications, '\"ABSENCE_DECIDED\"', '\"ABSENCE_APPROVED\", \"ABSENCE_REJECTED\"') WHERE email_notifications LIKE '%ABSENCE_DECIDED%'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE notification SET type = 'ABSENCE_DECIDED' WHERE type IN ('ABSENCE_APPROVED', 'ABSENCE_REJECTED', 'ABSENCE_AUTO_APPROVED')");
        $this->addSql("UPDATE app_user SET email_notifications = REPLACE(email_notifications, '\"ABSENCE_APPROVED\", \"ABSENCE_REJECTED\"', '\"ABSENCE_DECIDED\"') WHERE email_notifications LIKE '%ABSENCE_APPROVED%'");
        $this->addSql('ALTER TABLE user_absence DROP FOREIGN KEY FK_2E57FEC3C54C8C93');
        $this->addSql('DROP INDEX IDX_ABSENCE_STATUS_DEADLINE ON user_absence');
        $this->addSql('DROP INDEX IDX_2E57FEC3C54C8C93 ON user_absence');
        $this->addSql("ALTER TABLE user_absence ADD reason VARCHAR(20) DEFAULT 'OSTALO' NOT NULL");
        $this->addSql("UPDATE user_absence a JOIN absence_type t ON t.id = a.type_id SET a.reason = CASE t.name WHEN 'Godišnji odmor' THEN 'GODISNJI' WHEN 'Bolovanje' THEN 'BOLOVANJE' ELSE 'OSTALO' END");
        $this->addSql("UPDATE user_absence SET status = 'NA_CEKANJU' WHERE status = 'NA_ODOBRENJU'");
        $this->addSql("UPDATE user_absence SET status = 'ODOBRENO' WHERE status = 'AUTOMATSKI_ODOBRENO'");
        $this->addSql('ALTER TABLE user_absence DROP half_day, DROP working_days, DROP decision_note, DROP approval_deadline, DROP reminder_sent, DROP type_id');
        $this->addSql('ALTER TABLE app_user DROP annual_leave_days');
        $this->addSql('DROP TABLE leave_balance');
        $this->addSql('DROP TABLE absence_type');
    }
}
