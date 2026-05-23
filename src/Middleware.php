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
                    return self::forbidden('No tienes permiso para esta acción');
                }
            }

            $request = $request->withAttribute('user', $payload);

            return $handler->handle($request);
        };
    }

    public static function permission(string $requiredPermission): callable
    {
        return function (Request $request, Handler $handler) use ($requiredPermission): Response {
            $user = $request->getAttribute('user');

            if (!is_array($user)) {
                return self::unauthorized('Usuario no autenticado');
            }

            $role = $user['role'] ?? '';

            // El superadmin siempre tiene acceso total.
            if ($role === 'superadmin') {
                return $handler->handle($request);
            }

            $permissions = self::normalizePermissions($user['permissions'] ?? []);

            if (!self::hasPermission($permissions, $requiredPermission)) {
                return self::forbidden('No tienes permiso para esta acción');
            }

            return $handler->handle($request);
        };
    }

    private static function normalizePermissions($rawPermissions): array
    {
        if (is_array($rawPermissions)) {
            return array_values(array_filter(array_map('strval', $rawPermissions)));
        }

        if ($rawPermissions instanceof \stdClass) {
            return array_values(array_filter(array_map('strval', (array) $rawPermissions)));
        }

        if (is_string($rawPermissions)) {
            $rawPermissions = trim($rawPermissions);

            if ($rawPermissions === '') {
                return [];
            }

            $decoded = json_decode($rawPermissions, true);

            if (is_array($decoded)) {
                return array_values(array_filter(array_map('strval', $decoded)));
            }

            return array_values(array_filter(array_map('trim', explode(',', $rawPermissions))));
        }

        return [];
    }

    private static function hasPermission(array $permissions, string $requiredPermission): bool
    {
        // Permiso global
        if (in_array('*', $permissions, true)) {
            return true;
        }

        // Permiso exacto, por ejemplo: news.delete
        if (in_array($requiredPermission, $permissions, true)) {
            return true;
        }

        // Permiso por módulo, por ejemplo: news.*
        $parts = explode('.', $requiredPermission);

        if (count($parts) >= 2) {
            $moduleWildcard = $parts[0] . '.*';

            if (in_array($moduleWildcard, $permissions, true)) {
                return true;
            }
        }

        return false;
    }

    private static function unauthorized(string $message): Response
    {
        $response = new SlimResponse();

        $response->getBody()->write(json_encode([
            'error' => $message,
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }

    private static function forbidden(string $message): Response
    {
        $response = new SlimResponse();

        $response->getBody()->write(json_encode([
            'error' => $message,
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus(403)
            ->withHeader('Content-Type', 'application/json');
    }
}