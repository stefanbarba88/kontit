<?php
// GET api/nbs.php?pib=123456789[&exclude_id=ID] → {ok, data} ili {ok:false, error[, existing]}
require_once __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/nbs.php';

$me = require_role('user');
$uid = (int)$me['id'];

$pib = trim($_GET['pib'] ?? '');
if (!preg_match('/^\d{9}$/', $pib)) {
    json_response(['ok' => false, 'error' => 'PIB mora imati tačno 9 cifara.'], 400);
}

// Klijent sa tim PIB-om već postoji? (pri izmeni se ne računa klijent koji se menja)
$st = db()->prepare('SELECT id, name FROM clients WHERE user_id = ? AND pib = ? AND id <> ?');
$st->execute([$uid, $pib, (int)($_GET['exclude_id'] ?? 0)]);
if ($existing = $st->fetch()) {
    json_response([
        'ok' => false,
        'error' => 'Klijent sa tim PIB-om već postoji.',
        'existing' => ['id' => (int)$existing['id'], 'name' => $existing['name'], 'url' => url('klijenti/form.php?id=' . $existing['id'])],
    ], 409);
}

try {
    $company = nbs_get_company($pib);
} catch (InvalidArgumentException $ex) {
    json_response(['ok' => false, 'error' => $ex->getMessage()], 400);
} catch (RuntimeException $ex) {
    json_response(['ok' => false, 'error' => $ex->getMessage()], 500);
} catch (Throwable $ex) {
    json_response(['ok' => false, 'error' => 'Greška pri pozivu NBS servisa.'], 502);
}

if ($company === null) {
    json_response(['ok' => false, 'error' => nbs_last_error() ?: 'Firma nije pronađena.'], 404);
}
json_response(['ok' => true, 'data' => nbs_map_client($company, $pib)]);
