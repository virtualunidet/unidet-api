<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

require __DIR__ . '/../vendor/autoload.php';

/**
 * ---------------------------------------------------------
 * DEBUG para DigitalOcean (captura errores en Runtime Logs)
 * Actívalo con: APP_DEBUG=true (env var)
 * ---------------------------------------------------------
 */
$debug = (getenv('APP_DEBUG') ?: 'false') === 'true';

if ($debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', 'php://stderr'); // DO captura stderr en Runtime Logs
    error_reporting(E_ALL);
}

// ---------------------------------------------------------
// Cargar .env SOLO si existe (en Azure normalmente NO existe)
// ---------------------------------------------------------
$envPath = __DIR__ . '/..';
if (is_file($envPath . '/.env')) {
    $dotenv = Dotenv::createImmutable($envPath);
    $dotenv->load();
}

// ---------------------------------------------------------
// Crear aplicación Slim
// ---------------------------------------------------------
$app = AppFactory::create();

// BasePath dinámico (local: /unidet-api/public, Azure: vacío)
$basePath = getenv('BASE_PATH') ?: '';
if ($basePath !== '') {
    $app->setBasePath($basePath);
}

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// ---------------------------------------------------------
// Error middleware (dev vs prod)
// APP_ENV=dev  -> muestra detalles
// APP_ENV=prod -> no muestra detalles
// ---------------------------------------------------------
$isDev = (getenv('APP_ENV') ?: 'prod') !== 'prod';

// 👇 si APP_DEBUG=true, forzamos que muestre detalles aunque APP_ENV sea prod
$showErrors = $isDev || $debug;

$app->addErrorMiddleware($showErrors, $showErrors, $showErrors);

// ---------------------------------------------------------
// CORS (permitir lista de orígenes por env)
// ---------------------------------------------------------
$allowedOrigins = array_filter(array_map('trim', explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: 'http://localhost:5173')));

$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response->withStatus(204);
});

$app->add(function (Request $request, RequestHandler $handler) use ($allowedOrigins): Response {
    $origin = $request->getHeaderLine('Origin');
    $response = $handler->handle($request);

    // Si no hay Origin (ej. Postman o same-origin), no forzamos CORS
    if (!$origin) return $response;

    // Solo reflejar si está en la allowlist
    if (in_array($origin, $allowedOrigins, true)) {
        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    }

    return $response;
});

// ---------------------------------------------------------
// Incluir rutas
// ---------------------------------------------------------
require __DIR__ . '/../src/Routes.php';

$app->run();
