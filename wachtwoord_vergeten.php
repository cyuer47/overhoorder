<?php
require_once __DIR__ . '/php/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ongeldig e-mailadres.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM docenten WHERE email = ?');
        $stmt->execute([$email]);
        $docent = $stmt->fetch();

        if ($docent) {
            // Genereer een unieke token en stel de vervaldatum in (bijv. 1 uur)
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Sla de token op in de database
            $stmt = $pdo->prepare('UPDATE docenten SET reset_token = ?, reset_token_expiry = ? WHERE id = ?');
            $stmt->execute([$token, $expiry, $docent['id']]);

            // Creëer de herstellink
            $reset_link = "https://overhoren.ivenboxem.nl/v4/wachtwoord_herstellen.php?token=$token";

            // Verstuur de e-mail met HTML-opmaak
            $subject = "Wachtwoord herstellen voor Overhoorder";
            $headers = "From: Overhoorder <no-reply@ivenboxem.nl>\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

            $body = '
<html>
<head>
  <style>
    body { font-family: Arial, sans-serif; background-color: #f4f4f4; color: #333; }
    .email-container { max-width: 600px; margin: 20px auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); text-align: center;}
    .button { display: inline-block; padding: 10px 20px; font-size: 16px; background-color: #007BFF; text-decoration: none; border-radius: 5px; }
  </style>
</head>
<body>
  <div class="email-container">
    <img src="https://overhoren.ivenboxem.nl/assets/img/logo.png" alt="Overhoorder Logo" style="max-width: 150px; height: auto; display: block; margin: 0 auto 20px;">
    <h2>Wachtwoord Herstellen</h2>
    <p>Hallo,</p>
    <p>Je hebt een verzoek ingediend om je wachtwoord te herstellen.</p>
    <p>Klik op de onderstaande knop om een nieuw wachtwoord in te stellen:</p>
    <p style="text-align: center;"><a href="' . htmlspecialchars($reset_link) . '" class="button" style="color: #fff !important;">Wachtwoord Herstellen</a></p>
    <p>Of kopieer en plak de volgende link in je browser:</p>
    <p><a href="' . htmlspecialchars($reset_link) . '">' . htmlspecialchars($reset_link) . '</a></p>
    <p>Deze link vervalt over 1 uur.</p>
    <p>Met vriendelijke groet,<br>Iven Boxem</p>
  </div>
</body>
</html>';

            if (mail($email, $subject, $body, $headers)) {
                $message = 'Een herstellink is naar je e-mailadres verstuurd.';
            } else {
                $error = 'Fout bij het versturen van de e-mail. Neem contact op met de beheerder.';
            }
        } else {
            $error = 'Geen gebruiker gevonden met dit e-mailadres.';
        }
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Wachtwoord Vergeten</title>
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
    <a class="btn-text" href="login.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#2B225C"><path d="m368-417 202 202-90 89-354-354 354-354 90 89-202 202h466v126H368Z"/></svg></a>
    <div class="card">
        <h2>Wachtwoord Vergeten</h2>
        <?php if (!empty($error)) echo '<p style="color:red;">' . htmlspecialchars($error) . '</p>'; ?>
        <?php if (!empty($message)) echo '<p style="color:green;">' . htmlspecialchars($message) . '</p>'; ?>
        <form method="post">
            <label>E-mailadres</label>
            <input type="email" name="email" required>
            <div style="margin-top:12px">
                <button class="btn-primary" type="submit">Verstuur herstellink</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>