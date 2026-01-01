<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

require __DIR__ . '/../vendor/autoload.php';

// ---------------------------------------------------------
// Cargar .env SOLO si existe (en DO normalmente NO existe)
// ---------------------------------------------------------
$envPath = __DIR__ . '/..';
if (is_file($envPath . '/.env')) {
    $dotenv = Dotenv::createImmutable($envPath);
    $dotenv->load();
}

// ---------------------------------------------------------
// DEBUG por variable de entorno (temporal)
// APP_DEBUG=true -> muestra warnings/errores (solo mientras arreglas)
// ---------------------------------------------------------
$debug = (($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? 'false') === 'true');

if ($debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', 'php://stderr'); // para ver en Runtime Logs
    error_reporting(E_ALL);
}

// ---------------------------------------------------------
// Crear aplicación Slim
// ---------------------------------------------------------
$app = AppFactory::create();

// BasePath dinámico
$basePath = getenv('BASE_PATH') ?: '';
if ($basePath !== '') {
    $app->setBasePath($basePath);
}

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// ---------------------------------------------------------
// Error middleware (dev vs prod)
// ---------------------------------------------------------
$isDev = $debug || ((getenv('APP_ENV') ?: 'prod') !== 'prod');
$app->addErrorMiddleware($isDev, $isDev, $isDev);

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

    if (!$origin) return $response;

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
