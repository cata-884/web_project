<?php
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = str_replace(BASE_URL, '', $uri);
$uri    = '/' . trim($uri, '/');
$method = strtolower($_SERVER['REQUEST_METHOD']);

// CORS — permite frontend-ul să faca fetch() din alta origine la dev
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

    // Campings API
    // /map TREBUIE să fie INAINTE de /(\d+) altfel ar matchui ca ID
    'get /api/campings/map'         => ['CampingsController', 'mapMarkers'],
    'get /api/campings'             => ['CampingsController', 'index'],
    'get /api/campings/(\d+)'       => ['CampingsController', 'show'],
    'post /api/campings'            => ['CampingsController', 'store'],
    'patch /api/campings/(\d+)'     => ['CampingsController', 'update'],
    'delete /api/campings/(\d+)'    => ['CampingsController', 'destroy'],

    // Reviews API
    'get /api/campings/(\d+)/reviews'   => ['ReviewsController', 'index'],
    'post /api/campings/(\d+)/reviews'  => ['ReviewsController', 'store'],
    'get /api/reviews/(\d+)'            => ['ReviewsController', 'show'],
    'patch /api/reviews/(\d+)'          => ['ReviewsController', 'update'],
    'delete /api/reviews/(\d+)'         => ['ReviewsController', 'destroy'],
    
    // Bookings API
    'get /api/bookings'                     => ['BookingsController', 'index'],
    'post /api/bookings'                    => ['BookingsController', 'store'],
    'get /api/bookings/(\d+)'               => ['BookingsController', 'show'],
    'patch /api/bookings/(\d+)'             => ['BookingsController', 'update'],
    'post /api/bookings/(\d+)/cancel'       => ['BookingsController', 'cancel'],
    'get /api/campings/(\d+)/availability'  => ['BookingsController', 'availability'],

    // Sections API (categorii personale de campinguri)
    'get /api/sections'                              => ['SectionsController', 'index'],
    'post /api/sections'                             => ['SectionsController', 'store'],
    'get /api/sections/(\d+)'                        => ['SectionsController', 'show'],
    'patch /api/sections/(\d+)'                      => ['SectionsController', 'update'],
    'delete /api/sections/(\d+)'                     => ['SectionsController', 'destroy'],
    'get /api/sections/(\d+)/campings'               => ['SectionsController', 'campings'],
    'post /api/sections/(\d+)/campings'              => ['SectionsController', 'addCamping'],
    'delete /api/sections/(\d+)/campings/(\d+)'      => ['SectionsController', 'removeCamping'],
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