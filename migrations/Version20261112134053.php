<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Provera zadataka, deo 2: task.finished_at (kad je zadatak postao ZAVRSENO, potvrdom ili direktno bez provere, za statistiku)
 * i podrazumevano uključen mail za posebne potvrde kod postojećih korisnika (korisnik ga isključuje u profilu).
 */
final class Version20261112134053 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Provera zadataka: task.finished_at i podrazumevani mail za posebne potvrde';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD finished_at DATETIME DEFAULT NULL');
        // dosadašnji završeni zadaci su svi potvrđeni
        $this->addSql("UPDATE task SET finished_at = approved_at WHERE status = 'ZAVRSENO' AND approved_at IS NOT NULL");
    }

    public function postUp(Schema $schema): void
    {
        $defaults = ['SPECIAL_APPROVAL_SUBMITTED', 'SPECIAL_APPROVAL_REMINDER'];
        foreach ($this->connection->fetchAllAssociative('SELECT id, email_notifications FROM app_user') as $row) {
            $current = json_decode((string) $row['email_notifications'], true);
            $current = \is_array($current) ? $current : [];
            $merged = array_values(array_unique(array_merge($current, $defaults)));
            if ($merged !== $current) {
                $this->connection->update('app_user', ['email_notifications' => json_encode($merged)], ['id' => $row['id']]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP finished_at');
    }
}
