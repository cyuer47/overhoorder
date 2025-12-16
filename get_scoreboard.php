<?php
require_once __DIR__ . '/php/db.php';
session_start();

if (empty($_SESSION['docent_id']) || empty($_SESSION['klas_id'])) {
    exit;
}
$sessie_id = $_GET['sessie_id'] ?? null;
if (!$sessie_id) exit;
$klas_id = $_SESSION['klas_id'];

$stmt = $pdo->prepare('
    SELECT l.id, l.naam as leerling_naam, COALESCE(SUM(r.points), 0) as total_points, COUNT(r.id) as total_answers
    FROM leerlingen l
    LEFT JOIN resultaten r ON l.id = r.leerling_id AND r.sessie_id = ?
    WHERE l.klas_id = ?
    GROUP BY l.id, l.naam
    ORDER BY total_points DESC, l.naam ASC
');
$stmt->execute([$sessie_id, $klas_id]);
$student_scores = $stmt->fetchAll();

if(empty($student_scores)): ?>
    <p class="helper">Nog geen scores beschikbaar.</p>
<?php else: ?>
    <?php foreach($student_scores as $score): ?>
        <div class="score-item" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="flex-grow: 1;">
                <strong><?=htmlspecialchars($score['leerling_naam'])?></strong>
                <form method="post" action="vraag_handler.php?sessie=<?= urlencode($sessie_id) ?>" onsubmit="return confirm('Weet je zeker dat je <?= htmlspecialchars($score['leerling_naam']) ?> wilt verwijderen? Dit kan niet ongedaan gemaakt worden.');" style="display: inline; margin-left: 10px;">
                    <input type="hidden" name="leerling_id" value="<?= htmlspecialchars($score['id']) ?>">
                    <button type="submit" name="delete_student" class="btn-danger" style="padding: 4px 8px; font-size: 12px; border-radius: 4px;">Verwijderen</button>
                </form>
            </div>
            <span>
                <strong><?=htmlspecialchars($score['total_points'])?> punten</strong>
                <small>(<?=htmlspecialchars($score['total_answers'])?> antwoorden)</small>
            </span>
        </div>
    <?php endforeach; ?>
<?php endif; ?>