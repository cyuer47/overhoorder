<?php
require_once __DIR__ . '/php/db.php';

$error = '';
$message = '';
$show_form = false;
$token = $_GET['token'] ?? '';

if ($token) {
    $stmt = $pdo->prepare('SELECT id, reset_token_expiry FROM docenten WHERE reset_token = ?');
    $stmt->execute([$token]);
    $docent = $stmt->fetch();

    if ($docent) {
        $expiry_datetime = new DateTime($docent['reset_token_expiry']);
        $current_datetime = new DateTime();

        if ($current_datetime <= $expiry_datetime) {
            $show_form = true;
        } else {
            $error = 'Deze herstellink is verlopen.';
        }
    } else {
        $error = 'Ongeldige herstellink.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $token_post = $_POST['token'];

    if ($new_password !== $confirm_password) {
        $error = 'Wachtwoorden komen niet overeen.';
    } else if (strlen($new_password) < 6) {
        $error = 'Het wachtwoord moet minstens 6 tekens lang zijn.';
    } else {
        $stmt = $pdo->prepare('SELECT id, reset_token_expiry FROM docenten WHERE reset_token = ?');
        $stmt->execute([$token_post]);
        $docent = $stmt->fetch();

        if ($docent) {
            $expiry_datetime = new DateTime($docent['reset_token_expiry']);
            $current_datetime = new DateTime();

            if ($current_datetime <= $expiry_datetime) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE docenten SET wachtwoord = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?');
                $stmt->execute([$hashed_password, $docent['id']]);
                $message = 'Je wachtwoord is succesvol gewijzigd. Je kunt nu inloggen.';
                $show_form = false;
            } else {
                $error = 'Deze herstellink is verlopen. Vraag een nieuwe aan.';
            }
        } else {
            $error = 'Ongeldige herstellink.';
        }
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Wachtwoord Herstellen</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="icon" type="image/x-icon" href="https://overhoren.ivenboxem.nl/assets/img/logo2.png">
<script>
    !function(t,e){var o,n,p,r;e.__SV||(window.posthog && window.posthog.__loaded)||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="init Ce js Ls Te Fs Ds capture Ye calculateEventProperties zs register register_once register_for_session unregister unregister_for_session Ws getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSurveysLoaded onSessionId getSurveys getActiveMatchingSurveys renderSurvey displaySurvey canRenderSurvey canRenderSurveyAsync identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty Bs Us createPersonProfile Hs Ms Gs opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing get_explicit_consent_status is_capturing clear_opt_in_out_capturing Ns debug L qs getPageViewId captureTraceFeedback captureTraceMetric".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
    posthog.init('phc_bjbHSOWX3nTZKHLlw7SSkutWkjSfTV38ewAcRmxPsYN', {
        api_host: 'https://eu.i.posthog.com',
        defaults: '2025-05-24',
        person_profiles: 'always', // or 'always' to create profiles for anonymous users as well
    })
</script>
</head>
<body>
<div class="container" style="max-width:480px; margin: 40px auto;">
    <div class="card">
        <h2>Wachtwoord Herstellen</h2>
        <?php if (!empty($error)) echo '<p style="color:red;">' . htmlspecialchars($error) . '</p>'; ?>
        <?php if (!empty($message)) echo '<p style="color:green;">' . htmlspecialchars($message) . '</p>'; ?>

        <?php if ($show_form): ?>
            <form method="post">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <label>Nieuw wachtwoord</label>
                <input type="password" name="new_password" required>
                <label>Bevestig nieuw wachtwoord</label>
                <input type="password" name="confirm_password" required>
                <div style="margin-top:12px">
                    <button class="btn-primary" type="submit">Wachtwoord wijzigen</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>