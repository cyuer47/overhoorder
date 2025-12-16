<?php
// Voeg hier je databaseverbinding toe.
require_once __DIR__ . '/php/db.php';

// Start de sessie om toegang te krijgen tot de sessievariabelen
session_start();

// Controleer of de docent is ingelogd
if (empty($_SESSION['docent_id']) || empty($_SESSION['klas_id'])) {
    http_response_code(403);
    echo "Toegang geweigerd.";
    exit();
}

$klas_id = $_SESSION['klas_id'];

try {
    // Haal laatste activiteit op en bepaal status op basis van timestamp
    $stmt = $pdo->prepare("SELECT naam, last_seen, status FROM leerlingen WHERE klas_id = ? ORDER BY naam ASC");
    $stmt->execute([$klas_id]);
    $leerlingen = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $nu = new DateTimeImmutable('now');
    $actieve_leerlingen = [];
    $non_actieve_leerlingen = [];

    foreach ($leerlingen as $l) {
        // Bepaal primair op basis van status vanuit de browser (focus/blur/visibility)
        if ($l['status'] === 'actief') {
            $actieve_leerlingen[] = $l;
            continue;
        }
        if ($l['status'] === 'non-actief' || $l['status'] === 'tabblad_afgesloten') {
            $non_actieve_leerlingen[] = $l;
            continue;
        }
        // Fallback: als status ontbreekt, gebruik heartbeat (< = 5s)
        $is_actief = false;
        if (!empty($l['last_seen'])) {
            $laatste = new DateTimeImmutable($l['last_seen']);
            $diff = $nu->getTimestamp() - $laatste->getTimestamp();
            $is_actief = ($diff <= 5);
        }
        if ($is_actief) {
            $actieve_leerlingen[] = $l;
        } else {
            $non_actieve_leerlingen[] = $l;
        }
    }

    echo '<h4>Actief</h4>';
    if (!empty($actieve_leerlingen)) {
        echo '<ul>';
        foreach($actieve_leerlingen as $l) {
            echo '<li>' . htmlspecialchars($l['naam']) . ' <span style="color:green; font-weight: bold;">(Actief)</span></li>';
        }
        echo '</ul>';
    } else {
        echo '<p>Geen actieve leerlingen.</p>';
    }

    echo '<h4>Non-actief</h4>';
    if (!empty($non_actieve_leerlingen)) {
        echo '<ul>';
        foreach($non_actieve_leerlingen as $l) {
            // Toon extra duiding: non-actief door blur/hidden of tabblad afgesloten
            $suffix = ' (Non-actief)';
            if ($l['status'] === 'tabblad_afgesloten') {
                $suffix = ' (Tabblad afgesloten)';
            } elseif ($l['status'] === 'non-actief') {
                $suffix = ' (Non-actief)';
            } else {
                $suffix = ' (Geen recente activiteit)';
            }
            echo '<li>' . htmlspecialchars($l['naam']) . ' <span style="color:red; font-weight: bold;">' . $suffix . '</span></li>';
        }
        echo '</ul>';
    } else {
        echo '<p>Geen non-actieve leerlingen.</p>';
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo "Er is een fout opgetreden bij het ophalen van de leerlingenlijst.";
}