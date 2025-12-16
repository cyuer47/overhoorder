<?php
require_once __DIR__ . '/php/db.php';
session_start();

if (empty($_SESSION['docent_id']) || empty($_SESSION['klas_id'])) {
    exit;
}
$sessie_id = $_GET['sessie_id'] ?? null;
if (!$sessie_id) exit;

$stmt = $pdo->prepare('
    SELECT r.*, l.naam as leerlingNaam, v.vraag, v.antwoord as correct_antwoord, v.id as vraag_id 
    FROM resultaten r 
    JOIN leerlingen l ON l.id=r.leerling_id 
    JOIN vragen v ON v.id=r.vraag_id 
    WHERE r.sessie_id = ? 
    ORDER BY 
    CASE r.status
        WHEN "onbekend" THEN 1
        WHEN "fout" THEN 2
        WHEN "typfout" THEN 3
        WHEN "goed" THEN 4
        ELSE 5
    END
    LIMIT 50
');
$stmt->execute([$sessie_id]);
$records = $stmt->fetchAll();

if(empty($records)): ?>
    <p class="helper">Nog geen antwoorden beschikbaar.</p>
<?php else: ?>
    <table class="table">
        <thead><tr><th>Leerling</th><th>Vraag</th><th>Antwoord</th><th>Status</th><th>Punten</th></tr></thead>
        <tbody>
            <?php foreach($records as $r): ?>
                <tr>
                    <td><?=htmlspecialchars($r['leerlingNaam'])?></td>
                    <td><?=htmlspecialchars($r['vraag'])?></td>
                    <td><?=htmlspecialchars($r['antwoord_given'])?></td>
                    <td>
                        <?php
                        switch ($r['status']) {
                            case 'goed':
                                echo '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#53b806"><path d="M382-208 122-468l90-90 170 170 366-366 90 90-456 456Z"/></svg>';
                                break;
                            case 'typfout':
                                echo '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#06a6b8"><path d="M564-48 379-233l83-84 102 102 214-214 83 84L564-48ZM100-320l199-520h127l199 520H499l-44-127H257l-44 127H100Zm187-214h140l-68-195h-4l-68 195Z"/></svg>';
                                break;
                            case 'fout':
                                echo '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#b82906"><path d="m256-168-88-88 224-224-224-224 88-88 224 224 224-224 88 88-224 224 224 224-88 88-224-224-224 224Z"/></svg>';
                                break;
                            default:
                                $student_answer_js = htmlspecialchars($r['antwoord_given'], ENT_QUOTES, 'UTF-8');
                                $correct_answer_js = htmlspecialchars($r['correct_antwoord'], ENT_QUOTES, 'UTF-8');
                                echo '<p onclick="openModal(' . $r['id'] . ', \'' . $student_answer_js . '\', \'' . $correct_answer_js . '\')" style="border:none; background:none; font-size: 20px; cursor: pointer;"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#fcba03"><path d="M417-385v-406h126v406H417Zm0 216v-126h126v126H417Z"/></svg></p>';
                        }
                        ?>
                    </td>
                    <td><strong><?=htmlspecialchars($r['points'] ?? '0')?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>