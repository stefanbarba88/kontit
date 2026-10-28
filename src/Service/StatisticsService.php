<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Agregatni upiti za dashboard (SPEC 8). Ne proverava prava: kontroler odlučuje čije timove prosleđuje.
 *
 * Pripadnost timu se računa po istoriji članstva (TeamMember): završen zadatak i povratak na doradu
 * pripadaju timu u kome je radnik bio član u trenutku potvrde, odnosno vraćanja. Otvoreni zadaci i
 * klijenti pripadaju trenutnom članstvu.
 */
class StatisticsService
{
    /** Zadatak je otvoren dok nije završen ni otkazan */
    private const OPEN = "('NOVO','U_RADU','CEKA_KLIJENTA','NA_PROVERI')";

    /** Posle koliko dana "Čeka klijenta" postaje upozorenje (SPEC 8, "X dana") */
    public const WAITING_DAYS = 7;

    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @return list<int> aktivni timovi koje korisnik vodi
     */
    public function ledTeamIds(User $leader): array
    {
        return array_map('intval', $this->db->fetchFirstColumn('SELECT id FROM team WHERE leader_id = :u AND deleted_at IS NULL', ['u' => $leader->getId()]));
    }

    /**
     * @return list<int> svi aktivni timovi
     */
    public function allTeamIds(): array
    {
        return array_map('intval', $this->db->fetchFirstColumn('SELECT id FROM team WHERE deleted_at IS NULL'));
    }

    /**
     * @return array<string, mixed>
     */
    public function forWorker(User $user): array
    {
        $id = $user->getId();
        $today = new \DateTimeImmutable('today');
        $monthStart = $today->modify('first day of this month');
        $prevStart = $monthStart->modify('-1 month');

        $open = (int) $this->db->fetchOne(
            'SELECT COUNT(DISTINCT t.id) FROM task t JOIN task_assignee a ON a.task_id = t.id WHERE a.user_id = :u AND t.status IN '.self::OPEN,
            ['u' => $id],
        );
        $overdue = (int) $this->db->fetchOne(
            'SELECT COUNT(DISTINCT t.id) FROM task t JOIN task_assignee a ON a.task_id = t.id WHERE a.user_id = :u AND t.status IN '.self::OPEN.' AND t.due_date < :today',
            ['u' => $id, 'today' => $today->format('Y-m-d')],
        );
        $soon = (int) $this->db->fetchOne(
            'SELECT COUNT(DISTINCT t.id) FROM task t JOIN task_assignee a ON a.task_id = t.id WHERE a.user_id = :u AND t.status IN '.self::OPEN.' AND t.due_date BETWEEN :from AND :to',
            ['u' => $id, 'from' => $today->format('Y-m-d'), 'to' => $today->modify('+3 days')->format('Y-m-d')],
        );

        $doneSql = 'SELECT COUNT(DISTINCT t.id) FROM task t JOIN task_assignee a ON a.task_id = t.id WHERE a.user_id = :u AND t.status = \'ZAVRSENO\' AND t.approved_at >= :from AND t.approved_at < :to';
        $thisMonth = (int) $this->db->fetchOne($doneSql, ['u' => $id, 'from' => $monthStart->format('Y-m-d'), 'to' => $monthStart->modify('+1 month')->format('Y-m-d')]);
        $lastMonth = (int) $this->db->fetchOne($doneSql, ['u' => $id, 'from' => $prevStart->format('Y-m-d'), 'to' => $monthStart->format('Y-m-d')]);

        $perf = $this->performance([$id], null, 90)[$id] ?? ['done' => 0, 'withDue' => 0, 'onTime' => 0];
        $returned = $this->returned([$id], null, 30)[$id] ?? 0;

        return [
            'open' => $open,
            'overdue' => $overdue,
            'dueSoon' => $soon,
            'doneThisMonth' => $thisMonth,
            'doneLastMonth' => $lastMonth,
            'onTimePercent' => self::percent($perf['onTime'], $perf['withDue']),
            'returned30' => $returned,
            'clientsPrimary' => (int) $this->db->fetchOne('SELECT COUNT(*) FROM client WHERE is_active = 1 AND primary_accountant_id = :u', ['u' => $id]),
            'clientsBackup' => (int) $this->db->fetchOne('SELECT COUNT(*) FROM client WHERE is_active = 1 AND backup_accountant_id = :u', ['u' => $id]),
        ];
    }

