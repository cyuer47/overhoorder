<?php
require_once __DIR__ . '/php/db.php';

if (empty($_SESSION['docent_id'])) {
    header('Location: login.php');
    exit;
}

$docent_id = $_SESSION['docent_id'];
$vraag_id = $_POST['vraag_id'] ?? null;

if (!$vraag_id) {
    die('Geen vraag opgegeven.');
}

// 🟣 Haal bijbehorende klas_id op via de vraag
$stmt = $pdo->prepare('SELECT klas_id FROM vragen WHERE id = ?');
$stmt->execute([$vraag_id]);
$klas_id = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT vragenlijst_id FROM vragen WHERE id = ?');
$stmt->execute([$vraag_id]);
$vragenlijst_id = $stmt->fetchColumn();

if (!$klas_id) {
    die('Vraag niet gevonden.');
}

// 🟣 Controleer of de docent eigenaar is van de klas
$stmt = $pdo->prepare('SELECT docent_id FROM klassen WHERE id = ?');
$stmt->execute([$klas_id]);
$owner_id = $stmt->fetchColumn();

if ($owner_id != $docent_id) {
    die('Geen rechten om deze vraag te verwijderen.');
}

// 🗑️ Verwijder de vraag
$stmt = $pdo->prepare('DELETE FROM vragen WHERE id = ?');
$stmt->execute([$vraag_id]);

header('Location: manage_vragenlijst.php?id=' . urlencode($vragenlijst_id));
exit;
