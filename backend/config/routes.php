<?php
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = str_replace(BASE_URL, '', $uri);
$uri    = '/' . trim($uri, '/');
$method = strtolower($_SERVER['REQUEST_METHOD']);

// CORS — permite frontend-ul să faca fetch() din altă origine la dev
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($method === 'options') {
    http_response_code(204);
    exit();
}

$routes = [
    // Auth API
    'post /api/auth/register' => ['AuthController', 'register'],
    'post /api/auth/login'    => ['AuthController', 'login'],
    'post /api/auth/logout'   => ['AuthController', 'logout'],
    'get /api/auth/me'        => ['AuthController', 'me'],

    // --- TODO: adăugăm pe rând în zilele următoare ---
    // campings, bookings, reviews, admin, stats, import/export
];

$matched = false;
foreach ($routes as $pattern => $handler) {
    [$routeMethod, $routePath] = explode(' ', $pattern, 2);
    if ($routeMethod !== $method) continue;

    if (preg_match('#^' . $routePath . '$#', $uri, $matches)) {
        [$class, $action] = $handler;
        $controller = new $class();
        $controller->$action(...array_slice($matches, 1));
        $matched = true;
        break;
    }
}

if (!$matched) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Endpoint inexistent', 'uri' => $uri]);
}