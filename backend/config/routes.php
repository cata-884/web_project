<?php

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = str_replace(BASE_URL, '', $uri);
$uri    = '/' . trim($uri, '/');
$method = strtolower($_SERVER['REQUEST_METHOD']);




// CORS — permite frontend-ul sa faca fetch() din alta origine la dev
// Cross-Origin Resource Sharing
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($method === 'options') {
    http_response_code(204);
    exit();
}

$routes = [
    // Auth API
    'post /api/auth/register'  => ['AuthController', 'register'],
    'post /api/auth/login'     => ['AuthController', 'login'],
    'post /api/auth/logout'    => ['AuthController', 'logout'],
    'get /api/auth/me'         => ['AuthController', 'me'],
    'patch /api/users/me'      => ['AuthController', 'updateMe'],


    // Campings API
    // /map si /by-slug TREBUIE sa fie INAINTE de /(\d+) altfel ar matchui ca ID
    'get /api/campings/map'              => ['CampingsController', 'mapMarkers'],
    'get /api/campings/by-slug/([^/]+)' => ['CampingsController', 'showBySlug'],
    'get /api/campings'                  => ['CampingsController', 'index'],
    'get /api/campings/(\d+)'            => ['CampingsController', 'show'],
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

    // Organizer API (cereri de promovare)
    'post /api/organizers/apply'                     => ['OrganizersController', 'apply'],
    'get /api/organizers/my-application'             => ['OrganizersController', 'myApplication'],
    'get /api/organizers/pending'                    => ['OrganizersController', 'pending'],
    'post /api/organizers/(\d+)/approve'             => ['OrganizersController', 'approve'],
    'post /api/organizers/(\d+)/reject'              => ['OrganizersController', 'reject'],

    // Media API
    'post /api/campings/(\d+)/media'  => ['MediaController', 'uploadCampingMedia'],
    'post /api/reviews/(\d+)/media'   => ['MediaController', 'uploadReviewMedia'],
    'delete /api/media/camping/(\d+)' => ['MediaController', 'destroyCampingMedia'],
    'delete /api/media/review/(\d+)'  => ['MediaController', 'destroyReviewMedia'],

    // Contact API
    'post /api/contact'           => ['ContactController', 'store'],
  /*  'get /api/admin/messages'     => ['ContactController', 'index'],*/

    // Admin API (gestionare useri + ban-uri)
    'get /api/admin/users'                           => ['AdminController', 'listUsers'],
    'post /api/admin/users/(\d+)/ban'                => ['AdminController', 'banUser'],
    'post /api/admin/users/(\d+)/unban'              => ['AdminController', 'unbanUser'],
    'get /api/admin/users/(\d+)/bans'                => ['AdminController', 'userBans'],

    // Admin API (gestionare cereri camping)
    'get /api/admin/campings'                        => ['AdminController', 'listCampings'],
    'post /api/admin/campings/(\d+)/approve'         => ['AdminController', 'approveCamping'],
    'post /api/admin/campings/(\d+)/reject'          => ['AdminController', 'rejectCamping'],
    'post /api/admin/campings/(\d+)/reject-feedback' => ['AdminController', 'rejectCampingFeedback'],

    // Retrimitere cerere camping (user)
    'post /api/campings/(\d+)/resubmit'              => ['CampingsController', 'resubmit'],

    // Admin Stats API
    'get /api/admin/stats/summary'   => ['StatsController', 'summary'],
    'get /api/admin/stats/chart.svg' => ['StatsController', 'chartSvg'],
    'get /api/admin/stats/report.pdf'=> ['StatsController', 'reportPdf'],

    'get /api/admin/messages'              => ['AdminController', 'listMessages'],

    // Admin Export API
    'get /api/admin/export/campings.csv'  => ['ExportController', 'campingsCsv'],
    'get /api/admin/export/campings.json' => ['ExportController', 'campingsJson'],
    'get /api/admin/export/bookings.csv'  => ['ExportController', 'bookingsCsv'],
    'get /api/admin/export/bookings.json' => ['ExportController', 'bookingsJson'],
    'get /api/admin/export/reviews.csv'   => ['ExportController', 'reviewsCsv'],
    'get /api/admin/export/reviews.json'  => ['ExportController', 'reviewsJson'],
    'get /api/admin/export/users.csv'     => ['ExportController', 'usersCsv'],
    'get /api/admin/export/users.json'    => ['ExportController', 'usersJson'],

    // Preferences API
    'get /api/preferences'  => ['PreferencesController', 'get'],
    'post /api/preferences' => ['PreferencesController', 'save'],

    // Admin Import API
    'post /api/admin/import/campings'     => ['ImportController', 'campings'],
    // OAuth Google
    'get /api/auth/oauth/google'                     => ['AuthController', 'oauthGoogleStart'],
    'get /api/auth/oauth/google/callback'            => ['AuthController', 'oauthGoogleCallback'],
];

$matched = false;
foreach ($routes as $pattern => $handler) {
    [$routeMethod, $routePath] = explode(' ', $pattern, 2);
    if ($routeMethod !== $method) continue;

    /*
        $matches[0] va fi intregul text potrivit: '/api/campings/45'
        $matches[1] va fi doar bucata prinsa in paranteze (\d+), adica ID-ul: '45'
    */
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