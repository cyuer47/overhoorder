<?php
require_once __DIR__ . '/php/db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$leerling_id = $_SESSION['leerling_id'] ?? null;
$klas_id = $_SESSION['klas_id'] ?? null;

if (!$leerling_id || !$klas_id) {
    echo json_encode(['success' => false, 'message' => 'Niet ingelogd.']);
    exit;
}

try {
    // Verify the student still exists and belongs to this class
    $stmt = $pdo->prepare('SELECT id FROM leerlingen WHERE id = ? AND klas_id = ?');
    $stmt->execute([$leerling_id, $klas_id]);
    if (!$stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'removed' => true, 'message' => 'Leerling bestaat niet meer.']);
        exit;
    }

    // Optionally allow a limited set of status values, but ignore arbitrary values
    $allowed = ['actief', 'non-actief', 'tabblad_afgesloten'];
    $raw_status = $_POST['status'] ?? null;

    if ($raw_status && in_array($raw_status, $allowed, true)) {
        // Alleen status updaten; heartbeat gebeurt in periodieke poll (get_student_state)
        $stmt = $pdo->prepare('UPDATE leerlingen SET status = ? WHERE id = ?');
        $stmt->execute([$raw_status, $leerling_id]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database fout: ' . $e->getMessage()]);
}
?>