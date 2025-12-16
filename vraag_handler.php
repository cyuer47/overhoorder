<?php
require_once __DIR__ . '/php/db.php';
session_start();

if (empty($_SESSION['docent_id'])) {
    $current_page = basename($_SERVER['PHP_SELF']);
    header('Location: login.php?redirecturi=' . urlencode($current_page));
    exit;
}
$docent_id = $_SESSION['docent_id'];
$sessie_id = $_GET['sessie'] ?? null;
if (!$sessie_id) die('Geen sessie geselecteerd.');

// load session and klas
$stmt = $pdo->prepare('SELECT s.*, k.naam as klasnaam, k.klascode FROM sessies s JOIN klassen k ON k.id = s.klas_id WHERE s.id = ? AND s.docent_id = ?');
$stmt->execute([$sessie_id, $docent_id]);
$sess = $stmt->fetch();
if (!$sess) die('Sessie niet gevonden of geen rechten.');
$_SESSION['klas_id'] = $sess['klas_id'];

// add question (for class)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vraag'])) {
    $vraag = trim($_POST['vraag']); $antwoord = trim($_POST['antwoord']);
    if($vraag && $antwoord){
        $stmt = $pdo->prepare('INSERT INTO vragen (klas_id, vraag, antwoord) VALUES (?, ?, ?)');
        $stmt->execute([$sess['klas_id'], $vraag, $antwoord]);
    }
    header('Location: vraag_handler.php?sessie='.$sessie_id); exit;
}

// HANDMATIG NAKIJKEN: UPDATE NAAR STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_answer'])) {
    $resultaat_id = $_POST['resultaat_id'];
    $status = $_POST['status'];
    
    // Calculate points based on status
    $points = 0;
    switch($status) {
        case 'goed': $points = 10; break;
        case 'typfout': $points = 5; break;
        case 'fout': $points = 0; break;
    }
    
    $stmt = $pdo->prepare('UPDATE resultaten SET status = ?, points = ? WHERE id = ?');
    $stmt->execute([$status, $points, $resultaat_id]);
    header('Location: vraag_handler.php?sessie='.$sessie_id); exit;
}

// NEW: Send question to all students
if(isset($_GET['action']) && $_GET['action'] === 'send_question') {
    // 1. Get IDs of questions already asked in this session
    $stmt = $pdo->prepare('SELECT DISTINCT vraag_id FROM resultaten WHERE sessie_id = ?');
    $stmt->execute([$sessie_id]);
    $asked_question_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // 2. Select a question that has not been asked yet
    $placeholders = implode(',', array_fill(0, count($asked_question_ids), '?'));
    $query_params = array_merge([$sess['klas_id'], $sess['vragenlijst_id']], $asked_question_ids);

    if (empty($asked_question_ids)) {
        $stmt = $pdo->prepare('SELECT * FROM vragen WHERE klas_id = ? AND vragenlijst_id = ?');
        $stmt->execute([$sess['klas_id'], $sess['vragenlijst_id']]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM vragen WHERE klas_id = ? AND vragenlijst_id = ? AND id NOT IN (' . $placeholders . ')');
        $stmt->execute($query_params);
    }
    
    $vragen = $stmt->fetchAll();
    
    if(!empty($vragen)) {
        $q = $vragen[array_rand($vragen)];
        
        // Set current question for all students to see
        $stmt = $pdo->prepare('UPDATE sessies SET current_question_id = ?, question_start_time = NOW() WHERE id = ?');
        $stmt->execute([$q['id'], $sessie_id]);
        
        // Clear any previous answers for this round
        $stmt = $pdo->prepare('DELETE FROM resultaten WHERE sessie_id = ? AND vraag_id = ?');
        $stmt->execute([$sessie_id, $q['id']]);
        
        header('Location: vraag_handler.php?sessie=' . $sessie_id);
        exit;
    } else {
        // No more questions left, redirect with a flag
        header('Location: vraag_handler.php?sessie=' . $sessie_id . '&status=no_more_questions');
        exit;
    }
}

// CLEAR question (stop current round)
if(isset($_GET['action']) && $_GET['action'] === 'clear_question') {
    $stmt = $pdo->prepare('UPDATE sessies SET current_question_id = NULL, question_start_time = NULL WHERE id = ?');
    $stmt->execute([$sessie_id]);
    header('Location: vraag_handler.php?sessie='.$sessie_id);
    exit;
}

// NEW: Handle student deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    $leerling_id = $_POST['leerling_id'];
    
    // First, delete all results associated with the student
    $stmt = $pdo->prepare('DELETE FROM resultaten WHERE leerling_id = ?');
    $stmt->execute([$leerling_id]);
    
    // Then, delete the student
    $stmt = $pdo->prepare('DELETE FROM leerlingen WHERE id = ? AND klas_id = ?');
    $stmt->execute([$leerling_id, $_SESSION['klas_id']]);
    
    header('Location: vraag_handler.php?sessie=' . $sessie_id);
    exit;
}

