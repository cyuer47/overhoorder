<?php
require_once __DIR__ . '/php/db.php';

// Start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if this is an AJAX request
$is_ajax = isset($_POST['ajax']) && $_POST['ajax'] == '1';

if ($is_ajax) {
    header('Content-Type: application/json');
}

// Accept POST data for answer submission
$sessie_id = $_POST['sessie_id'] ?? null;
$vraag_id = $_POST['vraag_id'] ?? null;
$antwoord = trim($_POST['antwoord_leerling'] ?? $_POST['antwoord'] ?? '');
$leerling_id = $_POST['leerling_id'] ?? null;

if(!$sessie_id || !$vraag_id || !$antwoord) {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }
    die('Missing required fields.');
}

// If leerling_id not provided, get from session
if(!$leerling_id){
    $leerling_id = $_SESSION['leerling_id'] ?? null;
    if (!$leerling_id) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'No student session found.']);
            exit;
        }
        die('No student found for this submission.');
    }
}

// Ensure the student still exists (may have been removed)
try {
    $stmt = $pdo->prepare('SELECT id, klas_id FROM leerlingen WHERE id = ?');
    $stmt->execute([$leerling_id]);
    $leerling = $stmt->fetch();
    if (!$leerling) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'removed' => true, 'message' => 'Je bent verwijderd uit de sessie.']);
            exit;
        }
        die('Leerling niet gevonden.');
    }
} catch (Exception $e) {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'message' => 'Database fout: ' . $e->getMessage()]);
        exit;
    }
    die('Database error: ' . $e->getMessage());
}

try {
    // 1. Fetch the correct answer for the question
    $stmt = $pdo->prepare('SELECT antwoord FROM vragen WHERE id = ?');
    $stmt->execute([$vraag_id]);
    $correct_antwoord = trim($stmt->fetchColumn());
    
    // 2. Check if the submitted answer is an exact match (case-sensitive)
    if (strcmp($antwoord, $correct_antwoord) === 0) {
        $status = 'goed';
        $points = 10;
    } else {
        $status = 'onbekend';
        $points = 0;
    }

    // 3. Check if student already answered this question in this session
    $stmt = $pdo->prepare('SELECT id FROM resultaten WHERE sessie_id = ? AND leerling_id = ? AND vraag_id = ?');
    $stmt->execute([$sessie_id, $leerling_id, $vraag_id]);
    $existing = $stmt->fetch();

    if($existing) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'Je hebt deze vraag al beantwoord.']);
            exit;
        }
        // Update existing answer instead of creating duplicate
        $stmt = $pdo->prepare('UPDATE resultaten SET antwoord_given = ?, status = ?, points = ? WHERE id = ?');
        $stmt->execute([$antwoord, $status, $points, $existing['id']]);
    } else {
        // Create new answer record
        $stmt = $pdo->prepare('INSERT INTO resultaten (sessie_id, leerling_id, vraag_id, antwoord_given, status, points) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$sessie_id, $leerling_id, $vraag_id, $antwoord, $status, $points]);
    }

    if ($is_ajax) {
        echo json_encode(['success' => true, 'message' => 'Antwoord succesvol verstuurd.']);
        exit;
    }

} catch (Exception $e) {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'message' => 'Database fout: ' . $e->getMessage()]);
        exit;
    }
    die('Database error: ' . $e->getMessage());
}

// Non-AJAX redirect logic
if(isset($_POST['from_teacher']) || strpos($_SERVER['HTTP_REFERER'] ?? '', 'vraag_handler.php') !== false) {
    header('Location: vraag_handler.php?sessie=' . $sessie_id);
} else {
    // Check if this is version 2
    if(strpos($_SERVER['HTTP_REFERER'] ?? '', 'leerling_v2.php') !== false) {
        header('Location: leerling_v2.php');
    } else {
        header('Location: leerling.php');
    }
}
exit;
?>