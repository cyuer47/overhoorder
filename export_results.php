<?php
require_once __DIR__ . '/php/db.php';

session_start();

$sessie_id = $_GET['sessie'] ?? null;
$docent_id = $_SESSION['docent_id'] ?? null;

if (!$sessie_id || !$docent_id) {
    http_response_code(403);
    die('Toegang geweigerd.');
}

// Check of de docent eigenaar is van de sessie
$stmt = $pdo->prepare('SELECT docent_id FROM sessies WHERE id = ?');
$stmt->execute([$sessie_id]);
$sessie_docent_id = $stmt->fetchColumn();

if ($sessie_docent_id != $docent_id) {
    http_response_code(403);
    die('Toegang geweigerd. U bent geen eigenaar van deze sessie.');
}

// Haal alle resultaten op voor de sessie met de nieuwe status-kolom
$stmt = $pdo->prepare('
    SELECT 
        l.naam AS leerling_naam,
        v.vraag,
        r.antwoord_given AS gegeven_antwoord,
        r.status AS status,
        r.created_at AS datum_tijd
    FROM resultaten r
    JOIN leerlingen l ON l.id = r.leerling_id
    JOIN vragen v ON v.id = r.vraag_id
    WHERE r.sessie_id = ?
    ORDER BY r.created_at ASC
');
$stmt->execute([$sessie_id]);
$resultaten = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Zet HTTP headers voor CSV-download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=sessie_' . $sessie_id . '_resultaten.csv');

// Open een output stream
$output = fopen('php://output', 'w');

// Schrijf de CSV-headers
fputcsv($output, ['Leerling', 'Vraag', 'Gegeven Antwoord', 'Status', 'Datum en Tijd']);

// Schrijf de rijen
foreach ($resultaten as $rij) {
    fputcsv($output, $rij);
}

fclose($output);
exit();
?>