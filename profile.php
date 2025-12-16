<?php
session_start();
require_once __DIR__ . '/php/db.php';

// Haal docent ID uit URL (indien aanwezig)
$docent_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Als geen ID → gebruik ingelogde docent
if ($docent_id <= 0) {
    if (empty($_SESSION['docent_id'])) {
        header('Location: login.php?redirecturi=' . urlencode(basename($_SERVER['PHP_SELF'])));
        exit;
    }
    $docent_id = $_SESSION['docent_id'];
}

// Haal docent gegevens op
$stmt = $pdo->prepare("SELECT naam, email, avatar, bio, vakken, is_public, badge FROM docenten WHERE id = ?");
$stmt->execute([$docent_id]);
$docent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$docent) {
    echo "Profiel niet gevonden.";
    exit;
}

// Check of het profiel openbaar is als je niet eigen profiel bekijkt
$eigen_profiel = isset($_SESSION['docent_id']) && $_SESSION['docent_id'] == $docent_id;

if (!$eigen_profiel && !$docent['is_public']) {
    echo "<script>
        alert('Dit profiel is privé en kan niet bekeken worden.');
        window.location.href='profile.php';
    </script>";
    exit;
}

// Vul defaults in als velden leeg zijn
$docent_naam = $docent['naam'] ?? 'Docent';
$email = $docent['email'] ?? '';
$current_avatar = $docent['avatar'] ?? null;
$bio = $docent['bio'] ?? '';
$vakken = $docent['vakken'] ?? '';
$is_public = (bool)($docent['is_public'] ?? 0);
$badge = $docent['badge'] ?? 'none';
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($docent_naam) ?> - Profiel</title>
<link rel="stylesheet" href="assets/css/styles.css">
<link rel="icon" type="image/x-icon" href="https://overhoren.ivenboxem.nl/assets/img/logo2.png">
<style>
/* Hier kun je dezelfde CSS als jouw huidige profielpagina gebruiken */
.profile-card { max-width:700px; margin:32px auto; padding:24px; }
.profile-header { display:flex; flex-direction:column; align-items:center; text-align:center; margin-bottom:32px; }
.profile-header img { width:160px; height:160px; border-radius:50%; border:4px solid #7C4DFF; padding:4px; background:#fff; margin-bottom:16px; }
.profile-header h1 { margin:0; font-size:28px; font-weight:700; }
.profile-header p { margin:4px 0; font-size:16px; color:#555; }
.profile-section { margin-bottom:24px; }
.profile-section h3 { margin-bottom:8px; font-size:20px; color:#7C4DFF; }
.profile-section p { margin:4px 0; font-size:16px; color:#333; }
.actions { display:flex; justify-content:center; gap:16px; margin-top:24px; }
</style>
</head>
<body>
<div class="container">
    <div class="profile-card">
        <div class="profile-header">
            <img src="get_avatar.php?file=<?= htmlspecialchars($current_avatar ?? '') ?>&seed=<?= urlencode($docent_naam) ?>&size=160" alt="Avatar">
            <h1><?= htmlspecialchars($docent_naam) ?></h1>
            <p><?= htmlspecialchars($email ?: 'E-mail niet ingesteld') ?></p>
            <p>Rol: Docent</p>
            <p>Badge: <?= htmlspecialchars($badge) ?></p>
        </div>

        <div class="profile-section">
            <h3>Over mij</h3>
            <p><?= nl2br(htmlspecialchars($bio ?: 'Geen bio toegevoegd.')) ?></p>
        </div>

        <div class="profile-section">
            <h3>Vakken / Specialisaties</h3>
            <p><?= nl2br(htmlspecialchars($vakken ?: 'Nog geen vakken toegevoegd.')) ?></p>
        </div>

        <div class="profile-section">
            <h3>Profielstatus</h3>
            <p><?= $is_public ? 'Openbaar profiel' : 'Privé profiel' ?></p>
        </div>

        <div class="actions">
            <?php if ($eigen_profiel): ?>
                <a class="btn-primary" href="settings.php">Instellingen wijzigen</a>
                <a class="btn-secondary" href="dashboard.php">Terug naar dashboard</a>
            <?php else: ?>
                <a class="btn-secondary" href="profile.php">Terug naar eigen profiel</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
