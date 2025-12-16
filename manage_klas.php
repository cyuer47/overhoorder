<?php
session_start(); 

require_once __DIR__ . '/php/db.php'; 

if (empty($_SESSION['docent_id'])) {
    $current_page = basename($_SERVER['PHP_SELF']);
    header('Location: login.php?redirecturi=' . urlencode($current_page));
    exit;
}

$docent_id = $_SESSION['docent_id'];
$klas_id = $_GET['klas'] ?? null;

if (!$klas_id) die('Geen klas gekozen');

$stmt = $pdo->prepare('SELECT * FROM klassen WHERE id = ? AND docent_id = ?');
$stmt->execute([$klas_id, $docent_id]);
$klas = $stmt->fetch();

if(!$klas) die('Klas niet gevonden of geen rechten');

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vragenlijst_naam'])) {
    $naam = trim($_POST['vragenlijst_naam']);
    if($naam){
        $stmt = $pdo->prepare('INSERT INTO vragenlijsten (klas_id, naam) VALUES (?, ?)');
        $stmt->execute([$klas_id, $naam]);
    }
    header('Location: manage_klas.php?klas='.$klas_id);
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_vragenlijst_id'], $_POST['edit_vragenlijst_naam'])) {
    $id = (int)$_POST['edit_vragenlijst_id'];
    $nieuwe_naam = trim($_POST['edit_vragenlijst_naam']);
    if($nieuwe_naam){
        $stmt = $pdo->prepare('UPDATE vragenlijsten SET naam=? WHERE id=? AND klas_id=?');
        $stmt->execute([$nieuwe_naam, $id, $klas_id]);
    }
    header('Location: manage_klas.php?klas='.$klas_id);
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all_students'])) {
    $stmt = $pdo->prepare('DELETE FROM leerlingen WHERE klas_id = ?');
    $stmt->execute([$klas_id]);
    header('Location: manage_klas.php?klas='.$klas_id);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM leerlingen WHERE klas_id=? ORDER BY id');
$stmt->execute([$klas_id]);
$leerlingen = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM vragenlijsten WHERE klas_id=? ORDER BY id DESC');
$stmt->execute([$klas_id]);
$vragenlijsten = $stmt->fetchAll();
?>

