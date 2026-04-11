<?php
// ============================================================
// VESTIA API — Database Configuration
// ============================================================
error_reporting(0);
ini_set('display_errors', 0);

// ✅ Render يوفر هذه المتغيرات تلقائياً عند إنشاء قاعدة بيانات PostgreSQL
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'vestia_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Token expiry in seconds (30 days)
define('TOKEN_EXPIRY', 30 * 24 * 60 * 60);
// Shipping fee
define('SHIPPING_FEE', 80.00);

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // ✅ pgsql بدلاً من mysql
        $dsn = 'pgsql:host=' . DB_HOST . ';port=5432;dbname=' . DB_NAME;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // ✅ تعيين encoding بدلاً من charset في DSN
            $pdo->exec("SET client_encoding TO 'UTF8'");
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
        }
    }
    return $pdo;
}
