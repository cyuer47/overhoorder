<?php
require_once __DIR__ . '/php/db.php';
if (empty($_SESSION['docent_id'])) { header('Location: login.php'); exit; }
$docent_id = $_SESSION['docent_id'];
$klas_id = $_GET['klas'] ?? null;
if (!$klas_id) die('Geen klas gekozen');

$stmt = $pdo->prepare('SELECT docent_id FROM klassen WHERE id = ?');
$stmt->execute([$klas_id]);
$owner_id = $stmt->fetchColumn();
if ($owner_id != $docent_id) die('Geen rechten om deze klas te verwijderen.');

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('DELETE FROM resultaten WHERE sessie_id IN (SELECT id FROM sessies WHERE klas_id = ?)');
    $stmt->execute([$klas_id]);
    $stmt = $pdo->prepare('DELETE FROM sessies WHERE klas_id = ?');
    $stmt->execute([$klas_id]);
    $stmt = $pdo->prepare('DELETE FROM leerlingen WHERE klas_id = ?');
    $stmt->execute([$klas_id]);
    $stmt = $pdo->prepare('DELETE FROM vragen WHERE klas_id = ?');
    $stmt->execute([$klas_id]);
    $stmt = $pdo->prepare('DELETE FROM klassen WHERE id = ? AND docent_id = ?');
    $stmt->execute([$klas_id, $docent_id]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die('Fout bij het verwijderen van de klas: ' . $e->getMessage());
}
header('Location: dashboard.php');
exit;