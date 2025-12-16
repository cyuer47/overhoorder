<?php
require_once __DIR__ . '/php/db.php';

session_start();

// join flow
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_klascode'])) {
    $code = strtoupper(trim($_POST['join_klascode']));
    $naam = trim($_POST['naam']);
    if(!$code || !$naam) { $error='Vul klascode en naam in'; }
    else {
        // find klas
        $stmt = $pdo->prepare('SELECT * FROM klassen WHERE klascode = ?');
        $stmt->execute([$code]);
        $k = $stmt->fetch();
        if(!$k) { $error='Klascode onbekend'; }
        else {
            $stmt = $pdo->prepare('INSERT INTO leerlingen (klas_id, naam) VALUES (?, ?)');
            $stmt->execute([$k['id'], $naam]);
            $leerling_id = $pdo->lastInsertId();
            $_SESSION['leerling_id'] = $leerling_id;
            $_SESSION['klas_id'] = $k['id'];
            $_SESSION['klas_code'] = $code;
            header('Location: leerling.php');
            exit;
        }
    }
}

$leerling_id = $_SESSION['leerling_id'] ?? null;
$klas_id = $_SESSION['klas_id'] ?? null;

// Controleer of de sessie nog bestaat
if ($leerling_id && $klas_id) {
    // 1. Zoek naar een actieve sessie voor deze klas
    $stmt = $pdo->prepare('SELECT id FROM sessies WHERE klas_id = ? AND actief = 1');
    $stmt->execute([$klas_id]);
    $actieve_sessie = $stmt->fetch();

    // 2. Als er geen actieve sessie is, log dan de leerling uit
    if (!$actieve_sessie) {
        // Vernietig de sessie van de leerling
        session_unset();
        session_destroy();
        // Stuur de leerling door naar de inlogpagina
        header('Location: index.php');
        exit;
    }
}

// Uitloggen als op de uitlogknop wordt gedrukt
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

