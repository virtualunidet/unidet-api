<?php
declare(strict_types=1);

/**
 * Wrapper para soportar: /index.php?r=/news
 * Convierte r en PATH_INFO/REQUEST_URI y luego carga Slim (public/index.php)
 */
if (isset($_GET['r'])) {
    $r = (string) $_GET['r'];
    if ($r === '') { $r = '/'; }
    if ($r[0] !== '/') { $r = '/' . $r; }

    $qs = $_GET;
    unset($qs['r']);
    $query = http_build_query($qs);

    $_SERVER['PATH_INFO'] = $r;
    $_SERVER['ORIG_PATH_INFO'] = $r;
    $_SERVER['REQUEST_URI'] = $r . ($query ? ('?' . $query) : '');
    $_SERVER['QUERY_STRING'] = $query;

    $_SERVER['PHP_SELF'] = '/index.php' . $r;
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

require __DIR__ . '/public/index.php';
