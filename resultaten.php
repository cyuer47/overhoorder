<?php
require_once __DIR__ . '/php/db.php';
if (empty($_SESSION['docent_id'])) { header('Location: login.php'); exit; }
$docent_id = $_SESSION['docent_id'];
$klas_id = $_GET['klas'] ?? null;
if($klas_id){
    // ensure ownership
    $stmt = $pdo->prepare('SELECT * FROM klassen WHERE id = ? AND docent_id = ?');
    $stmt->execute([$klas_id,$docent_id]);
    if(!$stmt->fetch()) die('Geen toegang tot deze klas.');
    $stmt = $pdo->prepare('SELECT r.*, l.naam as leerlingNaam, v.vraag FROM resultaten r JOIN leerlingen l ON l.id=r.leerling_id JOIN vragen v ON v.id=r.vraag_id JOIN sessies s ON s.id=r.sessie_id WHERE s.klas_id = ? ORDER BY r.created_at DESC');
    $stmt->execute([$klas_id]);
    $rows = $stmt->fetchAll();
} else {
    die('Geef klas id via ?klas=ID');
}
?>
<!doctype html><html lang="nl"><head><meta charset="utf-8"><title>Resultaten</title><link rel="stylesheet" href="assets/css/styles.css"><link rel="icon" type="image/x-icon" href="http://overhoren.ivenboxem.nl/assets/img/logo2.png"></head><body>
<div class="container">
  <a class="btn-text" href="dashboard.php">← Terug</a>
  <h1 class="app-title">Resultaten klas <?=htmlspecialchars($klas_id)?></h1>
  <div class="card">
    <table class="table">
      <thead><tr><th>Leerling</th><th>Vraag</th><th>Antwoord</th><th>Goed?</th><th>Tijd</th></tr></thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?=htmlspecialchars($r['leerlingNaam'])?></td>
            <td><?=htmlspecialchars($r['vraag'])?></td>
            <td><?=htmlspecialchars($r['antwoord_given'])?></td>
            <td><?= $r['correct'] ? '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#53b806"><path d="M382-208 122-468l90-90 170 170 366-366 90 90-456 456Z"/></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#b82906"><path d="m256-168-88-88 224-224-224-224 88-88 224 224 224-224 88 88-224 224 224 224-88 88-224-224-224 224Z"/></svg>' ?></td>
            <td><?=htmlspecialchars($r['created_at'])?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body></html>
