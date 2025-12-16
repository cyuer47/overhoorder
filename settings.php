<?php
session_start();
require_once __DIR__ . '/php/db.php';

if (empty($_SESSION['docent_id'])) {
    header('Location: login.php?redirecturi=' . urlencode(basename($_SERVER['PHP_SELF'])));
    exit;
}

$docent_id = $_SESSION['docent_id'];
$docent_naam = $_SESSION['docent_naam'] ?? 'Docent';

// Huidige gegevens ophalen
$stmt = $pdo->prepare("SELECT naam, email, bio, vakken, is_public, avatar FROM docenten WHERE id = ?");
$stmt->execute([$docent_id]);
$docent = $stmt->fetch(PDO::FETCH_ASSOC);

$docent_naam = $docent['naam'];
$email = $docent['email'];
$bio = $docent['bio'];
$vakken = $docent['vakken'];
$is_public = $docent['is_public'];
$current_avatar = $docent['avatar'];

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $new_name = trim($_POST['naam']);
    $new_email = trim($_POST['email']);
    $new_password = trim($_POST['wachtwoord']);
    $new_bio = trim($_POST['bio']);
    $new_vakken = trim($_POST['vakken']);
    $new_is_public = isset($_POST['is_public']) ? 1 : 0;

    // Alles updaten in één query behalve avatar (die kan apart komen)
    $sql = "UPDATE docenten SET naam = ?, email = ?, bio = ?, vakken = ?, is_public = ? WHERE id = ?";
    $pdo->prepare($sql)->execute([$new_name, $new_email, $new_bio, $new_vakken, $new_is_public, $docent_id]);

    $_SESSION['docent_naam'] = $new_name;

    if ($new_password) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE docenten SET wachtwoord = ? WHERE id = ?")->execute([$hashed, $docent_id]);
    }

    // Profielfoto upload
    if (!empty($_FILES['avatar']['name'])) {
        $allowed = ['jpg','jpeg','png','gif'];
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed) && $_FILES['avatar']['size'] <= 2*1024*1024) {
            $new_filename = 'avatar_' . $docent_id . '.' . $ext;
            $upload_dir = __DIR__ . '/uploads/avatars/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                $pdo->prepare("UPDATE docenten SET avatar = ? WHERE id = ?")->execute([$new_filename, $docent_id]);
                $current_avatar = $new_filename;
                $message = "Instellingen en profielfoto succesvol bijgewerkt!";
            } else {
                $message = "Fout bij uploaden van profielfoto.";
            }
        } else {
            $message = "Ongeldig bestandstype of te groot (max 2MB).";
        }
    }

    if (!$message) $message = "Instellingen succesvol bijgewerkt!";
}
?>

<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>Instellingen</title>
<link rel="stylesheet" href="assets/css/styles.css">
<link rel="icon" type="image/x-icon" href="https://overhoren.ivenboxem.nl/assets/img/logo2.png">
<style>
.avatar-section {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.avatar-section img {
    width: 96px;
    height: 96px;
    border-radius: 12px;
    border: 3px solid #7C4DFF; /* Material 3 Expressive paars halo */
    padding: 4px;
    background: #fff;
}

form label {
    display: block;
    margin-top: 12px;
    font-weight: 500;
}

form input {
    width: 100%;
    padding: 8px 12px;
    margin-top: 4px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 14px;
}

form .btn-primary, form .btn-secondary {
    margin-top: 16px;
}

.alert-success {
    background-color: #EDE7F6;
    border-left: 4px solid #7C4DFF;
    padding: 12px;
    margin-bottom: 16px;
    border-radius: 8px;
}
</style>
</head>
<body>
<div class="container">

    <div class="page-header">
        <h1>Instellingen</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card profile-card">
        <form method="post" enctype="multipart/form-data">

            <div class="avatar-section">
                <img id="avatarPreview" src="get_avatar.php?file=<?= htmlspecialchars($current_avatar ?? '') ?>&seed=<?= urlencode($docent_naam) ?>&size=96" alt="Avatar">
                <div>
                    <label>Upload nieuwe profielfoto</label>
                    <input type="file" name="avatar" accept="image/*" id="avatarInput">
                </div>
            </div>

            <label>Naam</label>
            <input type="text" name="naam" value="<?= htmlspecialchars($docent_naam) ?>" required>

            <label>E-mail</label>
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>">

            <label>Nieuw wachtwoord <small>(leeg laten als je niets wilt wijzigen)</small></label>
            <input type="password" name="wachtwoord">

            <label>Over mij (bio)</label>
            <textarea name="bio" rows="4"><?= htmlspecialchars($bio) ?></textarea>

            <label>Vakken / Specialisaties</label>
            <textarea name="vakken" rows="2"><?= htmlspecialchars($vakken) ?></textarea>

            <label>
              <input type="checkbox" name="is_public" value="1" <?= $is_public ? 'checked' : '' ?>>
              Profiel openbaar maken
            </label>


            <div>
                <button class="btn-primary" type="submit">Opslaan</button>
                <a href="profile.php" class="btn-secondary">Annuleren</a>
            </div>

        </form>
    </div>

</div>

<script>
const avatarInput = document.getElementById('avatarInput');
const avatarPreview = document.getElementById('avatarPreview');

avatarInput.addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            avatarPreview.src = e.target.result;
        }
        reader.readAsDataURL(file);
    } else {
        avatarPreview.src = 'get_avatar.php?file=<?= htmlspecialchars($current_avatar ?? '') ?>&seed=<?= urlencode($docent_naam) ?>&size=96';
    }
});
</script>
</body>
</html>
