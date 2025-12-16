<?php
session_start();
require_once __DIR__ . '/php/db.php';

// Alleen toegang voor docent ID 3 (admin)
if (empty($_SESSION['docent_id']) || $_SESSION['docent_id'] != 3) {
    die('❌ Geen toegang tot deze pagina.');
}

$docent_id = $_SESSION['docent_id'];

/**
 * Genereert een unieke licentiecode.
 * Voorbeeld: VRAGE-9A4B3F-921
 */
function genereer_code($type) {
    // Gebruik de eerste 5 karakters van het type voor de prefix
    return strtoupper(substr($type, 0, 5)) . '-' . strtoupper(bin2hex(random_bytes(3))) . '-' . rand(100, 999);
}

/* 📘 Boeken ophalen voor dropdown */
$boeken = $pdo->query("SELECT id, titel FROM boeken ORDER BY titel ASC")->fetchAll(PDO::FETCH_ASSOC);

/* 🧩 Licenties genereren */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aantal = max(1, (int)$_POST['aantal']);
    // Gebruik een standaardwaarde voor type als deze ontbreekt (bijvoorbeeld uit de selectie)
    $type = $_POST['type'] ?? 'Onbekend'; 
    $vervalt_op = !empty($_POST['vervalt_op']) ? $_POST['vervalt_op'] : null;
    // Boek IDs is een array bij multiple select
    $boek_ids = $_POST['boek_id'] ?? []; 

    if (empty($boek_ids)) {
        // Gebruik JavaScript voor een niet-blokkerende waarschuwing en teruggaan
        echo "<script>alert('⚠️ Kies minstens één boek.'); history.back();</script>";
        exit;
    }

    // Voorbereiden van statements buiten de lus is efficiënter
    $stmtLicentie = $pdo->prepare('INSERT INTO licenties (code, type, vervalt_op) VALUES (?, ?, ?)');
    $stmtKoppel = $pdo->prepare('INSERT INTO licentie_boeken (licentie_id, boek_id) VALUES (?, ?)');

    for ($i = 0; $i < $aantal; $i++) {
        $code = genereer_code($type);
        $stmtLicentie->execute([$code, $type, $vervalt_op]);
        $licentie_id = $pdo->lastInsertId(); // Haal de ID op van de zojuist ingevoegde licentie

        // Koppel elk geselecteerd boek aan de nieuwe licentie
        foreach ($boek_ids as $boek_id) {
            $stmtKoppel->execute([$licentie_id, $boek_id]);
        }
    }

    echo "<script>alert('✅ $aantal licentie(s) van type \"$type\" aangemaakt.'); window.location='admin_genereer_licenties.php';</script>";
    exit;
}

/* 📋 Laatste 50 licenties ophalen met gekoppelde boeken */
$stmt = $pdo->query("
    SELECT 
      l.id AS licentie_id,
      l.code,
      l.type,
      l.vervalt_op,
      l.geclaimd_at,
      d.naam AS docent_naam,
      GROUP_CONCAT(b.titel SEPARATOR ', ') AS boeken
    FROM licenties l
    LEFT JOIN docenten d ON l.docent_id = d.id
    LEFT JOIN licentie_boeken lb ON l.id = lb.licentie_id
    LEFT JOIN boeken b ON lb.boek_id = b.id
    GROUP BY l.id, l.code, l.type, l.vervalt_op, l.geclaimd_at, d.naam 
    ORDER BY l.id DESC
    LIMIT 50
");
$codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>Admin - Licentiegenerator</title>
<link rel="stylesheet" href="assets/css/styles.css">
<link rel="icon" type="image/x-icon" href="https://overhoren.ivenboxem.nl/assets/img/logo2.png">
<style>
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 12px;
  border-radius: 12px;
  overflow: hidden;
}
th, td {
  text-align: left;
  padding: 8px 10px;
}
th {
  background: var(--md-sys-color-primary-container, #f0f0f0);
}
tr:nth-child(even) {
  background: var(--surface-container-low, #fafafa);
}
tr:hover {
  background: var(--surface-container-high, #f3f6ff);
}
code {
  font-family: monospace;
  background: #f7f7f7;
  padding: 2px 4px;
  border-radius: 6px;
}
</style>
</head>
<body>
<div class="container">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h1 class="app-title">Licentiegenerator</h1>
        <a href="dashboard.php" class="btn-text">
            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="var(--svg-icons)">
                <path d="m368-417 202 202-90 89-354-354 354-354 90 89-202 202h466v126H368Z"/>
            </svg>
        </a>
    </div>

    <div class="card">
        <form method="post">
            <h3>Nieuwe licenties aanmaken</h3>

            <label for="boek_id">Boeken (meerdere mogelijk)</label>
            <select name="boek_id[]" id="boek_id" multiple required> 
                <?php foreach($boeken as $boek): ?>
                    <option value="<?= $boek['id'] ?>"><?= htmlspecialchars($boek['titel']) ?></option>
                <?php endforeach; ?>
            </select>
            <small>Houd Ctrl (of Cmd) ingedrukt om meerdere boeken te selecteren.</small><br>

            <label for="type">Type licentie</label>
            <select name="type" id="type" required>
                <option value="Neue Kontakte">Neue Kontakte</option>
                <option value="Engels">Engels</option>
                <option value="Grandes Lignes">Grandes Lignes</option>
                <option value="Overal Natuurkunde">Overal Natuurkunde</option>
                <option value="Nieuw Nederlands">Nieuw Nederlands</option>
            </select>

            <label for="vervalt_op">Vervaldatum (optioneel)</label>
            <input type="date" name="vervalt_op" id="vervalt_op" min="<?= date('Y-m-d') ?>">

            <label for="aantal">Aantal codes</label>
            <input type="number" name="aantal" id="aantal" min="1" max="100" value="5" required>

            <div style="margin-top:12px;">
                <button class="btn-primary" type="submit">Genereer licenties</button>
            </div>
        </form>
    </div>

    <div style="height:20px"></div>

    <div class="card">
        <h3>Laatste 50 licenties</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Code</th>
                <th>Type</th>
                <th>Boeken</th>
                <th>Docent</th>
                <th>Vervalt op</th>
                <th>Geclaimd</th>
            </tr>
            <?php if(empty($codes)): ?>
                <tr><td colspan="7"><em>Geen licenties gevonden.</em></td></tr>
            <?php else: ?>
                <?php foreach($codes as $c): ?>
                    <tr>
                        <td><?= $c['licentie_id'] ?></td> 
                        <td><code><?= htmlspecialchars($c['code']) ?></code></td>
                        <td><?= htmlspecialchars($c['type']) ?></td>
                        <td><?= htmlspecialchars($c['boeken'] ?: '—') ?></td> 
                        <td><?= htmlspecialchars($c['docent_naam'] ?: '—') ?></td>
                        <td><?= $c['vervalt_op'] ?: '—' ?></td>
                        <td><?= $c['geclaimd_at'] ?: '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>

</div>
</body>
</html>