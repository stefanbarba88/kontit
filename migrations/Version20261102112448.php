<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Šef je automatski član tima koji vodi (samo podaci, šema se ne menja).
 */
final class Version20261102112448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Šef tima postaje član tima koji vodi (jedno članstvo po osobi)';
    }

    public function up(Schema $schema): void
    {
        // šef koji je član tuđeg (ili obrisanog) tima izlazi iz njega
        $this->addSql('UPDATE team_member m JOIN team mt ON mt.id = m.team_id SET m.left_at = NOW(), m.updated_at = NOW() '
            .'WHERE m.left_at IS NULL AND (mt.leader_id <> m.user_id OR mt.deleted_at IS NOT NULL) '
            .'AND EXISTS (SELECT 1 FROM team t WHERE t.leader_id = m.user_id AND t.deleted_at IS NULL)');
        // šef bez članstva postaje član (prvog) tima koji vodi
        $this->addSql('INSERT INTO team_member (team_id, user_id, joined_at, left_at, created_at, updated_at) '
            .'SELECT MIN(t.id), t.leader_id, NOW(), NULL, NOW(), NOW() FROM team t '
            .'WHERE t.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM team_member m WHERE m.user_id = t.leader_id AND m.left_at IS NULL) '
            .'GROUP BY t.leader_id');
    }

    public function down(Schema $schema): void
    {
        // članstva ostaju: ne zna se koja su nastala ovom migracijom, a šef i dalje sme da bude član tima
    }
}
