<?php
session_start();
require_once __DIR__ . '/php/db.php';
if (empty($_SESSION['docent_id'])) {
    $current_page = basename($_SERVER['PHP_SELF']);
    header('Location: login.php?redirecturi=' . urlencode($current_page));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['code'])) {
    $code = strtoupper(trim($_POST['code']));

    // Zoek de code in de database
    $stmt = $pdo->prepare('SELECT * FROM licenties WHERE code = ? AND docent_id IS NULL AND actief = 1');
    $stmt->execute([$code]);
    $lic = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lic) {
        // Claim de licentie
        $stmt = $pdo->prepare('UPDATE licenties SET docent_id = ?, geclaimd_at = NOW() WHERE id = ?');
        $stmt->execute([$_SESSION['docent_id'], $lic['id']]);

        // ✅ Koppel de juiste boeken op basis van het type (Duits, Engels, Frans)
        $boek_mapping = [
            'Duits' => 1,   // Neue Kontakte
            'Engels' => 4,  // Stepping Stones
            'Frans' => 5    // Libre Service (voeg dit boek zelf toe aan tabel boeken)
        ];

        if (isset($boek_mapping[$lic['type']])) {
            $boek_id = $boek_mapping[$lic['type']];
            $insert = $pdo->prepare("INSERT IGNORE INTO licentie_boeken (licentie_id, boek_id) VALUES (?, ?)");
            $insert->execute([$lic['id'], $boek_id]);
        }

        echo "<script>alert('✅ Licentie succesvol geactiveerd! Type: {$lic['type']}'); window.location='dashboard.php';</script>";
        exit;
    } else {
        echo "<script>alert('❌ Ongeldige of al geclaimde code.');</script>";
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>Licentie toevoegen</title>
<link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<div class="container">
  <h1>Licentie toevoegen</h1>
  <form method="post">
    <label>Licentiecode</label>
    <input name="code" required placeholder="Voer code in...">
    <div style="padding:5px;"></div>
    <button class="btn-primary" type="submit">Activeren</button>
  </form>
</div>
</body>
</html>