    /**
     * Pregled timova: opterećenje po radniku, učinak, čekanje na potvrdu i "Čeka klijenta".
     *
     * @param list<int> $teamIds
     *
     * @return array<string, mixed>
     */
    public function forTeams(array $teamIds): array
    {
        if ([] === $teamIds) {
            return ['workers' => [], 'awaiting' => [], 'waiting' => [], 'avgDaysToApproval' => null];
        }

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $members = $this->db->fetchAllAssociative(
            'SELECT u.id, u.first_name, u.last_name, tm.team_id FROM team_member tm JOIN app_user u ON u.id = tm.user_id '
            .'WHERE tm.left_at IS NULL AND u.is_active = 1 AND tm.team_id IN (:teams) ORDER BY u.last_name, u.first_name',
            ['teams' => $teamIds],
            ['teams' => ArrayParameterType::INTEGER],
        );
        $ids = array_map(static fn (array $m) => (int) $m['id'], $members);

        $clients = $this->countBy('SELECT primary_accountant_id k, COUNT(*) n FROM client WHERE is_active = 1 AND primary_accountant_id IN (:ids) GROUP BY primary_accountant_id', $ids);
        $open = $this->countBy('SELECT a.user_id k, COUNT(DISTINCT t.id) n FROM task t JOIN task_assignee a ON a.task_id = t.id WHERE a.user_id IN (:ids) AND t.status IN '.self::OPEN.' GROUP BY a.user_id', $ids);
        $late = $this->countBy('SELECT a.user_id k, COUNT(DISTINCT t.id) n FROM task t JOIN task_assignee a ON a.task_id = t.id WHERE a.user_id IN (:ids) AND t.status IN '.self::OPEN.' AND t.due_date < \''.$today.'\' GROUP BY a.user_id', $ids);
        $perf30 = $this->performance($ids, $teamIds, 30);
        $perf90 = $this->performance($ids, $teamIds, 90);
        $ret30 = $this->returned($ids, $teamIds, 30);
        $ret90 = $this->returned($ids, $teamIds, 90);

        $workers = [];
        foreach ($members as $m) {
            $id = (int) $m['id'];
            $p30 = $perf30[$id] ?? ['done' => 0, 'withDue' => 0, 'onTime' => 0];
            $p90 = $perf90[$id] ?? ['done' => 0, 'withDue' => 0, 'onTime' => 0];
            $workers[$id] = [
                'id' => $id,
                'name' => $m['first_name'].' '.$m['last_name'],
                'teamId' => (int) $m['team_id'],
                'clients' => $clients[$id] ?? 0,
                'open' => $open[$id] ?? 0,
                'overdue' => $late[$id] ?? 0,
                'onTime30' => self::percent($p30['onTime'], $p30['withDue']),
                'onTime90' => self::percent($p90['onTime'], $p90['withDue']),
                'returned30' => self::percent($ret30[$id] ?? 0, $p30['done']),
                'returned90' => self::percent($ret90[$id] ?? 0, $p90['done']),
                'returnedCount30' => $ret30[$id] ?? 0,
            ];
        }

        $awaiting = $this->db->fetchAllAssociative(
            'SELECT DISTINCT t.id, t.title, t.submitted_at FROM task t JOIN task_assignee a ON a.task_id = t.id '
            .'JOIN team_member tm ON tm.user_id = a.user_id AND tm.left_at IS NULL AND tm.team_id IN (:teams) '
            .'WHERE t.status = \'NA_PROVERI\' ORDER BY t.submitted_at ASC',
            ['teams' => $teamIds],
            ['teams' => ArrayParameterType::INTEGER],
        );
        $waiting = $this->db->fetchAllAssociative(
            'SELECT DISTINCT t.id, t.title, t.updated_at, c.short_name client FROM task t JOIN task_assignee a ON a.task_id = t.id '
            .'JOIN team_member tm ON tm.user_id = a.user_id AND tm.left_at IS NULL AND tm.team_id IN (:teams) '
            .'LEFT JOIN client c ON c.id = t.client_id '
            .'WHERE t.status = \'CEKA_KLIJENTA\' AND t.updated_at < :limit ORDER BY t.updated_at ASC',
            ['teams' => $teamIds, 'limit' => (new \DateTimeImmutable('-'.self::WAITING_DAYS.' days'))->format('Y-m-d H:i:s')],
            ['teams' => ArrayParameterType::INTEGER],
        );
        $avg = $this->db->fetchOne(
            'SELECT AVG(TIMESTAMPDIFF(HOUR, x.created_at, x.approved_at)) FROM (SELECT DISTINCT t.id, t.created_at, t.approved_at FROM task t JOIN task_assignee a ON a.task_id = t.id '
            .'WHERE t.status = \'ZAVRSENO\' AND t.approved_at >= :since AND EXISTS (SELECT 1 FROM team_member tm WHERE tm.user_id = a.user_id AND tm.team_id IN (:teams) '
            .'AND tm.joined_at <= t.approved_at AND (tm.left_at IS NULL OR tm.left_at > t.approved_at))) x',
            ['teams' => $teamIds, 'since' => (new \DateTimeImmutable('-90 days'))->format('Y-m-d H:i:s')],
            ['teams' => ArrayParameterType::INTEGER],
        );

        return [
            'workers' => array_values($workers),
            'awaiting' => $awaiting,
            'waiting' => $waiting,
            'avgDaysToApproval' => false === $avg || null === $avg ? null : round((float) $avg / 24, 1),
        ];
    }

