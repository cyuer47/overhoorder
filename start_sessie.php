<?php
require_once __DIR__ . '/php/db.php';
if (empty($_SESSION['docent_id'])) {
    $current_page = basename($_SERVER['PHP_SELF']);
    header('Location: login.php?redirecturi=' . urlencode($current_page));
    exit;
}
$docent_id = $_SESSION['docent_id'];
$klas_id = $_GET['klas'] ?? null;

if (!$klas_id) die('Geen klas gekozen.');

// Controleer of de klas bij deze docent hoort
$stmt = $pdo->prepare('SELECT * FROM klassen WHERE id = ? AND docent_id = ?');
$stmt->execute([$klas_id, $docent_id]);
$klas = $stmt->fetch();
if (!$klas) die('Klas hoort niet bij jouw account.');

// Haal alle vragenlijsten van deze klas op
$stmt = $pdo->prepare('SELECT * FROM vragenlijsten WHERE klas_id = ? ORDER BY id DESC');
$stmt->execute([$klas_id]);
$vragenlijsten = $stmt->fetchAll();

// Als de docent een lijst heeft gekozen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vragenlijst_id'])) {
    $vragenlijst_id = $_POST['vragenlijst_id'];

    // Controleer of de vragenlijst bij de klas hoort
    $stmt = $pdo->prepare('SELECT * FROM vragenlijsten WHERE id = ? AND klas_id = ?');
    $stmt->execute([$vragenlijst_id, $klas_id]);
    $lijst = $stmt->fetch();
    if (!$lijst) die('Ongeldige vragenlijst.');

    // Stop alle oude sessies van deze klas
    $stmt = $pdo->prepare('UPDATE sessies SET actief = 0 WHERE klas_id = ?');
    $stmt->execute([$klas_id]);

    // Start nieuwe sessie
    $stmt = $pdo->prepare('
        INSERT INTO sessies 
        (klas_id, docent_id, vragenlijst_id, actief, started_at, round_seen, prev_student_id, current_student_id, current_question_id) 
        VALUES (?, ?, ?, 1, NOW(), JSON_ARRAY(), NULL, NULL, NULL)
    ');
    $stmt->execute([$klas_id, $docent_id, $vragenlijst_id]);
    $sessie_id = $pdo->lastInsertId();

    header('Location: vraag_handler.php?sessie=' . $sessie_id);
    exit;
}
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>Start overhoring</title>
<link rel="stylesheet" href="assets/css/styles.css">
<link rel="icon" type="image/x-icon" href="https://overhoren.ivenboxem.nl/assets/img/logo2.png">
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="btn-text">
    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="var(--svg-icons)">
      <path d="m368-417 202 202-90 89-354-354 354-354 90 89-202 202h466v126H368Z"/>
    </svg>
  </a>
  <h1 class="app-title">Start overhoring</h1>
  <p class="helper">Klas: <strong><?= htmlspecialchars($klas['naam']) ?></strong></p>

  <?php if (empty($vragenlijsten)): ?>
    <div class="card">
      <p class="helper">Deze klas heeft nog geen vragenlijsten. Maak eerst een vragenlijst aan via <strong>Beheer klas</strong>.</p>
      <a href="manage_klas.php?klas=<?= $klas_id ?>" class="btn-primary">Ga naar klasbeheer</a>
    </div>
  <?php else: ?>
    <div class="card">
      <h3>Kies een vragenlijst</h3>
      <form method="post">
        <label>Vragenlijst</label>
        <select name="vragenlijst_id" required>
          <option value="">Selecteer een vragenlijst...</option>
          <?php foreach ($vragenlijsten as $v): ?>
            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['naam']) ?></option>
          <?php endforeach; ?>
        </select>
        <div style="margin-top:12px;">
          <button class="btn-primary" type="submit">Start overhoring</button>
        </div>
      </form>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
