<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/php/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT id, naam, badge
        FROM docenten
        WHERE is_public = 1 AND naam LIKE :search
        ORDER BY naam
        LIMIT 10
    ");
    $stmt->execute(['search' => "%$q%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Fout bij zoeken',
        'msg' => $e->getMessage()
    ]);
}