// Check for status messages
$status_message = '';
if (isset($_GET['status']) && $_GET['status'] === 'no_more_questions') {
    $status_message = 'Alle vragen zijn beantwoord! Voeg nieuwe vragen toe of beëindig de sessie.';
}


$stmt = $pdo->prepare('SELECT s.*, k.naam as klasnaam, k.klascode FROM sessies s JOIN klassen k ON k.id = s.klas_id WHERE s.id = ?');
$stmt->execute([$sessie_id]);
$sess = $stmt->fetch();

$stmt = $pdo->prepare('SELECT * FROM vragen WHERE klas_id = ?');
$stmt->execute([$sess['klas_id']]); $vragen = $stmt->fetchAll();
$stmt = $pdo->prepare('SELECT * FROM leerlingen WHERE klas_id = ?');
$stmt->execute([$sess['klas_id']]); $leerlingen = $stmt->fetchAll();
?>
<!doctype html>
<html lang="nl">
<head>
<script>
    !function(t,e){var o,n,p,r;e.__SV||(window.posthog && window.posthog.__loaded)||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="init Ce js Ls Te Fs Ds capture Ye calculateEventProperties zs register register_once register_for_session unregister unregister_for_session Ws getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSurveysLoaded onSessionId getSurveys getActiveMatchingSurveys renderSurvey displaySurvey canRenderSurvey canRenderSurveyAsync identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty Bs Us createPersonProfile Hs Ms Gs opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing get_explicit_consent_status is_capturing clear_opt_in_out_capturing Ns debug L qs getPageViewId captureTraceFeedback captureTraceMetric".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
    posthog.init('phc_bjbHSOWX3nTZKHLlw7SSkutWkjSfTV38ewAcRmxPsYN', {
        api_host: 'https://eu.i.posthog.com',
        defaults: '2025-05-24',
        person_profiles: 'always', // or 'always' to create profiles for anonymous users as well
    })
</script>
    <meta charset="utf-8">
    <title>Vragen</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="icon" type="image/x-icon" href="https://overhoren.ivenboxem.nl/assets/img/logo2.png">
    <style>
     .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.6);
     }
     .modal-content {
        background-color: #fefefe;
        margin: 15% auto;
        padding: 20px;
        border-radius: 8px;
        width: 80%;
        max-width: 400px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        text-align: center;
     }
     .modal-buttons {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 20px;
     }
     .modal-buttons button {
        padding: 12px;
        font-size: 16px;
        border-radius: 20px;
        border: none;
        cursor: pointer;
     }
     .modal-buttons .btn-good {
        background-color: #4CAF50;
        color: white;
     }
     .modal-buttons .btn-typo {
        background-color: #2196F3;
        color: white;
     }
     .modal-buttons .btn-wrong {
        background-color: #F44336;
        color: white;
     }
     .score-board {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 16px;
     }
     .score-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #e9ecef;
     }
     .score-item:last-child {
        border-bottom: none;
     }
     .current-question {
        background: #e3f2fd;
        padding: 20px;
        border-radius: 12px;
        border-left: 4px solid #2196F3;
     }
    </style>
