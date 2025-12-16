<?php
require_once __DIR__ . '/php/db.php';

header('Content-Type: application/json');

// Check if student is logged in
$leerling_id = $_SESSION['leerling_id'] ?? null;
$klas_id = $_SESSION['klas_id'] ?? null;

if (!$leerling_id || !$klas_id) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Get active session
$stmt = $pdo->prepare('SELECT * FROM sessies WHERE klas_id = ? AND actief = 1 LIMIT 1');
$stmt->execute([$klas_id]);
$sessie = $stmt->fetch();

if (!$sessie) {
    echo json_encode(['error' => 'No active session']);
    exit;
}

// Get student's current score
$stmt = $pdo->prepare('
    SELECT COALESCE(SUM(points), 0) as total_points 
    FROM resultaten 
    WHERE leerling_id = ?
');
$stmt->execute([$leerling_id]);
$score_data = $stmt->fetch();

// Return current question ID and score
echo json_encode([
    'question_id' => $sessie['current_question_id'],
    'score' => $score_data['total_points']
]);
?>