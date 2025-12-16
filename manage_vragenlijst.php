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

$stmt = $pdo->prepare('SELECT v.*, k.docent_id, k.naam as klasnaam FROM vragenlijsten v JOIN klassen k ON v.klas_id = k.id WHERE v.id=?');
$stmt->execute([$id]);
$lijst = $stmt->fetch();
if(!$lijst || $lijst['docent_id'] != $docent_id) die('Geen rechten of lijst niet gevonden.');

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vraag'], $_POST['antwoord']) && !isset($_POST['edit_vraag_id'])) {
    $vraag = trim($_POST['vraag']);
    $antwoord = trim($_POST['antwoord']);
    if($vraag && $antwoord){
        $stmt = $pdo->prepare('INSERT INTO vragen (klas_id, vragenlijst_id, vraag, antwoord) VALUES (?, ?, ?, ?)');
        $stmt->execute([$lijst['klas_id'], $id, $vraag, $antwoord]);
    }
    header('Location: manage_vragenlijst.php?id='.$id);
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_vraag_id'])) {
    $vraag_id = (int)$_POST['edit_vraag_id'];
    $nieuwe_vraag = trim($_POST['edit_vraag']);
    $nieuw_antwoord = trim($_POST['edit_antwoord']);
    if($nieuwe_vraag && $nieuw_antwoord){
        $stmt = $pdo->prepare('UPDATE vragen SET vraag=?, antwoord=? WHERE id=? AND vragenlijst_id=?');
        $stmt->execute([$nieuwe_vraag, $nieuw_antwoord, $vraag_id, $id]);
    }
    header('Location: manage_vragenlijst.php?id='.$id);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM vragen WHERE vragenlijst_id = ? ORDER BY id DESC');
$stmt->execute([$id]);
$vragen = $stmt->fetchAll();
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>Vragenlijst beheren</title>
<link rel="stylesheet" href="assets/css/styles.css">
<link rel="icon" type="image/x-icon" href="https://overhoren.ivenboxem.nl/assets/img/logo2.png">
<style>
ul.vraag-lijst {
  list-style: none;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
ul.vraag-lijst li {
  background: var(--surface-container, #f8f8f8);
  padding: 10px 12px;
  border-radius: 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.vraag-info {
  display: flex;
  align-items: center;
  flex: 1;
  gap: 12px;
  flex-wrap: wrap;
}
.edit-input {
  border: none;
  background: transparent;
  font: inherit;
  outline: none;
  flex: 1;
  min-width: 200px;
}
.edit-input[name="edit_antwoord"] {
  color: gray;
}
.edit-btn, .save-btn, .delete-btn {
  all: unset;
  cursor: pointer;
  vertical-align: middle;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  border-radius: 8px;
}
.edit-btn:hover, .save-btn:hover, .delete-btn:hover {
  background: rgba(0,0,0,0.05);
}
.icon-group {
  display: flex;
  align-items: center;
  gap: 6px;
}
</style>
</head>
<body>
<div class="container">

  <a href="manage_klas.php?klas=<?= $lijst['klas_id'] ?>" class="btn-text">
    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="var(--svg-icons)">
      <path d="m368-417 202 202-90 89-354-354 354-354 90 89-202 202h466v126H368Z"/>
    </svg>
  </a>

  <h1 class="app-title"><?= htmlspecialchars($lijst['naam']) ?></h1>
  <div class="helper">Klas: <?= htmlspecialchars($lijst['klasnaam']) ?></div>

  <div class="card">
    <h3>Vraag toevoegen</h3>
    <form method="post">
      <label>Vraag</label>
      <input name="vraag" required>
      <label>Antwoord</label>
      <input name="antwoord" required>
      <button class="btn-primary" type="submit">Opslaan</button>
    </form>
  </div>

  <div style="height:16px"></div>

  <div class="card">
    <h3>Bestaande vragen</h3>
    <ul class="vraag-lijst">
      <?php if(empty($vragen)) echo '<p class="helper">Nog geen vragen in deze lijst.</p>'; ?>
      <?php foreach($vragen as $q): ?>
        <li>
          <div class="vraag-info">
            <form method="post" style="display:flex; flex:1; gap:10px; align-items:center;">
              <input type="hidden" name="edit_vraag_id" value="<?= $q['id'] ?>">
              <input type="text" name="edit_vraag" value="<?= htmlspecialchars($q['vraag']) ?>" class="edit-input" readonly>
              <input type="text" name="edit_antwoord" value="<?= htmlspecialchars($q['antwoord']) ?>" class="edit-input" readonly>
              
              <div class="icon-group">
                <button type="button" class="edit-btn" title="Bewerken" onclick="
                  const row = this.closest('form');
                  const inputs = row.querySelectorAll('.edit-input');
                  const saveBtn = row.querySelector('.save-btn');
                  inputs.forEach(i => i.removeAttribute('readonly'));
                  inputs[0].focus();
                  this.style.display='none';
                  saveBtn.style.display='inline-flex';
                ">
                  <svg xmlns='http://www.w3.org/2000/svg' height='22px' viewBox='0 -960 960 960' width='24px' fill='var(--on-primary-container)'><path d='M211-212h58l323-323-56-57-325 325v55ZM86-86v-234l526-526q14-14 31.5-21t36.5-7q18 0 36 7t33 21l98 96q14 14 21 32.5t7 37.5q0 19-7 37t-21 32L322-86H86Zm652-594-57-58 57 58ZM564-564l-28-28 56 57-28-29Z'/></svg>
                </button>

                <button type="submit" class="save-btn" title="Opslaan" style="display:none;">
                  <svg xmlns='http://www.w3.org/2000/svg' height='22px' viewBox='0 -960 960 960' width='22px' fill='var(--on-primary-container)'><path d='M874-694v482q0 53-36.5 89.5T748-86H212q-53 0-89.5-36.5T86-212v-536q0-53 36.5-89.5T212-874h482l180 180Zm-126 53L641-748H212v536h536v-429ZM480-252q50 0 85-35t35-85q0-50-35-85t-85-35q-50 0-85 35t-35 85q0 50 35 85t85 35ZM252-548h360v-160H252v160Zm-40-93v429-536 107Z'/></svg>
                </button>
              </div>
            </form>

            <form action="delete_vraag.php" method="post" onsubmit="return confirm('Weet je zeker dat je deze vraag wilt verwijderen?');">
              <input type="hidden" name="vraag_id" value="<?= $q['id'] ?>">
              <button type="submit" class="delete-btn" title="Verwijderen">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="var(--on-primary-container)">
                  <path d="M269-86q-53 0-89.5-36.5T143-212v-497H80v-126h257v-63h284v63h259v126h-63v497q0 53-36.5 89.5T691-86H269Zm422-623H269v497h422v-497ZM342-281h103v-360H342v360Zm173 0h103v-360H515v360ZM269-709v497-497Z"></path>
                </svg>
              </button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
</body>
</html>