</head>
<body>
<div class="container">
    <a class="btn-text" href="dashboard.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="var(--svg-icons)"><path d="m368-417 202 202-90 89-354-354 354-354 90 89-202 202h466v126H368Z"/></svg></a>
    <h1 class="app-title">Overhoring - <?=htmlspecialchars($sess['klasnaam'])?></h1>
    <div style="text-align: center; margin-bottom: 20px;">
        <span class="chip">Klascode: <strong><?=htmlspecialchars($sess['klascode'])?></strong></span>
    </div>

    <div class="card">
        <h3>Vraag Beheer</h3>
        <?php
          if ($status_message) {
            echo '<div class="current-question" style="background: #fff3cd; border-color: #ffe066;">';
            echo "<h4><svg xmlns='http://www.w3.org/2000/svg' height='24px' viewBox='0 -960 960 960' width='24px' fill='#F9DB78'><path d='m11-103 469-811 469 811H11Zm192-111h554L480-692 203-214Zm277-29q18 0 31.5-13.5T525-288q0-18-13.5-31T480-332q-18 0-31.5 13T435-288q0 18 13.5 31.5T480-243Zm-40-117h80v-189h-80v189Zm40-93Z'/></svg> Let op:</h4>";
            echo "<p style='font-size: 16px; font-weight: bold; margin: 10px 0;'>" . htmlspecialchars($status_message) . "</p>";
            echo "</div>";
          } else if ($sess['current_question_id']) {
            // Haal vraagtekst en aantal antwoorden op
            $stmt = $pdo->prepare('
            SELECT v.vraag, COUNT(r.id) AS antwoorden
            FROM vragen v
            LEFT JOIN resultaten r ON r.vraag_id = v.id AND r.sessie_id = ?
            WHERE v.id = ?
            GROUP BY v.id
            ');
            $stmt->execute([$sessie_id, $sess['current_question_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $qtxt = $row['vraag'];
            $aantalAntwoorden = $row['antwoorden'];

            
            echo "<div class='current-question'>";
            echo "<h4><svg xmlns='http://www.w3.org/2000/svg' height='24px' viewBox='0 -960 960 960' width='24px' fill='#BB271A'><path d='M735-425v-110h206v110H735Zm79 296L649-253l67-88 164 123-66 89Zm-98-490-67-88 165-124 66 89-164 123ZM139-166v-160h-10q-48-6-79-42t-31-84v-56q0-51.97 37.01-88.99Q93.03-634 145-634h138l243-146v600L283-326h-12v160H139Zm261-236v-156l-86 50H145v56h169l86 50Zm166 90v-336q42 28 66.5 72.5T657-480q0 51-24.5 95.5T566-312ZM273-480Z'/></svg> Huidige vraag voor alle leerlingen:</h4>";
            echo "<p style='font-size: 18px; font-weight: bold; margin: 10px 0;'>" . htmlspecialchars($qtxt) . " <span id='answerCount' style='color:#555'>(Antwoorden: $aantalAntwoorden)</span></p>";


            echo "<p class='helper'>Alle leerlingen kunnen nu deze vraag beantwoorden.</p>";
            echo "</div>";
            
            echo '<div style="margin-top:12px">';
            echo '<a class="btn-danger" href="vraag_handler.php?sessie=' . $sessie_id . '&action=clear_question">Stop huidige vraag</a>';
            echo ' ';
            echo '<a class="btn-primary" href="vraag_handler.php?sessie=' . $sessie_id . '&action=send_question">Volgende vraag</a>';
            echo ' ';
            echo '<a class="btn-primary" href="cast.php?sessie=' . $sessie_id . '" target="_blank">Toon castscherm (voor projectie)</a>';
            echo '</div>';
          } else {
            echo '<p class="helper">Geen actieve vraag. Verstuur een vraag naar alle leerlingen.</p>';
            echo '<div style="margin-top:12px">';
            echo '<a class="btn-primary" href="vraag_handler.php?sessie=' . $sessie_id . '&action=send_question">Verstuur vraag naar alle leerlingen</a>';
            echo ' ';
            echo '<a class="btn-primary" href="cast.php?sessie=' . $sessie_id . '" target="_blank">Toon castscherm (voor projectie)</a>';
            echo '</div>';
          }
        ?>
        <br>
        <div style="margin-top:8px">
          <a class="btn-danger" href="stop_sessie.php?sessie=<?= $sessie_id ?>">Stop sessie</a>
        </div>
    </div>
    
    <div style="height:16px"></div>
    
    <div class="card">
        <h3><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#cca20c"><path d="M172-212h121v-296H172v296Zm247 0h122v-536H419v536Zm248 0h121v-216H667v216ZM46-86v-548h247v-240h374v320h247v468H46Z"/></svg> Scorebord</h3>
        <div class="score-board" id="scoreboard-container">
        </div>
    </div>
    
    <div style="height:16px"></div>
    
    <div class="card">
        <h3><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#0c66cc"><path d="M417-417H166v-126h251v-251h126v251h251v126H543v251H417v-251Z"/></svg> Vragen toevoegen</h3>
        <form method="post">
            <label>Vraag</label><input name="vraag" required>
            <label>Antwoord</label><input name="antwoord" required>
            <div style="margin-top:12px"><button class="btn-secondary" type="submit">Opslaan</button></div>
        </form>
    </div>
    
    <div style="height:16px"></div>
    
    <div class="card">
        <h3><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#0ccc63"><path d="M480-332 232-580l89-89 96 97v-262h126v262l96-97 89 89-248 248ZM252-126q-53 0-89.5-36.5T126-252v-120h126v120h456v-120h126v120q0 53-36.5 89.5T708-126H252Z"/></svg> Resultaten downloaden</h3>
        <p class="helper">Download de resultaten van deze sessie als CSV-bestand voor analyse.</p>
        <a class="btn-primary" href="export_results.php?sessie=<?= $sessie_id ?>">Download CSV</a>
    </div>
    
    <div style="height:16px"></div>

    <div class="card">
        <h3><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#08786f"><path d="M478-86q-152 0-264.5-101T87-440h127q15 99 89.5 163.5T478-212q112 0 190-78t78-190q0-112-78-190t-190-78q-57 0-109 23.5T279-657h82v97H94v-265h95v79q56-62 130.5-95T478-874q81 0 153 31t125.5 84.5Q810-705 841-633t31 153q0 81-31 153t-84.5 125.5Q703-148 631-117T478-86Zm107-218L433-456v-224h95v184l125 124-68 68Z"/></svg> Recente antwoorden</h3>
        <div id="recent-answers-container">
        </div>
    </div>
    
    <div style="height:16px"></div>
    
    <div class="card">
        <h3><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#8c0ccc"><path d="M212-86q-53 0-89.5-36.5T86-212v-536q0-53 36.5-89.5T212-874h536q53 0 89.5 36.5T874-748v536q0 53-36.5 89.5T748-86H212Zm0-126h536v-247H212v247Zm0-373h536v-175H212v175Zm268-49h228v-86H480v86Zm-268 49v-175 175Z"/></svg> Leerlingen in de sessie</h3>
        <div id="students-list-container">
        </div>
    </div>

</div>

<div id="gradeModal" class="modal">
  <div class="modal-content">
    <span onclick="closeModal()" style="float:right; cursor:pointer; font-size: 24px;"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="m347-280 133-133 133 133 67-67-133-133 133-133-67-67-133 133-133-133-67 67 133 133-133 133 67 67ZM480-46q-91 0-169.99-34.08-78.98-34.09-137.41-92.52-58.43-58.43-92.52-137.41Q46-389 46-480q0-91 34.08-169.99 34.09-78.98 92.52-137.41 58.43 58.43 137.41-92.52Q389-914 480-914q91 0 169.99 34.08 78.98 34.09 137.41 92.52 58.43 58.43 92.52 137.41Q914-571 914-480q0 91-34.08 169.99-34.09-78.98-92.52-137.41-58.43-58.43-137.41-92.52Q571-46 480-46Zm0-126q130 0 219-89t89-219q0-130-89-219t-219-89q-130 0-219 89t-89 219q0 130 89 219t219 89Zm0-308Z"/></svg></span>
    <h4>Beoordeel antwoord</h4>
    <div style="text-align: left; margin-top: 20px;">
        <p><strong>Gegeven antwoord:</strong> <span id="modalStudentAnswer"></span></p>
        <p><strong>Correct antwoord:</strong> <span id="modalCorrectAnswer"></span></p>
    </div>
    <p style="margin-top: 20px;">Selecteer de status voor dit antwoord:</p>
    <div class="modal-buttons">
      <form method="post">
        <input type="hidden" name="resultaat_id" id="modalResultId">
        <input type="hidden" name="status" value="goed">
        <button class="btn-good" type="submit" name="grade_answer">✅ Goed (10 punten)</button>
      </form>
      <form method="post">
        <input type="hidden" name="resultaat_id" id="modalResultId2">
        <input type="hidden" name="status" value="typfout">
        <button class="btn-typo" type="submit" name="grade_answer">📝 Typfout (5 punten)</button>
      </form>
      <form method="post">
        <input type="hidden" name="resultaat_id" id="modalResultId3">
        <input type="hidden" name="status" value="fout">
        <button class="btn-wrong" type="submit" name="grade_answer">❌ Fout (0 punten)</button>
      </form>
    </div>
  </div>
</div>

<script>
    const sessionId = '<?= $sessie_id ?>';
    
    function refreshStudentList() {
        const container = document.getElementById('students-list-container');
        fetch('get_students.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Netwerkrespons was niet ok.');
                }
                return response.text();
            })
            .then(html => {
                container.innerHTML = html;
            })
            .catch(error => {
                console.error('Er is een fout opgetreden bij het verversen:', error);
                container.innerHTML = '<p style="color:red;">Fout bij het laden van de lijst.</p>';
            });
    }

    function refreshScoreboard() {
        const container = document.getElementById('scoreboard-container');
        fetch(`get_scoreboard.php?sessie_id=${sessionId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Netwerkrespons was niet ok.');
                }
                return response.text();
            })
            .then(html => {
                container.innerHTML = html;
            })
            .catch(error => {
                console.error('Er is een fout opgetreden bij het verversen:', error);
                container.innerHTML = '<p style="color:red;">Fout bij het laden van het scorebord.</p>';
            });
    }

    function refreshRecentAnswers() {
        const container = document.getElementById('recent-answers-container');
        fetch(`get_recent_answers.php?sessie_id=${sessionId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Netwerkrespons was niet ok.');
                }
                return response.text();
            })
            .then(html => {
                container.innerHTML = html;
            })
            .catch(error => {
                console.error('Er is een fout opgetreden bij het verversen:', error);
                container.innerHTML = '<p style="color:red;">Fout bij het laden van de recente antwoorden.</p>';
            });
    }
    function refreshAnswerCount() {
        const span = document.getElementById('answerCount');
        if (!span) return; // Alleen als er een actieve vraag is
        fetch(`get_answer_count.php?sessie_id=${sessionId}`)
        .then(r => r.text())
        .then(count => {
            span.textContent = `(Antwoorden: ${count})`;
        })
        .catch(err => console.error('Fout bij answerCount:', err));
}

    document.addEventListener('DOMContentLoaded', () => {
        refreshStudentList();
        refreshScoreboard();
        refreshRecentAnswers();
        refreshAnswerCount();
    });
    // Iets lagere frequentie om DB-load te verlagen (3x per seconde → 1x per 2s)
    setInterval(refreshStudentList, 5000);
    setInterval(refreshRecentAnswers, 6000);
    setInterval(refreshAnswerCount, 8000);
    setInterval(refreshScoreboard, 10000);

    // Modal JavaScript
    function openModal(resultId, studentAnswer, correctAnswer) {
        document.getElementById('gradeModal').style.display = 'block';
        document.getElementById('modalResultId').value = resultId;
        document.getElementById('modalResultId2').value = resultId;
        document.getElementById('modalResultId3').value = resultId;
        document.getElementById('modalStudentAnswer').innerText = studentAnswer;
        document.getElementById('modalCorrectAnswer').innerText = correctAnswer;
    }

    function closeModal() {
        document.getElementById('gradeModal').style.display = 'none';
    }

    // Close the modal if the user clicks outside of it
    window.onclick = function(event) {
      const modal = document.getElementById('gradeModal');
      if (event.target == modal) {
        closeModal();
      }
    }
</script>

</body>
</html>