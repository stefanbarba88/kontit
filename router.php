<?php

// Router za PHP ugrađeni server (dev). AssetMapper servira fajlove preko Symfony-ja,
// a bez ove skripte php -S vraća 404 za svaki URL koji ima ekstenziju.
// Pokretanje: php -S 127.0.0.1:8000 -t public router.php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file(__DIR__.'/public'.$path) && !str_ends_with($path, '.php')) {
    return false;
}

$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__.'/public/index.php';