    /**
     * Kratak pregled po timu za admina.
     *
     * @return list<array<string, mixed>>
     */
    public function byTeam(): array
    {
        $teams = $this->db->fetchAllAssociative(
            'SELECT t.id, t.name, t.color, CONCAT(l.first_name, \' \', l.last_name) leader FROM team t LEFT JOIN app_user l ON l.id = t.leader_id WHERE t.deleted_at IS NULL ORDER BY t.name'
        );
        $result = [];
        foreach ($teams as $team) {
            $stats = $this->forTeams([(int) $team['id']]);
            $workers = $stats['workers'];
            $sumOnTime = array_sum(array_map(static fn (array $w) => $w['onTime30'], $workers));
            $result[] = [
                'id' => (int) $team['id'],
                'name' => $team['name'],
                'color' => $team['color'],
                'leader' => $team['leader'],
                'members' => count($workers),
                'clients' => array_sum(array_column($workers, 'clients')),
                'open' => array_sum(array_column($workers, 'open')),
                'overdue' => array_sum(array_column($workers, 'overdue')),
                'awaiting' => count($stats['awaiting']),
                'avgDays' => $stats['avgDaysToApproval'],
                'onTime30' => [] === $workers ? null : round($sumOnTime / max(1, count(array_filter($workers, static fn (array $w) => null !== $w['onTime30']))), 0),
            ];
        }

        return $result;
    }

    /**
     * Rizici i raspodela za admina (cela firma).
     *
     * @return array<string, mixed>
     */
    public function forCompany(): array
    {
        $noBackup = $this->db->fetchAllAssociative(
            'SELECT c.id, c.short_name name, CONCAT(u.first_name, \' \', u.last_name) worker FROM client c JOIN app_user u ON u.id = c.primary_accountant_id '
            .'WHERE c.is_active = 1 AND c.backup_accountant_id IS NULL ORDER BY c.short_name'
        );
        $idle = $this->db->fetchAllAssociative(
            'SELECT c.id, c.short_name name, CONCAT(u.first_name, \' \', u.last_name) worker FROM client c JOIN app_user u ON u.id = c.primary_accountant_id '
            .'WHERE c.is_active = 1 AND NOT EXISTS (SELECT 1 FROM task t WHERE t.client_id = c.id AND t.created_at >= :since) ORDER BY c.short_name',
            ['since' => (new \DateTimeImmutable('-30 days'))->format('Y-m-d H:i:s')],
        );
        $perTeam = $this->db->fetchAllAssociative(
            'SELECT COALESCE(t.name, \'Bez tima\') label, COUNT(c.id) n FROM client c '
            .'LEFT JOIN team_member tm ON tm.user_id = c.primary_accountant_id AND tm.left_at IS NULL '
            .'LEFT JOIN team t ON t.id = tm.team_id AND t.deleted_at IS NULL WHERE c.is_active = 1 GROUP BY label ORDER BY n DESC'
        );
        $perWorker = $this->db->fetchAllAssociative(
            'SELECT CONCAT(u.first_name, \' \', u.last_name) label, COUNT(c.id) n FROM client c JOIN app_user u ON u.id = c.primary_accountant_id '
            .'WHERE c.is_active = 1 GROUP BY u.id ORDER BY n DESC, label'
        );

        $start = (new \DateTimeImmutable('first day of this month'))->modify('-11 months')->format('Y-m-d');
        $rows = $this->db->fetchAllKeyValue(
            'SELECT DATE_FORMAT(approved_at, \'%Y-%m\') m, COUNT(*) n FROM task WHERE status = \'ZAVRSENO\' AND approved_at >= :start GROUP BY m',
            ['start' => $start],
        );
        $trend = [];
        for ($i = 11; $i >= 0; --$i) {
            $month = (new \DateTimeImmutable('first day of this month'))->modify("-$i months");
            $trend[] = ['label' => $month->format('m.Y'), 'n' => (int) ($rows[$month->format('Y-m')] ?? 0)];
        }

        $activity = $this->db->fetchAllAssociative(
            'SELECT CONCAT(u.first_name, \' \', u.last_name) name, COUNT(l.id) n FROM app_user u '
            .'LEFT JOIN activity_log l ON l.user_id = u.id AND l.created_at >= :since WHERE u.is_active = 1 GROUP BY u.id ORDER BY n DESC, name',
            ['since' => (new \DateTimeImmutable('-30 days'))->format('Y-m-d H:i:s')],
        );

        return [
            'noBackup' => $noBackup,
            'idleClients' => $idle,
            'clientsPerTeam' => $perTeam,
            'clientsPerWorker' => $perWorker,
            'trend' => $trend,
            'mostActive' => array_slice($activity, 0, 5),
            'leastActive' => array_slice(array_reverse($activity), 0, 5),
        ];
    }

