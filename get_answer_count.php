<?php
require_once __DIR__ . '/php/db.php';
session_start();

$sessie_id = $_GET['sessie_id'] ?? null;
if (!$sessie_id) {
    echo "0";
    exit;
}

// Haal huidige vraag van deze sessie op
$stmt = $pdo->prepare('SELECT current_question_id FROM sessies WHERE id = ?');
$stmt->execute([$sessie_id]);
$current_qid = $stmt->fetchColumn();

if (!$current_qid) {
    echo "0";
    exit;
}

// Tel antwoorden
$stmt = $pdo->prepare('SELECT COUNT(*) FROM resultaten WHERE sessie_id = ? AND vraag_id = ?');
$stmt->execute([$sessie_id, $current_qid]);
echo $stmt->fetchColumn();
