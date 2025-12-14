<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

require __DIR__ . '/../vendor/autoload.php';

/* =========================
 * .env (solo local)
 * ========================= */
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

/* =========================
 * Detectar Azure
 * ========================= */
$isAzure = (bool)(getenv('WEBSITE_INSTANCE_ID') || getenv('WEBSITE_SITE_NAME'));

/* =========================================================
 * AZURE FALLBACK ROUTER (modo ?r=)
 * =========================================================
 * Permite llamar:
 *   /index.php?r=/ping
 *   /index.php?r=/news
 *   /index.php?r=/courses
 *
 * Importante: en modo ?r= forzamos que Slim "vea" la ruta como /news, /courses, etc.
 */
$usingQueryRouter = false;

if ($isAzure && isset($_GET['r']) && is_string($_GET['r']) && $_GET['r'] !== '') {
    $usingQueryRouter = true;

    $route = '/' . ltrim($_GET['r'], '/');   // "/courses"
    $_SERVER['REQUEST_URI'] = $route;        // Slim verá "/courses"
    $_SERVER['PATH_INFO']   = $route;

    // Opcional: limpia r para que no estorbe
    // unset($_GET['r']);
}

/* =========================
 * Slim
 * ========================= */
$app = AppFactory::create();

/**
 * BASE_PATH:
 * - Azure normal (URL /index.php/<ruta>): basePath = /index.php
 * - Azure con ?r= (forzamos REQUEST_URI=/ruta): basePath = "" (vacío)
 * - Local: BASE_PATH desde .env si estás en subcarpeta (XAMPP)
 */
if ($isAzure) {
    if (!$usingQueryRouter) {
        // Tu forma actual: /index.php/ping
        $app->setBasePath('/index.php');
    }
    // Si está usando ?r=, NO seteamos basePath (queda vacío)
} else {
    $basePath = (string)($_ENV['BASE_PATH'] ?? getenv('BASE_PATH') ?? '');
    $basePath = rtrim(trim($basePath), '/');
    if ($basePath !== '') {
        $app->setBasePath($basePath);
    }
}

$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

/* =========================
 * Errors
 * ========================= */
$appDebugRaw = (string)($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? 'false');
$appDebug = in_array(strtolower($appDebugRaw), ['1','true','yes','on'], true);
$app->addErrorMiddleware($appDebug, true, true);

/* =========================
 * CORS
 * ========================= */
$allowedOriginsEnv = (string)($_ENV['ALLOWED_ORIGINS'] ?? getenv('ALLOWED_ORIGINS') ?? 'http://localhost:5173');
$allowedOrigins = array_values(array_filter(array_map('trim', explode(',', $allowedOriginsEnv))));

$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

$app->add(function (Request $request, RequestHandler $handler) use ($allowedOrigins): Response {
    $origin = $request->getHeaderLine('Origin');

    $allowOrigin = $allowedOrigins[0] ?? 'http://localhost:5173';
    if ($origin && in_array($origin, $allowedOrigins, true)) {
        $allowOrigin = $origin;
    }

    if (strtoupper($request->getMethod()) === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
    } else {
        $response = $handler->handle($request);
    }

    return $response
        ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
        ->withHeader('Vary', 'Origin')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

/* =========================
 * Rutas
 * ========================= */
require __DIR__ . '/../src/Routes.php';

$app->run();
