<?php
require_once 'config.php';
require_once 'vendor/autoload.php';
require_once 'php/db.php';
session_start();

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (!isset($token['error'])) {
        $client->setAccessToken($token['access_token']);

        $google_oauth = new Google_Service_Oauth2($client);
        $user = $google_oauth->userinfo->get();

        $email = $user->email;
        $naam = $user->name;

        // Check of docent al bestaat
        $stmt = $pdo->prepare('SELECT * FROM docenten WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $doc = $stmt->fetch();

        if (!$doc) {
            $stmt = $pdo->prepare('INSERT INTO docenten (naam, email, wachtwoord) VALUES (?, ?, ?)');
            $stmt->execute([$naam, $email, 'GOOGLE']);
            $docId = $pdo->lastInsertId();
        } else {
            $docId = $doc['id'];
            $naam = $doc['naam'];
        }

        $_SESSION['docent_id'] = $docId;
        $_SESSION['docent_naam'] = $naam;

        header('Location: dashboard.php');
        exit;
    } else {
        echo "Fout bij Google-login.";
    }
} else {
    header('Location: login.php');
    exit;
}
