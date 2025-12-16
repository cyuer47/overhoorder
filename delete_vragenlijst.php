<?php
require_once __DIR__ . '/php/db.php';

if (empty($_SESSION['docent_id'])) {
    header('Location: login.php');
    exit;
}

$docent_id = $_SESSION['docent_id'];
$vragenlijst_id = $_POST['vragenlijst_id'] ?? null;

if (!$vragenlijst_id) {
    die('Geen vragenlijst opgegeven.');
}

// 🟣 Haal klas_id van de vragenlijst op
$stmt = $pdo->prepare('SELECT klas_id FROM vragenlijsten WHERE id = ?');
$stmt->execute([$vragenlijst_id]);
$klas_id = $stmt->fetchColumn();

if (!$klas_id) {
    die('Vragenlijst niet gevonden.');
}

// 🟣 Controleer of de docent eigenaar is van de klas
$stmt = $pdo->prepare('SELECT docent_id FROM klassen WHERE id = ?');
$stmt->execute([$klas_id]);
$owner_id = $stmt->fetchColumn();

if ($owner_id != $docent_id) {
    die('Geen rechten om deze vragenlijst te verwijderen.');
}

// 🗑️ Verwijder eerst alle vragen die bij deze vragenlijst horen
$stmt = $pdo->prepare('DELETE FROM vragen WHERE vragenlijst_id = ?');
$stmt->execute([$vragenlijst_id]);

// 🗑️ Verwijder daarna de vragenlijst zelf
$stmt = $pdo->prepare('DELETE FROM vragenlijsten WHERE id = ?');
$stmt->execute([$vragenlijst_id]);

header('Location: manage_klas.php?klas=' . urlencode($klas_id));
exit;
