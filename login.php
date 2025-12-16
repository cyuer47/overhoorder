<?php
session_start(); // Zorg dat sessies gestart worden

// Check of de gebruiker al ingelogd is
if (!empty($_SESSION['docent_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/php/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pwd = $_POST['wachtwoord'] ?? '';
    $recaptcha = $_POST['g-recaptcha-response'] ?? '';

    $secretKey = '6LecuOIrAAAAAJML5qHYZHpAIiOuyIploZMPuikg';
    $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$recaptcha}");
    $captchaSuccess = json_decode($verify);

    if (empty($captchaSuccess->success)) {
        $error = 'Bevestig dat je geen robot bent.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM docenten WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $doc = $stmt->fetch();
        if ($doc && password_verify($pwd, $doc['wachtwoord'])) {
            $_SESSION['docent_id'] = $doc['id'];
            $_SESSION['docent_naam'] = $doc['naam'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Onjuiste gegevens';
        }
    }
}
?>



<!doctype html><html lang="nl"><head><meta charset="utf-8"><script>
    !function(t,e){var o,n,p,r;e.__SV||(window.posthog && window.posthog.__loaded)||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="init Ce js Ls Te Fs Ds capture Ye calculateEventProperties zs register register_once register_for_session unregister unregister_for_session Ws getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSurveysLoaded onSessionId getSurveys getActiveMatchingSurveys renderSurvey displaySurvey canRenderSurvey canRenderSurveyAsync identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty Bs Us createPersonProfile Hs Ms Gs opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing get_explicit_consent_status is_capturing clear_opt_in_out_capturing Ns debug L qs getPageViewId captureTraceFeedback captureTraceMetric".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
    posthog.init('phc_bjbHSOWX3nTZKHLlw7SSkutWkjSfTV38ewAcRmxPsYN', {
        api_host: 'https://eu.i.posthog.com',
        defaults: '2025-05-24',
        person_profiles: 'always', // or 'always' to create profiles for anonymous users as well
    })
</script><title>Login</title><link rel="stylesheet" href="assets/css/styles.css"><link rel="icon" type="image/x-icon" href="https://overhoren.ivenboxem.nl/assets/img/logo2.png"></head><body>

<div class="container card" style="max-width:420px;margin:40px auto">

  <h2>Docent inloggen</h2>
  <?php if(!empty($error)) echo "<p style='color:red;'>".htmlspecialchars($error)."</p>"; ?>
  <form method="post">
    <label>Email</label><input name="email" type="email" required>
    <label>Wachtwoord</label><input name="wachtwoord" type="password" required>

    <div class="g-recaptcha" data-sitekey="6LecuOIrAAAAAGsxiTqfjzqtFNkvf8x7usai3HkO" style="margin-top:12px;"></div>

    <div style="margin-top:12px"><button class="btn-primary" type="submit">Inloggen</button></div>


  </form>
  <p class="helper">Wachtwoord vergeten? <a href="wachtwoord_vergeten.php">Herstel wachtwoord</a></p>
  <p class="helper">Nog geen account? <a href="register.php">Registreer</a></p>
</div>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
</body></html>
