<?php
require_once __DIR__ . '/includes/db.php';

$name = 'Admin';
$email = 'admin@vestia.com';
$password = password_hash('admin123', PASSWORD_BCRYPT);

$stmt = db()->prepare('INSERT INTO admins (name, email, password) VALUES (?, ?, ?)');
$stmt->execute([$name, $email, $password]);

echo 'Admin created successfully!';
?>
