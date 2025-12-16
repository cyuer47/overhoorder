<?php
session_start();
require_once __DIR__ . '/php/db.php';

// Redirect als al ingelogd
if (!empty($_SESSION['docent_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $naam = trim($_POST['naam'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $wachtwoord = $_POST['wachtwoord'] ?? '';
    $wachtwoord2 = $_POST['wachtwoord2'] ?? '';
    $recaptcha = $_POST['g-recaptcha-response'] ?? '';

    $secretKey = '6LecuOIrAAAAAJML5qHYZHpAIiOuyIploZMPuikg';
    $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$recaptcha}");
    $captchaSuccess = json_decode($verify);

    if (empty($captchaSuccess->success)) {
        $error = 'Bevestig dat je geen robot bent.';
    } elseif (empty($naam) || empty($email) || empty($wachtwoord)) {
        $error = 'Vul alle velden in.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ongeldig e-mailadres.';
    } elseif ($wachtwoord !== $wachtwoord2) {
        $error = 'Wachtwoorden komen niet overeen.';
    } else {
        // Check of e-mail al bestaat
        $stmt = $pdo->prepare('SELECT id FROM docenten WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Er bestaat al een account met dit e-mailadres.';
        } else {
            // Account aanmaken
            $hash = password_hash($wachtwoord, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO docenten (naam, email, wachtwoord) VALUES (?, ?, ?)');
            $stmt->execute([$naam, $email, $hash]);

            $_SESSION['docent_id'] = $pdo->lastInsertId();
            $_SESSION['docent_naam'] = $naam;

            header('Location: dashboard.php');
            exit;
        }
    }
}
?>

<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <title>Registreren</title>
  <link rel="stylesheet" href="assets/css/styles.css">
  <link rel="icon" type="image/x-icon" href="https://overhoren.ivenboxem.nl/assets/img/logo2.png">
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
<div class="container card" style="max-width:460px;margin:40px auto">
  <h2>Docent registreren</h2>

  <?php if(!empty($error)) echo "<p style='color:red;'>".htmlspecialchars($error)."</p>"; ?>

  <form method="post">
    <label>Naam</label>
    <input type="text" name="naam" required>

    <label>Email</label>
    <input type="email" name="email" required>

    <label>Wachtwoord</label>
    <input type="password" name="wachtwoord" required>

    <label>Herhaal wachtwoord</label>
    <input type="password" name="wachtwoord2" required>

    <div class="g-recaptcha" data-sitekey="6LecuOIrAAAAAGsxiTqfjzqtFNkvf8x7usai3HkO" style="margin-top:12px;"></div>

    <div style="margin-top:12px">
      <button class="btn-primary" type="submit">Account aanmaken</button>
    </div>
  </form>

  <p class="helper">Al een account? <a href="login.php">Log in</a></p>
</div>
</body>
</html>
