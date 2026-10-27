<?php
// GET api/clients_search.php?q= → {ok, items: [...]}
require_once __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/clients.php';

$me = require_role('user');
$uid = (int)$me['id'];

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT id, client_type, name, short_name, pib, bpg, address, postal_code, city, email FROM clients WHERE user_id = ?';
$params = [$uid];
if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR short_name LIKE ? OR pib LIKE ? OR bpg LIKE ? OR city LIKE ?)';
    array_push($params, ...array_fill(0, 5, '%' . $q . '%'));
}
$sql .= ' ORDER BY name LIMIT 30';
$st = db()->prepare($sql);
$st->execute($params);

$items = array_map(fn($r) => [
    'id' => (int)$r['id'],
    'client_type' => $r['client_type'],
    'type_label' => client_type_label($r['client_type']),
    'name' => $r['name'],
    'short_name' => (string)$r['short_name'],
    'pib' => (string)$r['pib'],
    'bpg' => (string)$r['bpg'],
    'address' => client_address_line($r),
    'city' => (string)$r['city'],
    'email' => (string)$r['email'],
], $st->fetchAll());

json_response(['ok' => true, 'items' => $items]);
