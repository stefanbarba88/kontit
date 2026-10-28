<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028090515 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Neradni dani (pravilo za dane u nedelji, praznici, kolektivni odmor) umesto tabele holiday';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE non_working_day (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, name VARCHAR(150) NOT NULL, date_from DATE NOT NULL, date_to DATE NOT NULL, recurring TINYINT NOT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_NWD_DATES (date_from, date_to), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE weekly_non_working_rule (id INT AUTO_INCREMENT NOT NULL, days JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        // vikend je podrazumevano pravilo, a dosadašnji praznici (tabela holiday) postaju praznici samo za tu godinu
        $this->addSql("INSERT INTO weekly_non_working_rule (days, created_at, updated_at) VALUES ('[6,7]', NOW(), NOW())");
        $this->addSql("INSERT INTO non_working_day (type, name, date_from, date_to, recurring, created_at, updated_at) SELECT 'HOLIDAY_ONCE', name, date, date, 0, NOW(), NOW() FROM holiday");
        $this->addSql('DROP TABLE holiday');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE holiday (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, name VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, UNIQUE INDEX UNIQ_DC9AB234AA9E377A (date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('DROP TABLE non_working_day');
        $this->addSql('DROP TABLE weekly_non_working_rule');
    }
}
