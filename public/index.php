<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

require __DIR__ . '/../vendor/autoload.php';

/* ===== .env solo local ===== */
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

/* ===== detectar Azure ===== */
$isAzure = (bool)(getenv('WEBSITE_INSTANCE_ID') || getenv('WEBSITE_SITE_NAME'));
if ($isAzure) {
    $reqUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $path   = (string)(parse_url($reqUri, PHP_URL_PATH) ?? '');

    // 1) Soporte modo ?r=news (fallback)
    if (isset($_GET['r']) && is_string($_GET['r']) && trim($_GET['r']) !== '') {
        $route = '/' . ltrim(trim((string)$_GET['r']), '/');

        // Quitar r del query string para no ensuciar
        $rest = $_GET;
        unset($rest['r']);
        $qs = http_build_query($rest);

        $_SERVER['QUERY_STRING'] = $qs;
        $_SERVER['REQUEST_URI']  = $route . ($qs ? ('?' . $qs) : '');
        $_SERVER['PATH_INFO']    = $route;
        $_SERVER['PATH_TRANSLATED'] = $_SERVER['PATH_INFO'];
        $_SERVER['SCRIPT_NAME']  = '/index.php';
    }
    // 2) Soporte modo /index.php/news cuando Azure no manda PATH_INFO
    else {
        $prefix = '/index.php/';
        if (
            (empty($_SERVER['PATH_INFO']) || $_SERVER['PATH_INFO'] === '') &&
            strncmp($path, $prefix, strlen($prefix)) === 0
        ) {
            $_SERVER['PATH_INFO']   = substr($path, strlen('/index.php'));
            $_SERVER['SCRIPT_NAME'] = '/index.php';
        }
    }
}

/* ===== crear Slim ===== */
$app = AppFactory::create();

/* ===== basePath ===== */
if ($isAzure) {
    // En Azure SIEMPRE vas a pegarle como /index.php/...
    $app->setBasePath('/index.php');
} else {
    // Local (XAMPP) tu base real:
    $app->setBasePath('/unidet-api/public');
}

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

/* ===== errores (controlado por env) ===== */
$appDebugRaw = (string)($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? 'false');
$appDebug = in_array(strtolower($appDebugRaw), ['1','true','yes','on'], true);
$app->addErrorMiddleware($appDebug, true, true);

/* ===== CORS ===== */
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

/* ===== rutas ===== */
require __DIR__ . '/../src/Routes.php';

$app->run();
