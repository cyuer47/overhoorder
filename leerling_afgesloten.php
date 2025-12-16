<?php
require_once __DIR__ . '/php/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$leerling_id = $_SESSION['leerling_id'] ?? null;
$klas_id = $_SESSION['klas_id'] ?? null;

if($leerling_id && $klas_id){
    // Mark the student tab as closed and update last activity timestamp
    $stmt = $pdo->prepare('UPDATE leerlingen SET status = "tabblad_afgesloten", last_seen = NOW() WHERE id = ? AND klas_id = ?');
    $stmt->execute([$leerling_id, $klas_id]);
}
?>