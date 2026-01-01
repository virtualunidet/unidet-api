<?php
declare(strict_types=1);

namespace UnidetApi;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;

class Middleware
{
    public static function jwtAuth(array $allowedRoles = []): callable
    {
        return function (Request $request, Handler $handler) use ($allowedRoles): Response {
            $authHeader = $request->getHeaderLine('Authorization');

            if (!str_starts_with($authHeader, 'Bearer ')) {
                return self::unauthorized('Token faltante o inválido');
            }

            $token = substr($authHeader, 7);

            try {
                $payload = Auth::validateToken($token);
            } catch (\Throwable $e) {
                return self::unauthorized('Token inválido');
            }

            if (!empty($allowedRoles)) {
                $role = $payload['role'] ?? null;
                if (!in_array($role, $allowedRoles, true)) {
                    return self::unauthorized('No tienes permiso para esta acción');
                }
            }

            $request = $request->withAttribute('user', $payload);
            return $handler->handle($request);
        };
    }

    private static function unauthorized(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'error' => $message,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withStatus(401)
                        ->withHeader('Content-Type', 'application/json');
    }
}