    /**
     * Završeni zadaci radnika u poslednjih $days dana: koliko, koliko ima rok, koliko je stiglo na vreme.
     * Na vreme = radnik/učesnici su ga predali (submitted_at) do roka.
     *
     * @param list<int>      $ids
     * @param list<int>|null $teamIds samo zadaci iz perioda kad je radnik bio član tih timova
     *
     * @return array<int, array{done: int, withDue: int, onTime: int}>
     */
    private function performance(array $ids, ?array $teamIds, int $days): array
    {
        if ([] === $ids) {
            return [];
        }
        $params = ['ids' => $ids, 'since' => (new \DateTimeImmutable("-$days days"))->format('Y-m-d H:i:s')];
        $types = ['ids' => ArrayParameterType::INTEGER];
        $sql = 'SELECT a.user_id id, COUNT(DISTINCT t.id) done, COUNT(DISTINCT CASE WHEN t.due_date IS NOT NULL THEN t.id END) withDue, '
            .'COUNT(DISTINCT CASE WHEN t.due_date IS NOT NULL AND DATE(COALESCE(t.submitted_at, t.approved_at)) <= t.due_date THEN t.id END) onTime '
            .'FROM task t JOIN task_assignee a ON a.task_id = t.id WHERE a.user_id IN (:ids) AND t.status = \'ZAVRSENO\' AND t.approved_at >= :since'
            .$this->teamFilter($teamIds, 't.approved_at', $params, $types).' GROUP BY a.user_id';

        $result = [];
        foreach ($this->db->fetchAllAssociative($sql, $params, $types) as $row) {
            $result[(int) $row['id']] = ['done' => (int) $row['done'], 'withDue' => (int) $row['withDue'], 'onTime' => (int) $row['onTime']];
        }

        return $result;
    }

    /**
     * Koliko puta su zadaci radnika vraćeni na doradu u poslednjih $days dana.
     *
     * @param list<int>      $ids
     * @param list<int>|null $teamIds
     *
     * @return array<int, int>
     */
    private function returned(array $ids, ?array $teamIds, int $days): array
    {
        if ([] === $ids) {
            return [];
        }
        $params = ['ids' => $ids, 'since' => (new \DateTimeImmutable("-$days days"))->format('Y-m-d H:i:s')];
        $types = ['ids' => ArrayParameterType::INTEGER];
        $sql = 'SELECT a.user_id k, COUNT(DISTINCT c.id) n FROM task_comment c JOIN task_assignee a ON a.task_id = c.task_id '
            .'WHERE a.user_id IN (:ids) AND c.type = \'VRACANJE_NA_DORADU\' AND c.created_at >= :since'
            .$this->teamFilter($teamIds, 'c.created_at', $params, $types).' GROUP BY a.user_id';

        $result = [];
        foreach ($this->db->fetchAllAssociative($sql, $params, $types) as $row) {
            $result[(int) $row['k']] = (int) $row['n'];
        }

        return $result;
    }

    /**
     * @param list<int>|null       $teamIds
     * @param array<string, mixed> $params
     * @param array<string, mixed> $types
     */
    private function teamFilter(?array $teamIds, string $momentColumn, array &$params, array &$types): string
    {
        if (null === $teamIds) {
            return '';
        }
        $params['teamIds'] = $teamIds;
        $types['teamIds'] = ArrayParameterType::INTEGER;

        return ' AND EXISTS (SELECT 1 FROM team_member tm WHERE tm.user_id = a.user_id AND tm.team_id IN (:teamIds) '
            .'AND tm.joined_at <= '.$momentColumn.' AND (tm.left_at IS NULL OR tm.left_at > '.$momentColumn.'))';
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, int>
     */
    private function countBy(string $sql, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        $result = [];
        foreach ($this->db->fetchAllAssociative($sql, ['ids' => $ids], ['ids' => ArrayParameterType::INTEGER]) as $row) {
            $result[(int) $row['k']] = (int) $row['n'];
        }

        return $result;
    }

    private static function percent(int $part, int $whole): ?int
    {
        return 0 === $whole ? null : (int) round($part * 100 / $whole);
    }
}
