<?php
require_once __DIR__ . '/php/db.php';

if (empty($_SESSION['docent_id'])) {
    $current_page = basename($_SERVER['PHP_SELF']);
    header('Location: login.php?redirecturi=' . urlencode($current_page));
    exit;
}

// Nieuwe logica: Zet boek ID in sessie en herleid naar viewer (zonder ID in URL)
if (isset($_GET['action']) && $_GET['action'] === 'view_ebook' && isset($_GET['id'])) {
    $book_id_to_view = (int)$_GET['id'];
    if ($book_id_to_view > 0) {
        // Sla het ID op in de sessie
        $_SESSION['current_ebook_id'] = $book_id_to_view; 
        
        // Herleid naar de viewer zonder ID in de URL
        // Aangenomen dat 'ebook_viewer4.php' de juiste naam is
        header('Location: ebook_viewer.php');
        exit;
    }
}

$admin_id = 3;
$no_admin_id = -1;
$docent_id = $_SESSION['docent_id'];
$docent_naam = $_SESSION['docent_naam'] ?? 'Docent';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['klas_naam'])) {
    $naam = trim($_POST['klas_naam']);
    $vak = trim($_POST['vak'] ?? '');
    if ($naam) {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $stmt = $pdo->prepare('INSERT INTO klassen (docent_id, naam, klascode, vak) VALUES (?, ?, ?, ?)');
        $stmt->execute([$docent_id, $naam, $code, $vak]);
    }
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM klassen WHERE docent_id = ? ORDER BY id DESC');
$stmt->execute([$docent_id]);
$klassen = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM licenties WHERE docent_id = ? AND actief = 1 AND (vervalt_op IS NULL OR vervalt_op >= CURDATE())');
$stmt->execute([$docent_id]);
$licenties = $stmt->fetchAll(PDO::FETCH_ASSOC);

$_SESSION['heeft_licentie'] = false;
foreach ($licenties as $lic) {
    if ($lic['type'] === 'vragenlijsten') {
        $_SESSION['heeft_licentie'] = true;
        break;
    }
}

/* Bibliotheekvragenlijsten ophalen */
if ($docent_id == $admin_id) {
    // Admin of hoofdaccount ziet alles, inclusief verborgen lijsten
    $stmt = $pdo->query("SELECT * FROM bibliotheek_vragenlijsten ORDER BY id DESC");
} else {
    // Andere docenten zien alleen niet-verborgen lijsten
    $stmt = $pdo->query("SELECT * FROM bibliotheek_vragenlijsten WHERE licentie_type != 'verborgen' ORDER BY id DESC");
}
$biblio_lijsten = $stmt->fetchAll();

if ($docent_id == $no_admin_id) {
    // Admin ziet alle boeken
    $stmt = $pdo->query("SELECT id, titel, omschrijving FROM boeken ORDER BY id DESC");
    $boeken = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Andere docenten zien alleen boeken waarvoor ze licentie hebben
    $stmt = $pdo->prepare("
        SELECT DISTINCT b.id, b.titel, b.omschrijving
        FROM boeken b
        JOIN licentie_boeken lb ON lb.boek_id = b.id
        JOIN licenties l ON l.id = lb.licentie_id
        WHERE l.docent_id = ? AND l.actief = 1
        AND (l.vervalt_op IS NULL OR l.vervalt_op >= CURDATE())
        ORDER BY b.id DESC
    ");
    $stmt->execute([$docent_id]);
    $boeken = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Huidige avatar ophalen
$stmt = $pdo->prepare("SELECT avatar FROM docenten WHERE id = ?");
$stmt->execute([$docent_id]);
$current_avatar = $stmt->fetchColumn();
?>
<!doctype html>
<html lang="nl">
<head>
<script>
!function(t,e){var o,n,p,r;e.__SV||(window.posthog && window.posthog.__loaded)||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="init Ce js Ls Te Fs Ds capture Ye calculateEventProperties zs register register_once register_for_session unregister unregister_for_session Ws getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSurveysLoaded onSessionId getSurveys getActiveMatchingSurveys renderSurvey displaySurvey canRenderSurvey canRenderSurveyAsync identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty Bs Us createPersonProfile Hs Ms Gs opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing get_explicit_consent_status is_capturing clear_opt_in_out_capturing Ns debug L qs getPageViewId captureTraceFeedback captureTraceMetric".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
posthog.init('phc_bjbHSOWX3nTZKHLlw7SSkutWkjSfTV38ewAcRmxPsYN', {
    api_host: 'https://eu.i.posthog.com',
    defaults: '2025-05-24',
    person_profiles: 'always',
});
</script>
<meta charset="utf-8">
<title>Dashboard</title>
<link rel="stylesheet" href="assets/css/styles.css">
<link rel="icon" type="image/x-icon" href="https://overhoren.ivenboxem.nl/assets/img/logo2.png">
</head>
<body>
<div class="container">

  <div style="display:flex;justify-content:space-between;align-items:center">
    <div class="chip">Overhoorder • Docent</div>
    <div class="profile-container">
    <button class="profile-btn" onclick="toggleProfileMenu()">
        <!-- <img src="identicon.php?seed=<?= urlencode($docent_naam) ?>&size=48" alt="Avatar" class="profile-avatar"> -->
        <img 
    src="get_avatar.php?file=<?= htmlspecialchars($current_avatar ?? '') ?>&seed=<?= urlencode($docent_naam) ?>&size=48" 
    alt="Avatar" 
    class="profile-avatar">
        <span class="profile-name"><?= htmlspecialchars($docent_naam) ?></span>
        <svg class="dropdown-arrow" xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" fill="#1f1f1f">
            <path d="M7 10l5 5 5-5H7z"/>
        </svg>
    </button>
    <div class="profile-menu">
        <a href="profile.php">Mijn profiel</a>
        <a href="settings.php">Instellingen</a>
        <a href="logout.php" class="logout-btn">Uitloggen</a>
    </div>
</div>

  </div>

  <h1 class="app-title">Dashboard</h1>

  <!-- Nieuwe klas -->
  <div class="card">
    <h3>Nieuwe klas</h3>
    <form method="post">
      <label>Klasnaam</label><input name="klas_naam" required>
      <label>Vak (optioneel)</label><input name="vak">
      <div style="margin-top:12px"><button class="btn-primary" type="submit">Klas aanmaken</button></div>
    </form>
  </div>

  <div style="height:16px"></div>

  <!-- Klassenoverzicht -->
  <div class="card">
    <h3>Uw klassen</h3>
    <?php if (empty($klassen)) echo '<p class="helper">Nog geen klassen.</p>'; ?>
    <ul>
      <?php foreach ($klassen as $k): ?>
        <li style="margin:10px 0">
          <strong><?= htmlspecialchars($k['naam']) ?></strong>
          <div class="helper">Klascode: <?= htmlspecialchars($k['klascode']) ?> - Vak: <?= htmlspecialchars($k['vak']) ?></div>
          <div style="margin-top:8px;padding:5px;">
            <a class="btn-secondary" href="manage_klas.php?klas=<?= $k['id'] ?>">Beheren</a>
            <a class="btn-primary" href="start_sessie.php?klas=<?= $k['id'] ?>">Start overhoring</a>
            <a class="btn-danger" href="delete_klas.php?klas=<?= $k['id'] ?>" onclick="return confirm('Weet je zeker dat je deze klas wilt verwijderen? Alle leerlingen, vragen en resultaten zullen ook worden verwijderd.');">Verwijderen</a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div style="height:16px"></div>

  <!-- Bibliotheek -->
  <div class="card">
    <h3>Vragenlijstbibliotheek</h3>
    <p class="helper">Gebruik bestaande vragenlijsten of voeg ze toe aan je klas.</p>

    <ul>
      <?php if (empty($biblio_lijsten)) echo '<p class="helper">Nog geen vragenlijsten in de bibliotheek.</p>'; ?>
      <?php foreach ($biblio_lijsten as $b): ?>
        <li style="margin:10px 0">
          <strong><?= htmlspecialchars($b['naam']) ?></strong>
          <div class="helper"><?= htmlspecialchars($b['beschrijving'] ?? '') ?></div>
          <div style="margin-top:8px;padding:5px;">
            <?php
            $mag_gebruiken = false;
            $lic_type = $b['licentie_type'] ?? 'gratis';

            if ($lic_type === 'gratis') {
                $mag_gebruiken = true;
            } else {
                foreach ($licenties as $lic) {
                    if ($lic['type'] === $lic_type || $lic['type'] === 'premium') {
                        $mag_gebruiken = true;
                        break;
                    }
                }
            }
            ?>

            <?php if ($mag_gebruiken): ?>
              <a class="btn-primary" href="gebruik_bibliotheek.php?id=<?= $b['id'] ?>">Toevoegen aan klas</a>
            <?php else: ?>
              <button class="btn-secondary" disabled title="Licentie vereist">
                Licentie vereist (<?= htmlspecialchars($lic_type) ?>)
              </button>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

<div style="height:16px"></div>

<!-- Boekenlicenties -->
<div class="card">
  <h3>Boekenlicenties</h3>
  <?php if (empty($boeken)): ?>
    <p class="helper">Nog geen boeken beschikbaar. Activeer eerst een licentie om boeken te ontgrendelen.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($boeken as $boek): ?>
        <li style="margin:10px 0">
          <strong><?= htmlspecialchars($boek['titel']) ?></strong>
          <div class="helper"><?= htmlspecialchars($boek['omschrijving'] ?? '') ?></div>
          <div style="margin-top:8px;padding:5px;">
            <a class="btn-primary" href="dashboard.php?action=view_ebook&id=<?= $boek['id'] ?>">Open boek</a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

  <div style="height:16px"></div>

  <!-- Licenties -->
  <div class="card">
    <h3>Licenties</h3>
    <p class="helper">Je hebt momenteel <?= count($licenties) ?> actieve licentie(s).</p>
    <ul>
      <?php foreach ($licenties as $l): ?>
        <li>
          <strong><?= htmlspecialchars($l['type']) ?></strong> - vervalt op <?= htmlspecialchars($l['vervalt_op'] ?? 'geen vervaldatum') ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($docent_id == 3): ?>
    <div style="margin-top:12px;padding:5px;">
      <a href="licentie.php" class="btn-primary">Licentie toevoegen</a>
      <a href="admin_genereer_licenties.php" class="btn-secondary">Genereren</a>
    </div>
    <?php else: ?>
    <div style="margin-top:12px;padding:5px;">
      <a href="licentie.php" class="btn-primary">Licentie toevoegen</a>
    </div>
    <?php endif; ?>
  </div>
<div style="height:16px"></div>

<div class="card">
<h3>Zoeken</h3>
<p class="helper">Zoek naar elk publieke profiel!</p>
<input type="text" id="search" placeholder="Zoek een docent">
<ul id="results"></ul>
</div>

</div>

<script>
document.getElementById('search').addEventListener('input', async (e) => {
    const q = e.target.value;
    const ul = document.getElementById('results');
    ul.innerHTML = ''; // oude resultaten verwijderen

    if (!q) return; // niets getypt → geen resultaten tonen

    try {
        const res = await fetch(`search.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();

        data.forEach(person => {
            const li = document.createElement('li');
            const a = document.createElement('a');

            // link naar profiel
            a.href = `profile.php?id=${person.id}`;
            a.textContent = `${person.naam} (${person.badge})`;

            li.appendChild(a);
            ul.appendChild(li);
        });

        if (data.length === 0) {
            const li = document.createElement('li');
            li.textContent = 'Geen resultaten gevonden.';
            ul.appendChild(li);
        }

    } catch (err) {
        console.error('Fout bij zoeken:', err);
    }
});
</script>


<script>
function toggleProfileMenu() {
    const container = document.querySelector('.profile-container');
    container.classList.toggle('active');
}

// Sluit menu als je buiten klikt
document.addEventListener('click', function(e) {
    const container = document.querySelector('.profile-container');
    if (!container.contains(e.target)) {
        container.classList.remove('active');
    }
});
</script>
</body>
</html>