<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>Beheer klas</title>
<link rel="stylesheet" href="assets/css/styles.css">
<link rel="icon" type="image/x-icon" href="https://overhoren.ivenboxem.nl/assets/img/logo2.png">
<style>
ul.vragenlijst-lijst {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 0;
  margin-top: 10px;
}
ul.vragenlijst-lijst li {
  list-style: none;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.btn-danger {
  border: none;
  cursor: pointer;
}
.btn-primary {
  cursor: pointer;
}
a {
  text-decoration: none;
}

.edit-input {
  border: none;
  background: transparent;
  width: auto;
  font: inherit;
  padding: 4px 6px;
  border-radius: 8px;
  transition: background 0.2s;
}
.edit-input:focus {
  background: #f1f1f1;
  outline: 2px solid var(--md-sys-color-primary, #3b4fe4);
}
.edit-btn {
  background: none;
  border: none;
  cursor: pointer;
  margin-left: 6px;
  color: var(--on-primary-container, #333);
  border-radius: 50%;
  padding: 4px;
  transition: background 0.2s;
}
.edit-btn:hover {
  background: rgba(0,0,0,0.08);
}
.save-btn {
  display: none;
  background: var(--md-sys-color-primary, #3b4fe4);
  color: white;
  border: none;
  border-radius: 12px;
  padding: 4px 8px;
  cursor: pointer;
  margin-left: 6px;
}
.save-btn:hover {
  background: var(--md-sys-color-primary-container, #2c3ccf);
}
</style>
</head>
<body>
<div class="container">

  <a href="dashboard.php" class="btn-text">
    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="var(--svg-icons)">
      <path d="m368-417 202 202-90 89-354-354 354-354 90 89-202 202h466v126H368Z"/>
    </svg>
  </a>

  <h1 class="app-title">Beheer: <?=htmlspecialchars($klas['naam'])?></h1>

  <div class="card">
    <h3>Leerlingen</h3>
    <ul>
      <?php foreach($leerlingen as $l): ?>
        <li><?=htmlspecialchars($l['naam'])?></li>
      <?php endforeach; ?>
    </ul>
    <form method="post" onsubmit="return confirm('Alle leerlingen verwijderen?');">
      <button class="btn-danger" type="submit" name="delete_all_students">Verwijder alle leerlingen</button>
    </form>
  </div>

  <div style="height:16px"></div>

  <div class="card">
    <h3>Vragenlijsten</h3>
    <form method="post">
      <input name="vragenlijst_naam" placeholder="Naam vragenlijst" style="width: calc(100% - 110px); margin-bottom: 12px" required>
      <button class="btn-primary" type="submit" style="display: inline;">Aanmaken</button><br>
    </form>
    <ul class="vragenlijst-lijst">
      <?php if(empty($vragenlijsten)) echo '<p class="helper">Nog geen vragenlijsten.</p>'; ?>
<?php foreach($vragenlijsten as $v): ?>
  <li>
    <span class="btn-secondary" style="display:flex; align-items:center; justify-content:space-between; width:100%;">
      
      <div style="display:flex; align-items:center; gap:6px;">
        <a href="manage_vragenlijst.php?id=<?= $v['id'] ?>" style="color:inherit; text-decoration:none;">
          <?= htmlspecialchars($v['naam']) ?>
        </a>

        <form method="post" style="display:inline;">
          <input type="hidden" name="edit_vragenlijst_id" value="<?= $v['id'] ?>">
          <input type="text" name="edit_vragenlijst_naam"
                 value="<?= htmlspecialchars($v['naam']) ?>"
                 style="display:none; border:none; background:transparent; font:inherit; width:auto; outline:none;">
          
          <button type="button" title="Bewerken"
                  style="all:unset; cursor:pointer; vertical-align:middle;"
                  onclick="
                    const input=this.previousElementSibling;
                    const save=this.nextElementSibling;
                    input.style.display='inline-block';
                    input.focus();
                    this.style.display='none';
                    save.style.display='inline-block';
                  ">
            <svg xmlns='http://www.w3.org/2000/svg' height='22px' viewBox='0 -960 960 960' width='24p' fill='var(--on-primary-container)'><path d="M211-212h58l323-323-56-57-325 325v55ZM86-86v-234l526-526q14-14 31.5-21t36.5-7q18 0 36 7t33 21l98 96q14 14 21 32.5t7 37.5q0 19-7 37t-21 32L322-86H86Zm652-594-57-58 57 58ZM564-564l-28-28 56 57-28-29Z"/></svg>
          </button>

          <button type="submit" title="Opslaan"
                  style="all:unset; cursor:pointer; vertical-align:middle; display:none;">
            <svg xmlns='http://www.w3.org/2000/svg' height='22px' viewBox='0 -960 960 960' width='22px' fill='var(--on-primary-container)'><path d="M874-694v482q0 53-36.5 89.5T748-86H212q-53 0-89.5-36.5T86-212v-536q0-53 36.5-89.5T212-874h482l180 180Zm-126 53L641-748H212v536h536v-429ZM480-252q50 0 85-35t35-85q0-50-35-85t-85-35q-50 0-85 35t-35 85q0 50 35 85t85 35ZM252-548h360v-160H252v160Zm-40-93v429-536 107Z"/></svg>
          </button>
        </form>
      </div>

      <form action="delete_vragenlijst.php" method="post" style="display:inline;" onsubmit="return confirm('Weet je zeker dat je deze vragenlijst wilt verwijderen?');">
        <input type="hidden" name="vragenlijst_id" value="<?= $v['id'] ?>">
        <button type="submit" style="all: unset; vertical-align: middle; padding-left: 4px; cursor: pointer;">
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="var(--on-primary-container)">
            <path d="M269-86q-53 0-89.5-36.5T143-212v-497H80v-126h257v-63h284v63h259v126h-63v497q0 53-36.5 89.5T691-86H269Zm422-623H269v497h422v-497ZM342-281h103v-360H342v360Zm173 0h103v-360H515v360ZM269-709v497-497Z"></path>
          </svg>
        </button>
      </form>

    </span>
  </li>
<?php endforeach; ?>
    </ul>
  </div>

</div>
</body>
</html>
