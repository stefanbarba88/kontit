<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * P3 (SPEC 8g): kategorije poslova kao entitet, raspored posla (schedule_*) i override rasporeda na ClientJob-u,
 * predložena cena posla. Podaci se prenose iz starih kolona (category, recurrence, due_day, due_month, generate_days_before).
 */
final class Version20261028091846 extends AbstractMigration
{
    /** Dosadašnji enum kategorija: vrednost => [naziv, boja (DESIGN.md 3.6), ikonica] */
    private const CATEGORIES = [
        'KNJIZENJE' => ['Knjiženje', '#0089FF', 'book-open'],
        'PDV' => ['PDV', '#5B6CFF', 'receipt'],
        'PLATE' => ['Plate', '#22C55E', 'wallet'],
        'PRIJAVE' => ['Prijave', '#14B8A6', 'file-check'],
        'ZAVRSNI_RACUN' => ['Završni račun', '#8B5CF6', 'calculator'],
        'SAVETOVANJE' => ['Savetovanje', '#F59E0B', 'message-circle'],
        'ADMINISTRATIVNO' => ['Administrativno', '#F43F5E', 'folder'],
        'OSTALO' => ['Ostalo', '#64748B', 'circle-ellipsis'],
    ];

    public function getDescription(): string
    {
        return 'Kategorije poslova (job_category), raspored posla i override rasporeda na ClientJob-u, predložena cena posla';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE job_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, color VARCHAR(7) NOT NULL, icon VARCHAR(40) DEFAULT NULL, position INT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_610BBCBA5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $position = 0;
        foreach (self::CATEGORIES as [$name, $color, $icon]) {
            $this->addSql('INSERT INTO job_category (name, color, icon, position, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())', [$name, $color, $icon, $position++]);
        }

        // posao: nove kolone, pa prenos vrednosti
        $this->addSql('ALTER TABLE job_template ADD category_id INT DEFAULT NULL, ADD default_price NUMERIC(10, 2) DEFAULT NULL, ADD schedule_type VARCHAR(12) DEFAULT NULL, ADD schedule_interval INT DEFAULT NULL, ADD schedule_day_of_week INT DEFAULT NULL, ADD schedule_day_of_month INT DEFAULT NULL, ADD schedule_month_of_quarter INT DEFAULT NULL, ADD schedule_month INT DEFAULT NULL, ADD schedule_working_days_only TINYINT DEFAULT NULL, ADD schedule_lead_days INT DEFAULT NULL, ADD schedule_non_working_day_shift VARCHAR(10) DEFAULT NULL');
        foreach (self::CATEGORIES as $value => [$name]) {
            $this->addSql('UPDATE job_template SET category_id = (SELECT id FROM job_category WHERE name = ?) WHERE category = ?', [$name, $value]);
        }
        $this->addSql("UPDATE job_template SET category_id = (SELECT id FROM job_category WHERE name = 'Ostalo') WHERE category_id IS NULL");
        // jednokratni posao postaje "po potrebi" (SPEC 8g.3), nedeljni dan u nedelji, kvartalni 1. mesec posle kvartala
        $this->addSql("UPDATE job_template SET
            schedule_type = CASE recurrence WHEN 'ONE_TIME' THEN 'NONE' ELSE recurrence END,
            schedule_interval = CASE WHEN recurrence IN ('WEEKLY', 'MONTHLY') THEN 1 ELSE NULL END,
            schedule_day_of_week = CASE WHEN recurrence = 'WEEKLY' THEN COALESCE(due_day, 5) ELSE NULL END,
            schedule_day_of_month = CASE WHEN recurrence IN ('MONTHLY', 'QUARTERLY', 'YEARLY') THEN COALESCE(due_day, 15) ELSE NULL END,
            schedule_month_of_quarter = CASE WHEN recurrence = 'QUARTERLY' THEN 1 ELSE NULL END,
            schedule_month = CASE WHEN recurrence = 'YEARLY' THEN COALESCE(due_month, 1) ELSE NULL END,
            schedule_working_days_only = 0,
            schedule_lead_days = generate_days_before,
            schedule_non_working_day_shift = 'NEXT'");
        $this->addSql('ALTER TABLE job_template DROP category, DROP recurrence, DROP due_day, DROP due_month, DROP generate_days_before, CHANGE category_id category_id INT NOT NULL');
        $this->addSql('ALTER TABLE job_template ADD CONSTRAINT FK_F027BAF812469DE2 FOREIGN KEY (category_id) REFERENCES job_category (id)');
        $this->addSql('CREATE INDEX IDX_F027BAF812469DE2 ON job_template (category_id)');

        // posao klijenta: override rasporeda (prazan = kao u poslu); stari override (ponavljanje i/ili dan) postaje ceo raspored
        $this->addSql('ALTER TABLE client_job ADD schedule_override_type VARCHAR(12) DEFAULT NULL, ADD schedule_override_interval INT DEFAULT NULL, ADD schedule_override_day_of_week INT DEFAULT NULL, ADD schedule_override_day_of_month INT DEFAULT NULL, ADD schedule_override_month_of_quarter INT DEFAULT NULL, ADD schedule_override_month INT DEFAULT NULL, ADD schedule_override_working_days_only TINYINT DEFAULT NULL, ADD schedule_override_lead_days INT DEFAULT NULL, ADD schedule_override_non_working_day_shift VARCHAR(10) DEFAULT NULL');
        $this->addSql("UPDATE client_job j JOIN job_template t ON t.id = j.job_template_id SET
            j.schedule_override_type = CASE COALESCE(j.recurrence_override, t.schedule_type) WHEN 'ONE_TIME' THEN 'NONE' ELSE COALESCE(j.recurrence_override, t.schedule_type) END,
            j.schedule_override_interval = 1,
            j.schedule_override_day_of_week = CASE WHEN COALESCE(j.recurrence_override, t.schedule_type) = 'WEEKLY' THEN COALESCE(j.due_day_override, t.schedule_day_of_week, 5) ELSE NULL END,
            j.schedule_override_day_of_month = COALESCE(j.due_day_override, t.schedule_day_of_month, 15),
            j.schedule_override_month_of_quarter = COALESCE(t.schedule_month_of_quarter, 1),
            j.schedule_override_month = COALESCE(t.schedule_month, 1),
            j.schedule_override_working_days_only = 0,
            j.schedule_override_lead_days = t.schedule_lead_days,
            j.schedule_override_non_working_day_shift = 'NEXT'
            WHERE j.recurrence_override IS NOT NULL OR j.due_day_override IS NOT NULL");
        $this->addSql('ALTER TABLE client_job DROP recurrence_override, DROP due_day_override');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_job ADD recurrence_override VARCHAR(20) DEFAULT NULL, ADD due_day_override INT DEFAULT NULL');
        $this->addSql("UPDATE client_job SET recurrence_override = CASE schedule_override_type WHEN 'NONE' THEN 'ONE_TIME' WHEN 'DAILY' THEN 'WEEKLY' ELSE schedule_override_type END, due_day_override = COALESCE(schedule_override_day_of_month, schedule_override_day_of_week) WHERE schedule_override_type IS NOT NULL");
        $this->addSql('ALTER TABLE client_job DROP schedule_override_type, DROP schedule_override_interval, DROP schedule_override_day_of_week, DROP schedule_override_day_of_month, DROP schedule_override_month_of_quarter, DROP schedule_override_month, DROP schedule_override_working_days_only, DROP schedule_override_lead_days, DROP schedule_override_non_working_day_shift');

        $this->addSql('ALTER TABLE job_template DROP FOREIGN KEY FK_F027BAF812469DE2');
        $this->addSql('DROP INDEX IDX_F027BAF812469DE2 ON job_template');
        $this->addSql("ALTER TABLE job_template ADD category VARCHAR(30) NOT NULL DEFAULT 'OSTALO', ADD recurrence VARCHAR(20) NOT NULL DEFAULT 'MONTHLY', ADD due_day INT DEFAULT NULL, ADD due_month INT DEFAULT NULL, ADD generate_days_before INT NOT NULL DEFAULT 10");
        foreach (self::CATEGORIES as $value => [$name]) {
            $this->addSql('UPDATE job_template SET category = ? WHERE category_id = (SELECT id FROM job_category WHERE name = ?)', [$value, $name]);
        }
        $this->addSql("UPDATE job_template SET recurrence = CASE schedule_type WHEN 'NONE' THEN 'ONE_TIME' WHEN 'DAILY' THEN 'WEEKLY' ELSE schedule_type END, due_day = COALESCE(schedule_day_of_month, schedule_day_of_week), due_month = schedule_month, generate_days_before = COALESCE(schedule_lead_days, 10)");
        $this->addSql('ALTER TABLE job_template DROP category_id, DROP default_price, DROP schedule_type, DROP schedule_interval, DROP schedule_day_of_week, DROP schedule_day_of_month, DROP schedule_month_of_quarter, DROP schedule_month, DROP schedule_working_days_only, DROP schedule_lead_days, DROP schedule_non_working_day_shift');
        $this->addSql('DROP TABLE job_category');
    }
}
