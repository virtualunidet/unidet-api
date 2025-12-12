<?php
declare(strict_types=1);

use Slim\App;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

use UnidetApi\Admission;
use UnidetApi\Auth;
use UnidetApi\Middleware;
use UnidetApi\DB;
use UnidetApi\News;
use UnidetApi\Event;
use UnidetApi\Course;
use UnidetApi\Service;
use UnidetApi\Regulation;
use UnidetApi\Contact;
use UnidetApi\Faq;


/** @var App $app */

/* =========================================================
 * Ping básico
 * =======================================================*/

$app->get('/ping', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode(['message' => 'pong']));
    return $response->withHeader('Content-Type', 'application/json');
});

/* =========================================================
 * Prueba de conexión a BD
 * =======================================================*/

$app->get('/db-test', function (Request $request, Response $response) {
    try {
        $pdo  = DB::getConnection();
        $stmt = $pdo->query('SELECT 1 AS ok');
        $row  = $stmt->fetch(\PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode([
            'db'  => 'ok',
            'row' => $row,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
        $response->getBody()->write(json_encode([
            'db'      => 'error',
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE));

        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }
});

/* =========================================================
 * Login
 * =======================================================*/

$app->post('/auth/login', function (Request $request, Response $response) {
    $data = (array) $request->getParsedBody();

    $email    = $data['email']    ?? '';
    $password = $data['password'] ?? '';

    $user = Auth::attemptLogin($email, $password);

    if (!$user) {
        $response->getBody()->write(json_encode([
            'error' => 'Credenciales inválidas'
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(401)
                        ->withHeader('Content-Type', 'application/json');
    }

    $token = Auth::generateToken([
        'sub'  => $user['id'],
        'name' => $user['name'],
        'role' => $user['role'],
    ]);

    $response->getBody()->write(json_encode([
        'token' => $token,
        'user'  => $user
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
});

// Login específico para admin (alias de /auth/login)
$app->post('/admin/login', function (Request $request, Response $response) {
    $data = (array) $request->getParsedBody();

    $email    = $data['email']    ?? '';
    $password = $data['password'] ?? '';

    $user = Auth::attemptLogin($email, $password);

    if (!$user) {
        $response->getBody()->write(json_encode([
            'error' => 'Credenciales inválidas'
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(401)
                        ->withHeader('Content-Type', 'application/json');
    }

    // Aquí podrías validar rol admin si quieres:
    // if (($user['role'] ?? '') !== 'admin') { ... }

    $token = Auth::generateToken([
        'sub'  => $user['id'],
        'name' => $user['name'],
        'role' => $user['role'],
    ]);

    $response->getBody()->write(json_encode([
        'token' => $token,
        'user'  => $user
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
});

/* =========================================================
 * Ruta protegida de prueba (admin)
 * =======================================================*/

$app->get('/admin/test', function (Request $request, Response $response) {
    $user = $request->getAttribute('user');

    $response->getBody()->write(json_encode([
        'message' => 'Hola admin',
        'user'    => $user
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

/* =========================================================
 * Noticias públicas
 * =======================================================*/

// GET /news
$app->get('/news', function (Request $request, Response $response) {
    $params = $request->getQueryParams();

    $page   = isset($params['page'])  ? max(1, (int) $params['page'])  : 1;
    $limit  = isset($params['limit']) ? max(1, (int) $params['limit']) : 20;
    $offset = ($page - 1) * $limit;

    $items = News::listPublic($limit, $offset);

    $response->getBody()->write(json_encode([
        'page'  => $page,
        'limit' => $limit,
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
});

// GET /news/{id}
$app->get('/news/{id}', function (Request $request, Response $response, array $args) {
    $id   = (int) $args['id'];
    $item = News::getById($id);

    if (!$item || (int) $item['visible'] !== 1) {
        $response->getBody()->write(json_encode([
            'error' => 'Noticia no encontrada',
        ], JSON_UNESCAPED_UNICODE));

        return $response->withStatus(404)
                        ->withHeader('Content-Type', 'application/json');
    }

    unset($item['visible']);

    $response->getBody()->write(json_encode($item, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});

/* =========================================================
 * Noticias admin
 * =======================================================*/

// GET /admin/news
$app->get('/admin/news', function (Request $request, Response $response) {
    $items = News::listAdmin();

    $response->getBody()->write(json_encode([
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// POST /admin/news
$app->post('/admin/news', function (Request $request, Response $response) {
    $user = $request->getAttribute('user');
    $data = (array) $request->getParsedBody();

    if (empty($data['titulo']) || empty($data['contenido'])) {
        $response->getBody()->write(json_encode([
            'error' => 'titulo y contenido son obligatorios',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $id = News::create($data, (int) $user['sub']);

    $response->getBody()->write(json_encode([
        'id'      => $id,
        'message' => 'Noticia creada',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withStatus(201)
                    ->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// PUT /admin/news/{id}
$app->put('/admin/news/{id}', function (Request $request, Response $response, array $args) {
    $id   = (int) $args['id'];
    $data = (array) $request->getParsedBody();

    if (empty($data['titulo']) || empty($data['contenido'])) {
        $response->getBody()->write(json_encode([
            'error' => 'titulo y contenido son obligatorios',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $ok = News::update($id, $data);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo actualizar la noticia',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Noticia actualizada',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// DELETE /admin/news/{id}
$app->delete('/admin/news/{id}', function (Request $request, Response $response, array $args) {
    $id = (int) $args['id'];

    $ok = News::delete($id);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo eliminar la noticia',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Noticia eliminada',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

/* =========================================================
 * Eventos públicos
 * =======================================================*/

// GET /events
$app->get('/events', function (Request $request, Response $response) {
    $params = $request->getQueryParams();

    $page   = isset($params['page'])  ? max(1, (int) $params['page'])  : 1;
    $limit  = isset($params['limit']) ? max(1, (int) $params['limit']) : 50;
    $offset = ($page - 1) * $limit;

    $items = Event::listPublic($limit, $offset);

    $response->getBody()->write(json_encode([
        'page'  => $page,
        'limit' => $limit,
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
});

// GET /events/{id}
$app->get('/events/{id}', function (Request $request, Response $response, array $args) {
    $id   = (int) $args['id'];
    $item = Event::getById($id);

    if (!$item || (int) $item['visible'] !== 1) {
        $response->getBody()->write(json_encode([
            'error' => 'Evento no encontrado',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(404)
                        ->withHeader('Content-Type', 'application/json');
    }

    unset($item['visible']);

    $response->getBody()->write(json_encode($item, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});

/* =========================================================
 * Eventos admin
 * =======================================================*/

// GET /admin/events
$app->get('/admin/events', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $page   = isset($params['page'])  ? max(1, (int) $params['page'])  : 1;
    $limit  = isset($params['limit']) ? max(1, (int) $params['limit']) : 100;
    $offset = ($page - 1) * $limit;

    $items = Event::listAdmin($limit, $offset);

    $response->getBody()->write(json_encode([
        'page'  => $page,
        'limit' => $limit,
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// POST /admin/events
$app->post('/admin/events', function (Request $request, Response $response) {
    $user = $request->getAttribute('user');
    $data = (array) $request->getParsedBody();

    if (empty($data['titulo']) || empty($data['fecha_inicio'])) {
        $response->getBody()->write(json_encode([
            'error' => 'titulo y fecha_inicio son obligatorios',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $id = Event::create($data, (int) $user['sub']);

    $response->getBody()->write(json_encode([
        'id'      => $id,
        'message' => 'Evento creado',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withStatus(201)
                    ->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// PUT /admin/events/{id}
$app->put('/admin/events/{id}', function (Request $request, Response $response, array $args) {
    $id   = (int) $args['id'];
    $data = (array) $request->getParsedBody();

    if (empty($data['titulo']) || empty($data['fecha_inicio'])) {
        $response->getBody()->write(json_encode([
            'error' => 'titulo y fecha_inicio son obligatorios',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $ok = Event::update($id, $data);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo actualizar el evento',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Evento actualizado',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// DELETE /admin/events/{id}
$app->delete('/admin/events/{id}', function (Request $request, Response $response, array $args) {
    $id = (int) $args['id'];

    $ok = Event::delete($id);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo eliminar el evento',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Evento eliminado',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

/* =========================================================
 * Cursos públicos (Oferta educativa)
 * =======================================================*/

$app->get('/courses', function (Request $request, Response $response) {
    $params = $request->getQueryParams();

    $page     = isset($params['page'])     ? max(1, (int) $params['page'])  : 1;
    $limit    = isset($params['limit'])    ? max(1, (int) $params['limit']) : 50;
    $category = isset($params['category']) ? (string) $params['category']   : null;

    $offset = ($page - 1) * $limit;

    // Coincide con la firma: (categoria, limit, offset)
    $items = Course::listPublic($category, $limit, $offset);

    $response->getBody()->write(json_encode([
        'page'     => $page,
        'limit'    => $limit,
        'category' => $category,
        'items'    => $items,
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
});

// GET /courses/{id}
$app->get('/courses/{id}', function (Request $request, Response $response, array $args) {
    $id   = (int) $args['id'];
    $item = Course::getById($id);

    if (!$item || (int) $item['visible'] !== 1) {
        $response->getBody()->write(json_encode([
            'error' => 'Curso no encontrado',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(404)
                        ->withHeader('Content-Type', 'application/json');
    }

    unset($item['visible']);

    $response->getBody()->write(json_encode($item, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});


/* =========================================================
 * Cursos admin
 * =======================================================*/

// GET /admin/courses
$app->get('/admin/courses', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $page   = isset($params['page'])  ? max(1, (int) $params['page'])  : 1;
    $limit  = isset($params['limit']) ? max(1, (int) $params['limit']) : 100;
    $offset = ($page - 1) * $limit;

    $items = Course::listAdmin($limit, $offset);

    $response->getBody()->write(json_encode([
        'page'  => $page,
        'limit' => $limit,
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// POST /admin/courses
$app->post('/admin/courses', function (Request $request, Response $response) {
    $user = $request->getAttribute('user');
    $data = (array) $request->getParsedBody();

    if (empty($data['titulo']) || empty($data['categoria'])) {
        $response->getBody()->write(json_encode([
            'error' => 'titulo y categoria son obligatorios',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    // Normalizar visible y orden (nunca menor a 0)
    $data['visible'] = isset($data['visible']) ? (int) !!$data['visible'] : 1;
    $data['orden']   = isset($data['orden'])   ? max(0, (int) $data['orden']) : 0;

    $id = Course::create($data, (int) $user['sub']);

    $response->getBody()->write(json_encode([
        'id'      => $id,
        'message' => 'Curso creado',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withStatus(201)
                    ->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// PUT /admin/courses/{id}
$app->put('/admin/courses/{id}', function (Request $request, Response $response, array $args) {
    $id   = (int) $args['id'];
    $data = (array) $request->getParsedBody();

    if (empty($data['titulo']) || empty($data['categoria'])) {
        $response->getBody()->write(json_encode([
            'error' => 'titulo y categoria son obligatorios',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    // Normalizar visible y orden (nunca menor a 0)
    $data['visible'] = isset($data['visible']) ? (int) !!$data['visible'] : 1;
    $data['orden']   = isset($data['orden'])   ? max(0, (int) $data['orden']) : 0;

    $ok = Course::update($id, $data);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo actualizar el curso',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Curso actualizado',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// DELETE /admin/courses/{id}
$app->delete('/admin/courses/{id}', function (Request $request, Response $response, array $args) {
    $id = (int) $args['id'];

    $ok = Course::delete($id);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo eliminar el curso',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Curso eliminado',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// POST /admin/courses/upload-image
$app->post('/admin/courses/upload-image', function (Request $request, Response $response) {
    // El front manda el archivo en el campo "image"
    $uploadedFiles = $request->getUploadedFiles();

    if (!isset($uploadedFiles['image'])) {
        $response->getBody()->write(json_encode([
            'error' => 'No se recibió ningún archivo (campo "image").',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $file = $uploadedFiles['image'];

    if ($file->getError() !== UPLOAD_ERR_OK) {
        $response->getBody()->write(json_encode([
            'error' => 'Error al subir el archivo.',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    // Validar tamaño (ej. máximo 5 MB)
    $size = $file->getSize();
    if ($size !== null && $size > 5 * 1024 * 1024) {
        $response->getBody()->write(json_encode([
            'error' => 'La imagen es demasiado grande (máx. 5 MB).',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    // Validar extensión
    $clientFilename = $file->getClientFilename() ?? 'imagen';
    $ext = strtolower(pathinfo($clientFilename, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($ext, $allowed, true)) {
        $response->getBody()->write(json_encode([
            'error' => 'Formato no permitido. Usa JPG, PNG o WEBP.',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    // Carpeta destino: /public/uploads/courses
    $baseDir    = dirname(__DIR__); // unidet-api/
    $uploadDir  = $baseDir . '/public/uploads/courses';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    // Nombre seguro
    $basename  = bin2hex(random_bytes(8));
    $filename  = $basename . '.' . $ext;
    $target    = $uploadDir . '/' . $filename;

    // Guardar archivo
    $file->moveTo($target);

    // Ruta pública (la que usará el frontend)
    $publicPath = '/uploads/courses/' . $filename;

    $response->getBody()->write(json_encode([
        'url'     => $publicPath,
        'message' => 'Imagen subida correctamente',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));


/* =========================================================
 * Servicios públicos
 * =======================================================*/

// GET /services
$app->get('/services', function (Request $request, Response $response) {
    // Lista de servicios visibles (sin paginación de momento)
    $items = Service::listPublic();

    $response->getBody()->write(json_encode([
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
});


/* =========================================================
 * Servicios admin
 * =======================================================*/

// GET /admin/services
$app->get('/admin/services', function (Request $request, Response $response) {
    $items = Service::listAdmin();

    $response->getBody()->write(json_encode([
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// POST /admin/services
$app->post('/admin/services', function (Request $request, Response $response) {
    $data = (array) $request->getParsedBody();

    if (empty($data['titulo'])) {
        $response->getBody()->write(json_encode([
            'error' => 'titulo es obligatorio',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    // Normalizar visible y orden
    $data['visible'] = isset($data['visible']) ? (int) !!$data['visible'] : 1;
    $data['orden']   = isset($data['orden'])   ? max(1, (int) $data['orden']) : 1;

    $id = Service::create($data);

    $response->getBody()->write(json_encode([
        'id'      => $id,
        'message' => 'Servicio creado',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withStatus(201)
                    ->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// PUT /admin/services/{id}
$app->put('/admin/services/{id}', function (Request $request, Response $response, array $args) {
    $id   = (int) $args['id'];
    $data = (array) $request->getParsedBody();

    if (empty($data['titulo'])) {
        $response->getBody()->write(json_encode([
            'error' => 'titulo es obligatorio',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $data['visible'] = isset($data['visible']) ? (int) !!$data['visible'] : 1;
    $data['orden']   = isset($data['orden'])   ? max(1, (int) $data['orden']) : 1;

    $ok = Service::update($id, $data);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo actualizar el servicio',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Servicio actualizado',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// DELETE /admin/services/{id}
$app->delete('/admin/services/{id}', function (Request $request, Response $response, array $args) {
    $id = (int) $args['id'];

    $ok = Service::delete($id);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo eliminar el servicio',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Servicio eliminado',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

/* =========================================================
 * Admisiones públicas (pasos del proceso)
 * =======================================================*/

// GET /admissions
$app->get('/admissions', function (Request $request, Response $response) {
    $params = $request->getQueryParams();

    $page   = isset($params['page'])  ? max(1, (int) $params['page'])  : 1;
    $limit  = isset($params['limit']) ? max(1, (int) $params['limit']) : 50;
    $offset = ($page - 1) * $limit;

    $items = Admission::listPublic($limit, $offset);

    $response->getBody()->write(json_encode([
        'page'  => $page,
        'limit' => $limit,
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
});

// GET /admissions/{id}
$app->get('/admissions/{id}', function (Request $request, Response $response, array $args) {
    $id   = (int) $args['id'];
    $item = Admission::getById($id);

    if (!$item || (int) $item['visible'] !== 1) {
        $response->getBody()->write(json_encode([
            'error' => 'Paso de admisión no encontrado',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(404)
                        ->withHeader('Content-Type', 'application/json');
    }

    unset($item['visible']);

    $response->getBody()->write(json_encode($item, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});


/* =========================================================
 * Admisiones admin
 * =======================================================*/

// GET /admin/admissions
$app->get('/admin/admissions', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $page   = isset($params['page'])  ? max(1, (int) $params['page'])  : 1;
    $limit  = isset($params['limit']) ? max(1, (int) $params['limit']) : 100;
    $offset = ($page - 1) * $limit;

    $items = Admission::listAdmin($limit, $offset);

    $response->getBody()->write(json_encode([
        'page'  => $page,
        'limit' => $limit,
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// POST /admin/admissions
$app->post('/admin/admissions', function (Request $request, Response $response) {
    $data = (array) $request->getParsedBody();

    if (empty($data['titulo'])) {
        $response->getBody()->write(json_encode([
            'error' => 'titulo es obligatorio',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $id = Admission::create($data);

    $response->getBody()->write(json_encode([
        'id'      => $id,
        'message' => 'Paso de admisión creado',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withStatus(201)
                    ->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// PUT /admin/admissions/{id}
$app->put('/admin/admissions/{id}', function (Request $request, Response $response, array $args) {
    $id   = (int) $args['id'];
    $data = (array) $request->getParsedBody();

    if (empty($data['titulo'])) {
        $response->getBody()->write(json_encode([
            'error' => 'titulo es obligatorio',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $ok = Admission::update($id, $data);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo actualizar el paso de admisión',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Paso de admisión actualizado',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// DELETE /admin/admissions/{id}
$app->delete('/admin/admissions/{id}', function (Request $request, Response $response, array $args) {
    $id = (int) $args['id'];

    $ok = Admission::delete($id);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo eliminar el paso de admisión',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Paso de admisión eliminado',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

/* =========================================================
 * Reglamento – público (texto + pdf + secciones)
 * =======================================================*/

// GET /regulation
$app->get('/regulation', function (Request $request, Response $response) {
    $regRow   = Regulation::getSingleton();
    $sections = Regulation::getPublicStructured();

    $data = [
        'content_html' => $regRow['content_html'] ?? '',
        'pdf_path'     => $regRow['pdf_path'] ?? null,
        'sections'     => $sections,
    ];

    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});


/* =========================================================
 * Reglamento – admin
 * =======================================================*/

// GET /admin/regulation
$app->get('/admin/regulation', function (Request $request, Response $response) {
    $regRow   = Regulation::getSingleton();
    $sections = Regulation::getAdminStructured();

    $data = [
        'content_html' => $regRow['content_html'] ?? '',
        'pdf_path'     => $regRow['pdf_path'] ?? null,
        'sections'     => $sections,
    ];

    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// PUT /admin/regulation (solo texto grande HTML)
$app->put('/admin/regulation', function (Request $request, Response $response) {
    $data = (array)$request->getParsedBody();
    $html = (string)($data['content_html'] ?? '');

    $ok = Regulation::updateContentHtml($html);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo guardar el contenido del reglamento',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Contenido actualizado',
    ], JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// POST /admin/regulation/upload-pdf
$app->post('/admin/regulation/upload-pdf', function (Request $request, Response $response) {
    $uploadedFiles = $request->getUploadedFiles();

    if (!isset($uploadedFiles['pdf'])) {
        $response->getBody()->write(json_encode([
            'error' => 'No se recibió ningún archivo (campo "pdf").',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $file = $uploadedFiles['pdf'];

    if ($file->getError() !== UPLOAD_ERR_OK) {
        $response->getBody()->write(json_encode([
            'error' => 'Error al subir el archivo.',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    // Validar extensión
    $clientFilename = $file->getClientFilename() ?? 'reglamento.pdf';
    $ext = strtolower(pathinfo($clientFilename, PATHINFO_EXTENSION));

    if ($ext !== 'pdf') {
        $response->getBody()->write(json_encode([
            'error' => 'Solo se permiten archivos PDF.',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $baseDir   = dirname(__DIR__); // unidet-api/
    $uploadDir = $baseDir . '/public/uploads/regulation';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $basename = bin2hex(random_bytes(8));
    $filename = $basename . '.pdf';
    $target   = $uploadDir . '/' . $filename;

    $file->moveTo($target);

    $publicPath = '/uploads/regulation/' . $filename;

    Regulation::updatePdfPath($publicPath);

    $response->getBody()->write(json_encode([
        'pdf_path' => $publicPath,
        'message'  => 'PDF subido correctamente',
    ], JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

/* ===== Secciones – CRUD ===== */

// POST /admin/regulation/sections
$app->post('/admin/regulation/sections', function (Request $request, Response $response) {
    $data = (array)$request->getParsedBody();

    if (empty($data['titulo'])) {
        $response->getBody()->write(json_encode([
            'error' => 'titulo es obligatorio',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $id = Regulation::createSection($data);

    $response->getBody()->write(json_encode([
        'id'      => $id,
        'message' => 'Sección creada',
    ], JSON_UNESCAPED_UNICODE));
    return $response->withStatus(201)
                    ->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// PUT /admin/regulation/sections/{id}
$app->put('/admin/regulation/sections/{id}', function (Request $request, Response $response, array $args) {
    $id   = (int)$args['id'];
    $data = (array)$request->getParsedBody();

    if (empty($data['titulo'])) {
        $response->getBody()->write(json_encode([
            'error' => 'titulo es obligatorio',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $ok = Regulation::updateSection($id, $data);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo actualizar la sección',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Sección actualizada',
    ], JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// DELETE /admin/regulation/sections/{id}
$app->delete('/admin/regulation/sections/{id}', function (Request $request, Response $response, array $args) {
    $id = (int)$args['id'];

    $ok = Regulation::deleteSection($id);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo eliminar la sección',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Sección eliminada',
    ], JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

/* ===== Items – CRUD ===== */

// POST /admin/regulation/items
$app->post('/admin/regulation/items', function (Request $request, Response $response) {
    $data = (array)$request->getParsedBody();

    if (empty($data['section_id']) || empty($data['contenido'])) {
        $response->getBody()->write(json_encode([
            'error' => 'section_id y contenido son obligatorios',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $id = Regulation::createItem($data);

    $response->getBody()->write(json_encode([
        'id'      => $id,
        'message' => 'Punto creado',
    ], JSON_UNESCAPED_UNICODE));
    return $response->withStatus(201)
                    ->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// PUT /admin/regulation/items/{id}
$app->put('/admin/regulation/items/{id}', function (Request $request, Response $response, array $args) {
    $id   = (int)$args['id'];
    $data = (array)$request->getParsedBody();

    if (empty($data['section_id']) || empty($data['contenido'])) {
        $response->getBody()->write(json_encode([
            'error' => 'section_id y contenido son obligatorios',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $ok = Regulation::updateItem($id, $data);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo actualizar el punto',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Punto actualizado',
    ], JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// DELETE /admin/regulation/items/{id}
$app->delete('/admin/regulation/items/{id}', function (Request $request, Response $response, array $args) {
    $id = (int)$args['id'];

    $ok = Regulation::deleteItem($id);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo eliminar el punto',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Punto eliminado',
    ], JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

/* =========================================================
 * Contacto – público
 * =======================================================*/

// GET /contact
$app->get('/contact', function (Request $request, Response $response) {
    // Usamos la clase Contact (contact_settings con JSON)
    $data = Contact::getPublic();

    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});


/* =========================================================
 * Contacto – admin
 * =======================================================*/

// GET /admin/contact
$app->get('/admin/contact', function (Request $request, Response $response) {
    $data = Contact::getAdmin();

    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// PUT /admin/contact  (texto, phones/emails/socials en JSON)
$app->put('/admin/contact', function (Request $request, Response $response) {
    $data = (array)$request->getParsedBody();

    $ok = Contact::update($data);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudieron guardar los datos de contacto',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Datos guardados correctamente',
    ], JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// POST /admin/contact/upload-image  (imagen principal)
$app->post('/admin/contact/upload-image', function (Request $request, Response $response) {
    $uploadedFiles = $request->getUploadedFiles();

    if (!isset($uploadedFiles['image'])) {
        $response->getBody()->write(json_encode([
            'error' => 'No se recibió ningún archivo (campo "image").',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $file = $uploadedFiles['image'];

    if ($file->getError() !== UPLOAD_ERR_OK) {
        $response->getBody()->write(json_encode([
            'error' => 'Error al subir el archivo.',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    // tamaño máx 5 MB
    $size = $file->getSize();
    if ($size !== null && $size > 5 * 1024 * 1024) {
        $response->getBody()->write(json_encode([
            'error' => 'La imagen es demasiado grande (máx. 5 MB).',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    // extensión permitida
    $clientFilename = $file->getClientFilename() ?? 'contact.jpg';
    $ext = strtolower(pathinfo($clientFilename, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($ext, $allowed, true)) {
        $response->getBody()->write(json_encode([
            'error' => 'Formato no permitido. Usa JPG, PNG o WEBP.',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $baseDir   = dirname(__DIR__); // unidet-api/
    $uploadDir = $baseDir . '/public/uploads/contact';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $basename = bin2hex(random_bytes(8));
    $filename = $basename . '.' . $ext;
    $target   = $uploadDir . '/' . $filename;

    $file->moveTo($target);

    $publicPath = '/uploads/contact/' . $filename;

    Contact::updateImage($publicPath);

    $response->getBody()->write(json_encode([
        'image_url' => $publicPath,
        'message'   => 'Imagen subida correctamente',
    ], JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

/* =========================================================
 * FAQ – público
 * =======================================================*/

// GET /faq
$app->get('/faq', function (Request $request, Response $response) {
    $items = Faq::listPublic();

    $response->getBody()->write(json_encode([
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
});


/* =========================================================
 * FAQ – admin
 * =======================================================*/

// GET /admin/faq
$app->get('/admin/faq', function (Request $request, Response $response) {
    $items = Faq::listAdmin();

    $response->getBody()->write(json_encode([
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// POST /admin/faq
$app->post('/admin/faq', function (Request $request, Response $response) {
    $data = (array)$request->getParsedBody();

    if (empty($data['pregunta'])) {
        $response->getBody()->write(json_encode([
            'error' => 'pregunta es obligatoria',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $id = Faq::create($data);

    $response->getBody()->write(json_encode([
        'id'      => $id,
        'message' => 'Pregunta creada',
    ], JSON_UNESCAPED_UNICODE));
    return $response->withStatus(201)
                    ->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

// PUT /admin/faq/{id}
$app->put('/admin/faq/{id}', function (Request $request, Response $response, array $args) {
    $id   = (int)$args['id'];
    $data = (array)$request->getParsedBody();

    if (empty($data['pregunta'])) {
        $response->getBody()->write(json_encode([
            'error' => 'pregunta es obligatoria',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $ok = Faq::update($id, $data);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo actualizar la pregunta',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Pregunta actualizada',
    ], JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));


// DELETE /admin/faq/{id}
$app->delete('/admin/faq/{id}', function (Request $request, Response $response, array $args) {
    $id = (int)$args['id'];

    $ok = Faq::delete($id);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo eliminar la pregunta',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Pregunta eliminada',
    ], JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['admin', 'superadmin']));

/* =========================================================
 * Gestión de admins (solo superadmin)
 * =======================================================*/

// GET /admin/users  -> lista admins y superadmins
$app->get('/admin/users', function (Request $request, Response $response) {
    $pdo = DB::getConnection();

    $sql = "SELECT 
                id,
                nombre AS name,
                email,
                role,
                is_active,
                email_verified_at
            FROM users
            WHERE role IN ('admin', 'superadmin')
            ORDER BY CASE WHEN role = 'superadmin' THEN 0 ELSE 1 END,
                     nombre ASC";

    $stmt  = $pdo->query($sql);
    $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    // Normalizamos tipos para el frontend
    foreach ($items as &$item) {
        $item['is_active'] = (int)$item['is_active'];              // 0 ó 1 numérico
        $item['verified']  = $item['email_verified_at'] !== null;  // bool
    }
    unset($item);

    $response->getBody()->write(json_encode([
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['superadmin']));


// POST /admin/users  -> crea un nuevo admin
$app->post('/admin/users', function (Request $request, Response $response) {
    $data = (array)$request->getParsedBody();

    $name     = trim((string)($data['name'] ?? ''));
    $email    = trim((string)($data['email'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $role     = $data['role'] ?? 'admin'; // solo admin o superadmin

    if ($name === '' || $email === '' || $password === '') {
        $response->getBody()->write(json_encode([
            'error' => 'name, email y password son obligatorios',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response->getBody()->write(json_encode([
            'error' => 'email no es válido',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    // Solo permitimos 'admin' o 'superadmin'
    if (!in_array($role, ['admin', 'superadmin'], true)) {
        $role = 'admin';
    }

    $pdo = DB::getConnection();

    // ¿ya existe ese correo?
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        $response->getBody()->write(json_encode([
            'error' => 'Ya existe un usuario con ese email',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(409)
                        ->withHeader('Content-Type', 'application/json');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (nombre, email, password_hash, role, is_active, created_at)
            VALUES (:nombre, :email, :password_hash, :role, 1, SYSDATETIME())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nombre'        => $name,
        ':email'         => $email,
        ':password_hash' => $passwordHash,
        ':role'          => $role,
    ]);

    $id = (int)$pdo->lastInsertId();

    $response->getBody()->write(json_encode([
        'id'      => $id,
        'message' => 'Admin creado correctamente',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withStatus(201)
                    ->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['superadmin']));


// PUT /admin/users/{id}  -> cambiar rol, activo y verificado
$app->put('/admin/users/{id}', function (Request $request, Response $response, array $args) {
    $id   = (int)$args['id'];
    $data = (array)$request->getParsedBody();

    $pdo = DB::getConnection();

    $fields = [];
    $params = [':id' => $id];

    // ----- is_active -----
    if (array_key_exists('is_active', $data)) {
        $isActive = (int)!!$data['is_active'];

        // No permitir desactivar al superadmin principal
        if ($id === 1 && $isActive === 0) {
            $response->getBody()->write(json_encode([
                'error' => 'No puedes desactivar al superadmin principal',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)
                            ->withHeader('Content-Type', 'application/json');
        }

        $fields[]             = 'is_active = :is_active';
        $params[':is_active'] = $isActive;
    }

    // ----- role -----
    if (isset($data['role'])) {
        $role = (string)$data['role'];

        if (!in_array($role, ['admin', 'superadmin'], true)) {
            $response->getBody()->write(json_encode([
                'error' => 'role debe ser admin o superadmin',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)
                            ->withHeader('Content-Type', 'application/json');
        }

        // No permitir quitar el rol superadmin al usuario principal
        if ($id === 1 && $role !== 'superadmin') {
            $response->getBody()->write(json_encode([
                'error' => 'No puedes quitar el rol superadmin al usuario principal',
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)
                            ->withHeader('Content-Type', 'application/json');
        }

        $fields[]        = 'role = :role';
        $params[':role'] = $role;
    }

    // ----- verified (email_verified_at) -----
    if (array_key_exists('verified', $data)) {
        $verified = (bool)$data['verified'];

        if ($verified) {
            $fields[] = 'email_verified_at = SYSDATETIME()';
        } else {
            $fields[] = 'email_verified_at = NULL';
        }
    }

    if (!$fields) {
        $response->getBody()->write(json_encode([
            'message' => 'Nada que actualizar',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    $sql = "UPDATE users
            SET " . implode(', ', $fields) . "
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $ok   = $stmt->execute($params);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo actualizar el usuario',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Usuario actualizado',
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['superadmin']));


// POST /admin/users/{id}/reset-password
// Solo superadmin (incluyendo el admin principal) puede cambiar la contraseña de otro usuario
$app->post('/admin/users/{id}/reset-password', function (Request $request, Response $response, array $args) {
    $targetId = (int)$args['id']; // usuario al que le vamos a cambiar la contraseña

    // Usuario autenticado (quien está haciendo la petición)
    $authUser    = $request->getAttribute('user');
    $currentId   = (int)($authUser['sub']  ?? 0);
    $currentRole = (string)($authUser['role'] ?? '');

    // Por seguridad extra, aunque el middleware ya revisa el rol
    if ($currentRole !== 'superadmin') {
        $response->getBody()->write(json_encode([
            'error' => 'Solo un superadmin puede cambiar contraseñas de otros usuarios',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(403)
                        ->withHeader('Content-Type', 'application/json');
    }

    $data        = (array)$request->getParsedBody();
    $newPassword = (string)($data['new_password'] ?? '');

    if ($newPassword === '') {
        $response->getBody()->write(json_encode([
            'error' => 'new_password es obligatorio',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $pdo = DB::getConnection();

    // Verificar que el usuario exista y sea admin/superadmin
    $stmt = $pdo->prepare("
        SELECT id, email, role
        FROM users
        WHERE id = :id
          AND role IN ('admin', 'superadmin')
    ");
    $stmt->execute([':id' => $targetId]);
    $userRow = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$userRow) {
        $response->getBody()->write(json_encode([
            'error' => 'Usuario no encontrado o sin rol de admin/superadmin',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(404)
                        ->withHeader('Content-Type', 'application/json');
    }

    // Regla especial: la contraseña del superadmin principal (id = 1)
    // solo la puede cambiar ÉL MISMO.
    if ($targetId === 1 && $currentId !== 1) {
        $response->getBody()->write(json_encode([
            'error' => 'Solo el superadmin principal puede cambiar su propia contraseña',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    // Generar nuevo hash
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        UPDATE users
        SET password_hash = :hash
        WHERE id = :id
    ");
    $ok = $stmt->execute([
        ':hash' => $newHash,
        ':id'   => $targetId,
    ]);

    if (!$ok) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo actualizar la contraseña',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message'       => 'Contraseña actualizada correctamente',
        'user_id'       => $targetId,
        'user_email'    => $userRow['email'],
        'temp_password' => $newPassword, // la contraseña que tú elegiste
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['superadmin']));


// DELETE /admin/users/{id}  -> eliminar admin (solo superadmin)
$app->delete('/admin/users/{id}', function (Request $request, Response $response, array $args) {
    $targetId = (int)$args['id'];

    $authUser  = $request->getAttribute('user');
    $currentId = (int)($authUser['sub'] ?? 0);

    // No borrar al superadmin principal
    if ($targetId === 1) {
        $response->getBody()->write(json_encode([
            'error' => 'No puedes eliminar al superadmin principal',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    // Opcional: no permitir que alguien se borre a sí mismo
    if ($targetId === $currentId) {
        $response->getBody()->write(json_encode([
            'error' => 'No puedes eliminar tu propia cuenta desde aquí',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)
                        ->withHeader('Content-Type', 'application/json');
    }

    $pdo = DB::getConnection();

    // Verificar que sea admin/superadmin
    $stmt = $pdo->prepare("
        SELECT id, email, role
        FROM users
        WHERE id = :id
          AND role IN ('admin', 'superadmin')
    ");
    $stmt->execute([':id' => $targetId]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$row) {
        $response->getBody()->write(json_encode([
            'error' => 'Usuario no encontrado o sin rol de admin/superadmin',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(404)
                        ->withHeader('Content-Type', 'application/json');
    }

    // Eliminar
    $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $ok      = $stmtDel->execute([':id' => $targetId]);

    if (!$ok || $stmtDel->rowCount() === 0) {
        $response->getBody()->write(json_encode([
            'error' => 'No se pudo eliminar el usuario',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message'    => 'Usuario eliminado correctamente',
        'user_id'    => $targetId,
        'user_email' => $row['email'],
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
})->add(Middleware::jwtAuth(['superadmin']));
