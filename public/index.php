<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$app = AppFactory::create();

/* XAMPP */
$app->setBasePath('/unidet-api/public');

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$appDebugRaw = (string)($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? 'false');
$appDebug = in_array(strtolower($appDebugRaw), ['1','true','yes','on'], true);
$app->addErrorMiddleware($appDebug, true, true);

/* CORS */
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

    $response = strtoupper($request->getMethod()) === 'OPTIONS'
        ? new \Slim\Psr7\Response()
        : $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
        ->withHeader('Vary', 'Origin')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

require __DIR__ . '/../src/Routes.php';

$app->run();
