<?php
require_once __DIR__ . '/php/db.php';
session_start();
header('Content-Type: application/json');

$klas_id = $_SESSION['klas_id'] ?? null;
$leerling_id = $_SESSION['leerling_id'] ?? null;

$response = [
    'active_session' => false,
    'current_question_id' => null,
    'score' => null,
    'recent_answers' => []
];

if ($leerling_id && $klas_id) {
    // Check for active session
    $stmt = $pdo->prepare('SELECT * FROM sessies WHERE klas_id = ? AND actief = 1 LIMIT 1');
    $stmt->execute([$klas_id]);
    $s = $stmt->fetch();

    if ($s) {
        $response['active_session'] = true;
        $response['current_question_id'] = $s['current_question_id'];

        // Get student's current score
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(points), 0) as total_points FROM resultaten WHERE leerling_id = ?');
        $stmt->execute([$leerling_id]);
        $score_data = $stmt->fetch();
        $response['score'] = $score_data['total_points'];

        // Get recent results for this student
        $stmt = $pdo->prepare('
            SELECT r.*, v.vraag
            FROM resultaten r 
            JOIN vragen v ON v.id = r.vraag_id 
            WHERE r.leerling_id = ? AND r.sessie_id = ?
            ORDER BY r.created_at DESC 
            LIMIT 5
        ');
        $stmt->execute([$leerling_id, $s['id']]);
        $response['recent_answers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

echo json_encode($response);
?>