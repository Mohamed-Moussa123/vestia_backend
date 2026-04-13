<?php
// ============================================================
// VESTIA API — Main Router  (api/index.php)  ✅ النسخة المعدّلة
// ============================================================
error_reporting(0);
ini_set('display_errors', 0);

// ── CORS ──
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Bootstrap ──
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/helpers/auth.php';

// ── Controllers ──
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/ProductController.php';
require_once __DIR__ . '/controllers/CategoryController.php';
require_once __DIR__ . '/controllers/CartController.php';
require_once __DIR__ . '/controllers/SavedController.php';
require_once __DIR__ . '/controllers/OrderController.php';
require_once __DIR__ . '/controllers/ReviewController.php';
require_once __DIR__ . '/controllers/ProfileController.php';
require_once __DIR__ . '/controllers/TryOnController.php'; // ✅ أُضيف

// ── Route Parsing ──
$method    = $_SERVER['REQUEST_METHOD'];
$uri       = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$path      = '/' . trim(substr($uri, strlen($scriptDir)), '/');
$segments  = array_values(array_filter(explode('/', trim($path, '/'))));
$resource  = $segments[0] ?? '';
$id        = $segments[1] ?? null;
$sub       = $segments[2] ?? null;

// ── Route Table ──
match(true) {
    // AUTH
    $resource === 'register' && $method === 'POST' => AuthController::register(),
    $resource === 'login'    && $method === 'POST' => AuthController::login(),
    $resource === 'logout'   && $method === 'POST' => AuthController::logout(),

    // CATEGORIES
    $resource === 'categories' && $method === 'GET' => CategoryController::index(),

    // PRODUCTS
    $resource === 'products' && $method === 'GET' && $id === null                  => ProductController::index(),
    $resource === 'products' && $method === 'GET' && $id !== null && $sub === null => ProductController::show($id),

    // REVIEWS
    $resource === 'products' && $id !== null && $sub === 'reviews' && $method === 'GET'  => ReviewController::index($id),
    $resource === 'products' && $id !== null && $sub === 'reviews' && $method === 'POST' => ReviewController::store($id),

    // SAVED
    $resource === 'saved' && $method === 'GET'  => SavedController::index(),
    $resource === 'saved' && $method === 'POST' => SavedController::toggle(),

    // CART
    $resource === 'cart' && $method === 'GET'    => CartController::index(),
    $resource === 'cart' && $method === 'POST'   => CartController::add(),
    $resource === 'cart' && $method === 'PUT'    => CartController::update($id),
    $resource === 'cart' && $method === 'DELETE' => CartController::remove($id),

    // ORDERS
    $resource === 'orders' && $method === 'GET'  && $id === null => OrderController::index(),
    $resource === 'orders' && $method === 'GET'  && $id !== null => OrderController::show($id),
    $resource === 'orders' && $method === 'POST'                 => OrderController::store(),

    // PROFILE
    $resource === 'profile' && $method === 'GET' => ProfileController::show(),
    $resource === 'profile' && $method === 'PUT' => ProfileController::update(),

    // ✅ VIRTUAL TRY-ON
    $resource === 'virtual-tryon' && $method === 'POST' => TryOnController::generate(),

    // 404
    default => jsonError('Endpoint not found', 404),
};
