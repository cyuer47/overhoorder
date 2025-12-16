<?php
session_start();
require_once __DIR__ . '/php/db.php';

if (empty($_SESSION['docent_id'])) {
    header('Location: login.php');
    exit;
}

$sessie_id = $_GET['sessie'] ?? null;
if (!$sessie_id) {
    header('Location: dashboard.php');
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE sessies SET actief = 0, ended_at = NOW(), current_question_id = NULL WHERE id = ?');
    $stmt->execute([$sessie_id]);

    // Optionally, redirect to the dashboard with a success message
    header('Location: dashboard.php?status=sessie_gestopt');
    exit;

} catch (PDOException $e) {
    // Handle error, maybe redirect with an error message
    header('Location: dashboard.php?error=stop_failed');
    exit;
}
?>