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

// Verify the student still exists and belongs to the class, and update last activity timestamp
try {
    $stmt = $pdo->prepare('SELECT id FROM leerlingen WHERE id = ? AND klas_id = ?');
    $stmt->execute([$leerling_id, $klas_id]);
    if (!$stmt->fetchColumn()) {
        echo json_encode(['removed' => true, 'message' => 'Leerling bestaat niet meer.']);
        exit;
    }
    // Mark as active via heartbeat (poll)
    $stmt = $pdo->prepare('UPDATE leerlingen SET last_seen = NOW() WHERE id = ?');
    $stmt->execute([$leerling_id]);

    // Get active session
    $stmt = $pdo->prepare('SELECT * FROM sessies WHERE klas_id = ? AND actief = 1 LIMIT 1');
    $stmt->execute([$klas_id]);
    $sessie = $stmt->fetch();

    if (!$sessie) {
        echo json_encode(['session_ended' => true]);
        exit;
    }

    // Get student's current score and answer count (limited to current session)
    $stmt = $pdo->prepare('
        SELECT COALESCE(SUM(points), 0) as total_points, COUNT(id) as answer_count
        FROM resultaten 
        WHERE leerling_id = ? AND sessie_id = ?
    ');
    $stmt->execute([$leerling_id, $sessie['id']]);
    $score_data = $stmt->fetch();

    // Get current question details
    $current_question_text = null;
    $already_answered = false;
    // New: tracking for reveal
    $all_answered = false;
    $correct_answer_to_reveal = null;
    
    if ($sessie['current_question_id']) {
        // Get question text and correct answer
        $stmt = $pdo->prepare('SELECT vraag, antwoord FROM vragen WHERE id = ?');
        $stmt->execute([$sessie['current_question_id']]);
        $qrow = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_question_text = $qrow ? $qrow['vraag'] : null;
        $correct_answer = $qrow ? trim($qrow['antwoord']) : null;
        
        // Check if student already answered this question
        $stmt = $pdo->prepare('SELECT id FROM resultaten WHERE sessie_id = ? AND leerling_id = ? AND vraag_id = ?');
        $stmt->execute([$sessie['id'], $leerling_id, $sessie['current_question_id']]);
        $already_answered = (bool)$stmt->fetch();

        // Determine if all present students have answered
        // Count expected respondents: active/non-active or recent heartbeat (<= 15s)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM leerlingen WHERE klas_id = ? AND (status IN ('actief','non-actief') OR (last_seen IS NOT NULL AND TIMESTAMPDIFF(SECOND, last_seen, NOW()) <= 15))");
        $stmt->execute([$klas_id]);
        $expected_total = (int)$stmt->fetchColumn();

        // Count distinct answers for this question in this session
        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT leerling_id) FROM resultaten WHERE sessie_id = ? AND vraag_id = ?');
        $stmt->execute([$sessie['id'], $sessie['current_question_id']]);
        $answered_count = (int)$stmt->fetchColumn();

        $all_answered = ($expected_total > 0 && $answered_count >= $expected_total);

        if ($all_answered && $correct_answer !== null) {
            $correct_answer_to_reveal = $correct_answer;
        }
    }

    // Get recent answers
    $stmt = $pdo->prepare('
        SELECT r.*, v.vraag as question, r.antwoord_given as answer, r.created_at
        FROM resultaten r 
        JOIN vragen v ON v.id = r.vraag_id 
        WHERE r.leerling_id = ? AND r.sessie_id = ?
        ORDER BY r.created_at DESC 
        LIMIT 5
    ');
    $stmt->execute([$leerling_id, $sessie['id']]);
    $recent_answers = $stmt->fetchAll();

    // Return all data
    echo json_encode([
        'session_id' => $sessie['id'],
        'current_question_id' => $sessie['current_question_id'],
        'question_text' => $current_question_text,
        'already_answered' => $already_answered,
        'all_answered' => $all_answered,
        // Only reveal the correct answer when all have responded
        'correct_answer' => $all_answered ? $correct_answer_to_reveal : null,
        'score' => $score_data['total_points'],
        'answer_count' => $score_data['answer_count'],
        'recent_answers' => $recent_answers
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>