<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

require __DIR__ . '/../vendor/autoload.php';

// ---------------------------------------------------------
// Cargar variables de entorno (.env)
// ---------------------------------------------------------
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// ---------------------------------------------------------
// Crear aplicación Slim
// ---------------------------------------------------------
$app = AppFactory::create();

// Base path para XAMPP (carpeta /unidet-api/public)
$app->setBasePath('/unidet-api/public');

// Middleware para parsear el body (POST, PUT, etc.)
// MUY IMPORTANTE para que getParsedBody() funcione.
$app->addBodyParsingMiddleware();

// Middleware de errores (en dev lo dejamos en true, true, true)
$app->addErrorMiddleware(true, true, true);

// ---------------------------------------------------------
// CORS para permitir llamadas desde React (http://localhost:5173)
// ---------------------------------------------------------

// Responder peticiones OPTIONS (preflight)
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

// Middleware que agrega los headers CORS
$app->add(function (Request $request, RequestHandler $handler): Response {
    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
        ->withHeader(
            'Access-Control-Allow-Headers',
            'X-Requested-With, Content-Type, Accept, Origin, Authorization'
        )
        ->withHeader(
            'Access-Control-Allow-Methods',
            'GET, POST, PUT, PATCH, DELETE, OPTIONS'
        )
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

// ---------------------------------------------------------
// Incluir rutas de la API
// ---------------------------------------------------------
require __DIR__ . '/../src/Routes.php';

// Ejecutar la app
$app->run();
