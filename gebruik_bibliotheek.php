<?php
session_start();
require_once __DIR__ . '/php/db.php';

if (empty($_SESSION['docent_id'])) {
    $current_page = basename($_SERVER['PHP_SELF']);
    header('Location: login.php?redirecturi=' . urlencode($current_page));
    exit;
}

$docent_id = $_SESSION['docent_id'];
$id = $_GET['id'] ?? null;

if(!$id) die('Geen lijst gekozen');

$stmt = $pdo->prepare('SELECT * FROM bibliotheek_vragenlijsten WHERE id=?');
$stmt->execute([$id]);
$bronlijst = $stmt->fetch();

if(!$bronlijst) die('Lijst niet gevonden.');

// ✅ Licentiecontrole
if (($bronlijst['licentie_type'] ?? 'gratis') !== 'gratis') {
    $mag_gebruiken = false;
    $stmt = $pdo->prepare('SELECT * FROM licenties WHERE docent_id = ? AND actief = 1 AND (vervalt_op IS NULL OR vervalt_op >= CURDATE())');
    $stmt->execute([$docent_id]);
    $licenties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($licenties as $lic) {
        if ($lic['type'] === $bronlijst['licentie_type'] || $lic['type'] === 'premium') {
            $mag_gebruiken = true;
            break;
        }
    }

    if (!$mag_gebruiken) {
        die('❌ Je hebt geen geldige licentie voor deze vragenlijst.');
    }
}

// ✅ Klas kiezen
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    $klassen_stmt = $pdo->prepare('SELECT id, naam FROM klassen WHERE docent_id = ?');
    $klassen_stmt->execute([$docent_id]);
    $klassen = $klassen_stmt->fetchAll();
    ?>
    <html lang="nl"><head><meta charset="utf-8"><title>Kies klas</title>
    <link rel="stylesheet" href="assets/css/styles.css"></head><body>
    <div class="container">
      <h1>Kies klas voor: <?= htmlspecialchars($bronlijst['naam']) ?></h1>
      <form method="post">
        <label>Klas</label>
        <select name="klas_id" required>
          <option value="">-- Kies klas --</option>
          <?php foreach($klassen as $k): ?>
            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['naam']) ?></option>
          <?php endforeach; ?>
        </select>
        <div style="padding:5px;"></div>
        <button class="btn-primary" type="submit">Toevoegen</button>
      </form>
    </div>
    </body></html>
    <?php
    exit;
}

$klas_id = (int)$_POST['klas_id'];

// ✅ Nieuwe vragenlijst aanmaken in de klas
$stmt = $pdo->prepare('INSERT INTO vragenlijsten (klas_id, naam, beschrijving) VALUES (?, ?, ?)');
$stmt->execute([$klas_id, $bronlijst['naam'], $bronlijst['beschrijving']]);
$nieuwe_lijst_id = $pdo->lastInsertId();

// ✅ Vragen kopiëren
$stmt = $pdo->prepare('SELECT vraag, antwoord FROM bibliotheek_vragen WHERE bibliotheek_lijst_id=?');
$stmt->execute([$id]);
$vragen = $stmt->fetchAll();

foreach($vragen as $v){
    $stmt2 = $pdo->prepare('INSERT INTO vragen (klas_id, vragenlijst_id, vraag, antwoord) VALUES (?, ?, ?, ?)');
    $stmt2->execute([$klas_id, $nieuwe_lijst_id, $v['vraag'], $v['antwoord']]);
}

header('Location: manage_klas.php?klas='.$klas_id);
exit;
?>