?>
<!doctype html><html lang="nl"><head><meta charset="utf-8"><title>Leerling</title><link rel="icon" type="image/x-icon" href="https://overhoren.ivenboxem.nl/assets/img/logo2.png"><link rel="stylesheet" href="assets/css/styles.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<script>
    !function(t,e){var o,n,p,r;e.__SV||(window.posthog && window.posthog.__loaded)||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="init Ce js Ls Te Fs Ds capture Ye calculateEventProperties zs register register_once register_for_session unregister unregister_for_session Ws getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSurveysLoaded onSessionId getSurveys getActiveMatchingSurveys renderSurvey displaySurvey canRenderSurvey canRenderSurveyAsync identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty Bs Us createPersonProfile Hs Ms Gs opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing get_explicit_consent_status is_capturing clear_opt_in_out_capturing Ns debug L qs getPageViewId captureTraceFeedback captureTraceMetric".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
    posthog.init('phc_bjbHSOWX3nTZKHLlw7SSkutWkjSfTV38ewAcRmxPsYN', {
        api_host: 'https://eu.i.posthog.com',
        defaults: '2025-05-24',
        person_profiles: 'always', // or 'always' to create profiles for anonymous users as well
    })
</script>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
<style>
.question-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
.score-display {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 12px;
    text-align: center;
    margin-bottom: 20px;
}
.answer-form {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    border: 2px solid #6750A4;
    margin-top: 20px;
}
.answer-form input {
    font-size: 18px;
    padding: 15px;
    text-align: center;
    border: 2px solid #ddd;
    border-radius: 8px;
    width: 100%;
    box-sizing: border-box;
}
.answer-form input:focus {
    border-color: #6750A4;
    outline: none;
    box-shadow: 0 0 0 3px rgba(103, 80, 164, 0.1);
}
.answer-form button {
    font-size: 18px;
    padding: 15px 30px;
    width: 100%;
    margin-top: 15px;
    color: white;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    font-weight: bold;
    border-radius: 10px;
    /* border-color: white !important; */
    border: 0px;
    cursor: pointer;
}
.answer-form button:hover {
    background: linear-gradient(135deg, #667eea 100%, #764ba2 0%);
}
.status-message {
    padding: 12px 20px;
    border-radius: 8px;
    margin: 15px 0;
    text-align: center;
    font-weight: bold;
    animation: slideIn 0.3s ease;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.status-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.status-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.status-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
.waiting-screen {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}
.spinner {
    border: 4px solid #f3f3f4;
    border-top: 4px solid #6750A4;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
    margin: 20px auto;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.completed-screen {
    text-align: center;
    padding: 40px 20px;
    background: #f6e6ff;
    border-radius: 12px;
    border: 2px solid #6750A4;
}
.recent-answers {
    max-height: 250px;
    overflow-y: auto;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 10px;
}
.answer-item {
    padding: 10px;
    margin: 5px 0;
    background: white;
    border-radius: 6px;
    font-size: 14px;
    border-left: 4px solid #ddd;
}
.answer-item.goed { border-left-color: #4CAF50; }
.answer-item.typfout { border-left-color: #2196F3; }
.answer-item.fout { border-left-color: #F44336; }
.answer-item.onbekend { border-left-color: #FF9800; }
.stable-input {
    position: relative;
}
/* New CSS for the logout button */
.logout-btn {
    position: fixed;
    top: 20px;
    right: 20px;
    background-color: #e74c3c; /* Rood */
    color: white;
    border: none;
    border-radius: 8px;
    padding: 10px 15px;
    font-size: 14px;
    font-family: 'Poppins', sans-serif; /* Google Font */
    cursor: pointer;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: background-color 0.3s;
    display: flex;
    align-items: center;
    gap: 8px; /* Ruimte tussen icoon en tekst */
    text-decoration: none;
}
.logout-btn:hover {
    background-color: #c0392b; /* Donkerder rood */
}
/* Stijl voor het Google icoon */
.logout-btn .material-symbols-outlined {
    font-size: 18px; /* Icoon grootte */
}
</style>

<script>
// Status tracking and updates secured by session on server
window.addEventListener('pagehide', function() {
    // notify server that tab is closed for this logged-in student
    navigator.sendBeacon('leerling_afgesloten.php');
});

function updateStatus(status) {
    // server uses session; do not send IDs client-side
    const data = new FormData();
    data.append('status', status);
    fetch('status_update.php', { method: 'POST', body: data }).catch(() => {});
}

document.addEventListener('DOMContentLoaded', () => updateStatus('actief'));
window.addEventListener('blur', () => updateStatus('non-actief'));
window.addEventListener('focus', () => updateStatus('actief'));
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        updateStatus('actief');
    } else {
        updateStatus('non-actief');
    }
});
window.addEventListener('beforeunload', () => {
    navigator.sendBeacon('status_update.php', new URLSearchParams({status: 'non-actief'}));
});
</script>
</head><body>

<div class="container">
    <?php if(!$leerling_id): ?>
        <div class="card" style="max-width:480px;margin:40px auto">
            <h2>Leerling: doe mee</h2>
            <?php if(!empty($error)) echo "<p style='color:red;'>".htmlspecialchars($error)."</p>"; ?>
            <form method="post">
                <label>Klascode</label><input name="join_klascode" required>
                <label>Naam</label><input name="naam" required>
                <div style="margin-top:12px"><button class="btn-primary" type="submit">Deelnemen</button></div>
            </form>
        </div>
    <?php else: ?>
        <a href="?logout=1" class="logout-btn">
            <span class="material-symbols-outlined">logout</span>
            Uitloggen
        </a>

        <div class="score-display">
            <h3><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#cca20c"><path d="M172-212h121v-296H172v296Zm247 0h122v-536H419v536Zm248 0h121v-216H667v216ZM46-86v-548h247v-240h374v320h247v468H46Z"/></svg> Jouw Score</h3>
            <div style="font-size: 24px; font-weight: bold; color: #6750A4;" id="score-display">
                Laden...
            </div>
            <p class="helper" id="answer-count">Laden...</p>
        </div>

        <div id="messages"></div>

        <div class="card">
            
            <div id="waiting-state" style="display: none;">
                <div class="waiting-screen">
                    <h3><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#F9DB78"><path d="M332-172h296v-108q0-62-43-105t-105-43q-62 0-105 43t-43 105v108Zm148-360q62 0 105-43t43-105v-108H332v108q0 62 43 105t105 43ZM126-46v-126h80v-108q0-57 23-109.5t65-90.5q-42-38-65-90.5T206-680v-108h-80v-126h708v126h-80v108q0 57-22.5 109.5T666-480q43 38 65.5 90.5T754-280v108h80v126H126Zm354-126Zm0-616Z"/></svg> Wachten op de volgende vraag</h3>
                    <p>De docent bereidt een nieuwe vraag voor...</p>
                    <div class="spinner"></div>
                </div>
            </div>

            <div id="question-state" style="display: none;">
                <div class="question-card">
                    <h3><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffff"><path d="M172-95q-35 8-59.5-17T95-172l41-199 235 235-199 41Zm199-41L136-371l457-457q37-37 89-37t89 37l57 57q37 37 37 89t-37 89L371-136Zm311-603L289-346l57 57 393-393-57-57Z"/></svg> Beantwoord de vraag:</h3>
                    <p id="question-text" style="font-size: 20px; font-weight: bold; margin: 20px 0;">...</p>
                </div>
                
                <div class="stable-input">
                    <div class="answer-form">
                        <label for="answer-input">Jouw antwoord:</label>
                        <input type="text" id="answer-input" placeholder="Typ hier je antwoord..." autocomplete="off" autocorrect="off" spellcheck="false">
                        <button id="submit-btn" onclick="submitAnswer()">Verstuur Antwoord</button>
                    </div>
                </div>
            </div>

            <div id="completed-state" style="display: none;">
                <div class="completed-screen">
                    <h3><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#8B7DBE"><path d="M212-212v-536 412-87 211Zm0 126q-53 0-89.5-36.5T86-212v-536q0-53 36.5-89.5T212-874h536q53 0 89.5 36.5T874-748v248H748v-248H212v536h224v126H212Zm481 40L518-222l88-88 87 87 173-173 89 88L693-46ZM320-440q17 0 28.5-11.5T360-480q0-17-11.5-28.5T320-520q-17 0-28.5 11.5T280-480q0 17 11.5 28.5T320-440Zm0-154q17 0 28.5-11.5T360-634q0-17-11.5-28.5T320-674q-17 0-28.5 11.5T280-634q0 17 11.5 28.5T320-594Zm120 154h240v-80H440v80Zm0-154h240v-80H440v80Z"></path></svg> Vraag beantwoord!</h3>
                    <p id="completed-question">...</p>
                    <p id="completed-subtitle" style="margin-top: 15px;">Je hebt deze vraag beantwoord. Wacht op beoordeling...</p>
                    <p id="reveal-answer" style="margin-top: 12px; font-weight: bold; display: none;">Juiste antwoord: <span id="reveal-answer-text"></span></p>
                </div>
            </div>

        </div>

        <div style="height:16px"></div>
        <div class="card">
            <h3><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#A96424"><path d="M212-86q-51.98 0-88.99-37.01T86-212v-536q0-51.97 37.01-88.99Q160.02-874 212-874h135q20-38 55.5-59t77.5-21q42 0 77.5 21t55.5 59h135q51.97 0 88.99 37.01Q874-799.97 874-748v536q0 51.98-37.01 88.99Q799.97-86 748-86H212Zm0-126h536v-536H212v536Zm80-80h265v-80H292v80Zm0-148h376v-80H292v80Zm0-148h376v-80H292v80Zm188-194q16.47 0 27.23-10.77Q518-803.53 518-820t-10.77-27.23Q496.47-858 480-858t-27.23 10.77Q442-836.47 442-820t10.77 27.23Q463.53-782 480-782ZM212-212v-536 536Z"/></svg> Jouw recente antwoorden</h3>
            <div class="recent-answers" id="recent-answers">
                <p style="text-align: center; color: #999;">Laden...</p>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php if($leerling_id): ?>
<script>
// Global state
let appState = {
    currentQuestionId: null,
    currentSessionId: null,
    isSubmitting: false,
    lastUpdate: null
};

// UI State Management - Never rebuild, just show/hide
function showWaitingState() {
    document.getElementById('waiting-state').style.display = 'block';
    document.getElementById('question-state').style.display = 'none';
    document.getElementById('completed-state').style.display = 'none';
}

function showQuestionState(questionText) {
    document.getElementById('question-text').textContent = questionText;
    document.getElementById('waiting-state').style.display = 'none';
    document.getElementById('question-state').style.display = 'block';
    document.getElementById('completed-state').style.display = 'none';
    
    // Reset form state
    const input = document.getElementById('answer-input');
    const button = document.getElementById('submit-btn');
    
    if (!appState.isSubmitting) {
        input.disabled = false;
        // Alleen de waarde wissen als de gebruiker niet aan het typen is
        if (document.activeElement !== input) {
            input.value = '';
        }
        button.disabled = false;
        button.textContent = 'Verstuur Antwoord';
        
        // Focus op invoer met een kleine vertraging
        setTimeout(() => {
            if (input && document.activeElement !== input && !input.value) {
                input.focus();
            }
        }, 300);
    }
}

function showCompletedState(questionText) {
    document.getElementById('completed-question').textContent = questionText;
    document.getElementById('waiting-state').style.display = 'none';
    document.getElementById('question-state').style.display = 'none';
    document.getElementById('completed-state').style.display = 'block';
    
    // Default subtitle and hide reveal until all answered
    const subtitle = document.getElementById('completed-subtitle');
    if (subtitle) subtitle.textContent = 'Je hebt deze vraag beantwoord. Wacht op beoordeling...';
    const reveal = document.getElementById('reveal-answer');
    if (reveal) reveal.style.display = 'none';
    
    // Lock the form
    const input = document.getElementById('answer-input');
    const button = document.getElementById('submit-btn');
    input.disabled = true;
    button.disabled = true;
    button.textContent = 'Verstuurd';
}

// Message system
function showMessage(text, type = 'info') {
    const container = document.getElementById('messages');
    const message = document.createElement('div');
    message.className = `status-message status-${type}`;
    message.textContent = text;
    
    // Clear previous messages
    container.innerHTML = '';
    container.appendChild(message);
    
    // Auto-remove after 4 seconds
    setTimeout(() => {
        if (message.parentNode) {
            message.remove();
        }
    }, 4000);
}

// Score updates - just text changes
function updateScore(score, answerCount) {
    document.getElementById('score-display').textContent = `${score} punten`;
    document.getElementById('answer-count').textContent = `${answerCount} vragen beantwoord`;
}

// Recent answers - only update if changed
let lastAnswersHash = '';
function updateRecentAnswers(answers) {
    const newHash = JSON.stringify(answers.map(a => a.id + a.status));
    if (newHash === lastAnswersHash) return;
    lastAnswersHash = newHash;
    
    const container = document.getElementById('recent-answers');
    
    if (!answers.length) {
        container.innerHTML = '<p style="text-align: center; color: #999;">Nog geen antwoorden.</p>';
        return;
    }
    
    const html = answers.map(answer => {
        const statusMap = {
            'goed': '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#53b806"><path d="M382-208 122-468l90-90 170 170 366-366 90 90-456 456Z"/></svg> Goed (+' + (answer.points || 10) + ' punten)',
            'typfout': '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#06a6b8"><path d="M564-48 379-233l83-84 102 102 214-214 83 84L564-48ZM100-320l199-520h127l199 520H499l-44-127H257l-44 127H100Zm187-214h140l-68-195h-4l-68 195Z"/></svg> Typfout (+' + (answer.points || 5) + ' punten)',
            'fout': '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#b82906"><path d="m256-168-88-88 224-224-224-224 88-88 224 224 224-224 88 88-224 224 224 224-88 88-224-224-224 224Z"/></svg> Fout (0 punten)',
            'onbekend': '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#F9DB78"><path d="M332-172h296v-108q0-62-43-105t-105-43q-62 0-105 43t-43 105v108Zm148-360q62 0 105-43t43-105v-108H332v108q0 62 43 105t105 43ZM126-46v-126h80v-108q0-57 23-109.5t65-90.5q-42-38-65-90.5T206-680v-108h-80v-126h708v126h-80v108q0 57-22.5 109.5T666-480q43 38 65.5 90.5T754-280v108h80v126H126Zm354-126Zm0-616Z"/></svg> Wordt nagekeken...'
        };
        
        return `
            <div class="answer-item ${answer.status}">
                <div style="font-weight: bold; margin-bottom: 5px;">${escapeHtml(answer.question)}</div>
                <div style="margin-bottom: 5px;">Jouw antwoord: <em>${escapeHtml(answer.answer)}</em></div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px;">
                    <span>${statusMap[answer.status] || '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#BB271A"><path d="M408-325q0-101 20-148.5t70-83.5q37-26 53.5-49.5T568-662q0-32-22-53.5T489-737q-42 0-70.5 26.5T378-640l-151-64q28-85 95-138.5T489-896q117 0 180 67.5T732-665q0 66-21.5 110T641-470q-45 38-56.5 63T573-325H408Zm81 283q-46 0-78-32t-32-78q0-46 32-78.5t78-32.5q46 0 78.5 32.5T600-152q0 46-32.5 78T489-42Z"/></svg> Onbekend'}</span>
                    <span style="color: #666;">${formatTime(answer.created_at)}</span>
                </div>
            </div>
        `;
    }).join('');
    
    container.innerHTML = html;
}

// Submit answer
function submitAnswer() {
    if (appState.isSubmitting) return;
    
    const input = document.getElementById('answer-input');
    const button = document.getElementById('submit-btn');
    const answer = input.value.trim();
    
    if (!answer) {
        showMessage('Vul je antwoord in!', 'error');
        input.focus();
        return;
    }
    
    if (!appState.currentSessionId || !appState.currentQuestionId) {
        showMessage('Geen actieve vraag.', 'error');
        return;
    }
    
    // Lock the form
    appState.isSubmitting = true;
    input.disabled = true;
    button.disabled = true;
    button.textContent = 'Versturen...';
    
    showMessage('Antwoord wordt verstuurd...', 'info');
    
    const formData = new FormData();
    formData.append('sessie_id', appState.currentSessionId);
    formData.append('vraag_id', appState.currentQuestionId);
    formData.append('antwoord', answer);
    formData.append('ajax', '1');
    
    fetch('submit_answer.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Antwoord verstuurd!', 'success');
            // Show completed state immediately
            showCompletedState(document.getElementById('question-text').textContent);
            // Trigger immediate update
            updateAppState();
        } else {
            showMessage(data.message || 'Fout bij versturen', 'error');
            // Unlock form on error
            appState.isSubmitting = false;
            input.disabled = false;
            button.disabled = false;
            button.textContent = 'Verstuur Antwoord';
        }
    })
    .catch(error => {
        console.error('Submit error:', error);
        showMessage('Netwerkfout. Probeer opnieuw.', 'error');
        // Unlock form on error
        appState.isSubmitting = false;
        input.disabled = false;
        button.disabled = false;
        button.textContent = 'Verstuur Antwoord';
    });
}

// Get state from server and update UI accordingly
function updateAppState() {
    fetch('get_student_state.php')
        .then(response => response.json())
        .then(data => {
            if (data.removed) {
                showMessage('Je bent verwijderd uit de sessie.', 'error');
                setTimeout(() => { window.location.href = 'index.php'; }, 1500);
                return;
            }
            if (data.session_ended) {
                showWaitingState();
                showMessage('De sessie is gestopt door de docent.', 'info');
                return;
            }
            if (data.error) {
                console.error('State error:', data.error);
                return;
            }
            
            // Update score (always safe)
            updateScore(data.score || 0, data.answer_count || 0);
            
            // Update recent answers (safe, separate container)
            updateRecentAnswers(data.recent_answers || []);
            
            // Handle main state changes
            const newQuestionId = data.current_question_id;
            const hasQuestionChanged = newQuestionId !== appState.currentQuestionId;
            
            if (hasQuestionChanged) {
                console.log('Question changed:', appState.currentQuestionId, '→', newQuestionId);
                appState.currentQuestionId = newQuestionId;
                appState.currentSessionId = data.session_id;
                appState.isSubmitting = false; // Reset on question change
            }
            
            // Show appropriate UI state
            if (!newQuestionId) {
                showWaitingState();
            } else if (data.already_answered) {
                showCompletedState(data.question_text);
            } else {
                showQuestionState(data.question_text);
            }

            // Update reveal state for completed answers
            if (newQuestionId && data.already_answered) {
                const subtitle = document.getElementById('completed-subtitle');
                const reveal = document.getElementById('reveal-answer');
                const revealText = document.getElementById('reveal-answer-text');
                if (data.all_answered && data.correct_answer) {
                    if (revealText) revealText.textContent = data.correct_answer;
                    if (reveal) reveal.style.display = 'block';
                    if (subtitle) subtitle.textContent = 'Alle antwoorden binnen! Dit is het juiste antwoord:';
                } else {
                    if (reveal) reveal.style.display = 'none';
                    if (subtitle) subtitle.textContent = 'Je hebt deze vraag beantwoord. Wacht op beoordeling...';
                }
            }
            
        })
        .catch(error => {
            console.error('Update error:', error);
        });
}

// Utilities
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function formatTime(dateString) {
    return new Date(dateString).toLocaleTimeString('nl-NL', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    console.log('Welkom bij Overhoorder!');
    
    // Initial state
    updateAppState();
    
    // Iets lagere frequentie om DB-load te verlagen
    setInterval(updateAppState, 6000);
    
    // Handle Enter key in input
    document.getElementById('answer-input').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            submitAnswer();
        }
    });
});
</script>
<?php endif; ?>

</body></html>