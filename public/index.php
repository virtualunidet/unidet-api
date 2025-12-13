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
$dotenv->safeLoad(); // No revienta si no existe .env

/* =========================
 * Slim
 * ========================= */
$app = AppFactory::create();
$app->addRoutingMiddleware();

/**
 * BASE_PATH
 * - Local XAMPP: /unidet-api/public
 * - Azure: sin rewrite -> usa /index.php
 */
$isAzure = getenv('WEBSITE_INSTANCE_ID') || getenv('WEBSITE_SITE_NAME');

if ($isAzure) {
    // Azure (sin rewrite): las rutas entran como /index.php/...
    $app->setBasePath('/index.php');
} else {
    // Local: puedes usar BASE_PATH si lo ocupas
    $basePath = (string)($_ENV['BASE_PATH'] ?? getenv('BASE_PATH') ?? '');
    $basePath = trim($basePath);
    $basePath = rtrim($basePath, '/'); // "/" -> ""

    if ($basePath !== '') {
        $app->setBasePath($basePath);
    }
}

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
$allowedOrigin = (string)($_ENV['ALLOWED_ORIGIN'] ?? getenv('ALLOWED_ORIGIN') ?? 'http://localhost:5173');

$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

$app->add(function (Request $request, RequestHandler $handler) use ($allowedOrigin): Response {
    // Preflight rápido
    if (strtoupper($request->getMethod()) === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
    } else {
        $response = $handler->handle($request);
    }

    return $response
        ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
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
