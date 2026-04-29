<?php
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = str_replace(BASE_URL, '', $uri);
$uri    = '/' . trim($uri, '/');
$method = strtolower($_SERVER['REQUEST_METHOD']);

// 'METHOD /pattern' => [Controller, metoda]
$routes = [
    // --- Public (pre-auth) ---
    'get /'              => ['HomeController',        'index'],
    'get /about'         => ['HomeController',        'about'],
    'get /contact'       => ['HomeController',        'contact'],
    'post /contact'      => ['HomeController',        'submitContact'],
    'get /reviews'       => ['HomeController',        'reviews'],

    // --- Auth ---
    'get /login'         => ['AuthController',        'loginForm'],
    'post /login'        => ['AuthController',        'login'],
    'get /register'      => ['AuthController',        'registerForm'],
    'post /register'     => ['AuthController',        'register'],
    'get /logout'        => ['AuthController',        'logout'],

    // --- Dashboard (Modulul Descopera) ---
    'get /dashboard'     => ['DashboardController',   'index'],

    // --- Plante ---
    'get /plants'        => ['PlantsController',      'index'],
    'get /plants/new'    => ['PlantsController',      'create'],
    'post /plants'       => ['PlantsController',      'store'],
    'get /plants/(\d+)'  => ['PlantsController',      'show'],
    'get /plants/(\d+)/edit'    => ['PlantsController', 'edit'],
    'post /plants/(\d+)'        => ['PlantsController', 'update'],
    'post /plants/(\d+)/delete' => ['PlantsController', 'destroy'],

    // Endpoint-uri Ajax pentru plante
    'get /api/plants/search'    => ['PlantsController', 'apiSearch'],
    'get /api/plants/external'  => ['PlantsController', 'apiExternal'], // Trefle/GBIF

    // --- Colectii (Spatiul de lucru) ---
    'get /collections'                 => ['CollectionsController', 'index'],
    'get /collections/new'             => ['CollectionsController', 'create'],
    'post /collections'                => ['CollectionsController', 'store'],
    'get /collections/(\d+)'           => ['CollectionsController', 'show'],
    'get /collections/(\d+)/edit'      => ['CollectionsController', 'edit'],
    'post /collections/(\d+)'          => ['CollectionsController', 'update'],
    'post /collections/(\d+)/delete'   => ['CollectionsController', 'destroy'],
    'post /collections/(\d+)/publish'  => ['CollectionsController', 'togglePublish'],

    // Membri si permisiuni (rol: editor / viewer)
    'get /collections/(\d+)/members'        => ['CollectionsController', 'members'],
    'post /collections/(\d+)/members'       => ['CollectionsController', 'addMember'],
    'post /collections/(\d+)/members/(\d+)/delete' => ['CollectionsController', 'removeMember'],

    // Import / Export
    'get /collections/(\d+)/export' => ['CollectionsController', 'export'], // ?format=json|xml
    'post /collections/(\d+)/import' => ['CollectionsController', 'import'],

    // --- Comunitate ---
    'get /community'                  => ['CommunityController', 'index'],
    'get /api/community/search'       => ['CommunityController', 'apiSearch'],
    'post /community/friends/(\d+)'   => ['CommunityController', 'addFriend'],
    'post /community/friends/(\d+)/accept' => ['CommunityController', 'acceptFriend'],
    'post /community/friends/(\d+)/delete' => ['CommunityController', 'removeFriend'],

    // --- Setari ---
    'get /settings'                  => ['SettingsController', 'index'],
    'post /settings/profile'         => ['SettingsController', 'updateProfile'],
    'post /settings/password'        => ['SettingsController', 'updatePassword'],
    'post /settings/preferences'     => ['SettingsController', 'updatePreferences'],
    'post /settings/account/delete'  => ['SettingsController', 'deleteAccount'],
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
    echo '<h1>404 - Not Found</h1>';
    echo '<p>URI: ' . htmlspecialchars($uri) . '</p>';
}
