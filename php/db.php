<?php
// php/db.php - database connection and session
$DB_HOST = 'localhost';
$DB_NAME = 'overhoorder';
$DB_USER = 'root';
$DB_PASS = '';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connectie mislukt: " . htmlspecialchars($e->getMessage()));
}

if (session_status() === PHP_SESSION_NONE) session_start();
?>