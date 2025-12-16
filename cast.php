<?php
require_once __DIR__ . '/php/db.php';
session_start();

$sessie_id = $_GET['sessie'] ?? null;
if (!$sessie_id) {
    echo "Geen sessie geselecteerd.";
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT s.*, k.naam as klasnaam, k.klascode FROM sessies s JOIN klassen k ON k.id = s.klas_id WHERE s.id = ?');
    $stmt->execute([$sessie_id]);
    $sessie_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sessie_data) {
        echo "Sessie niet gevonden.";
        exit;
    }

    $current_question_id = $sessie_data['current_question_id'];
    $klas_code = htmlspecialchars($sessie_data['klascode']);
    
    // Determine which content to show based on URL parameters and session state
    $content_to_show = 'instructions';
    if (isset($_GET['status']) && $_GET['status'] === 'final_scoreboard') {
        $content_to_show = 'final_scoreboard';
    } else if ($current_question_id) {
        $content_to_show = 'question';
    } else {
        // If there's no active question, check if there are results to show a live scoreboard
        $stmt_results = $pdo->prepare('SELECT COUNT(*) FROM resultaten WHERE sessie_id = ?');
        $stmt_results->execute([$sessie_id]);
        if ($stmt_results->fetchColumn() > 0) {
            $content_to_show = 'scoreboard';
        }
    }

    // Helper for counts and reveal state
    $all_answered = false;
    $answered_count = 0;
    $expected_total = 0;
    $correct_answer_cast = null;
    if (!empty($current_question_id)) {
        // Count distinct answered
        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT leerling_id) FROM resultaten WHERE sessie_id = ? AND vraag_id = ?');
        $stmt->execute([$sessie_id, $current_question_id]);
        $answered_count = (int)$stmt->fetchColumn();

        // Expected respondents: same heuristic as student state
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM leerlingen WHERE klas_id = ? AND (status IN ('actief','non-actief') OR (last_seen IS NOT NULL AND TIMESTAMPDIFF(SECOND, last_seen, NOW()) <= 15))");
        $stmt->execute([$sessie_data['klas_id']]);
        $expected_total = (int)$stmt->fetchColumn();
        $all_answered = ($expected_total > 0 && $answered_count >= $expected_total);

        if ($all_answered) {
            $stmt = $pdo->prepare('SELECT antwoord FROM vragen WHERE id=?');
            $stmt->execute([$current_question_id]);
            $correct_answer_cast = $stmt->fetchColumn();
        }
    }

} catch (PDOException $e) {
    $content_to_show = 'error';
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="nl">
  <head>
    <meta charset="utf-8" />
    <title>Overhoorder Cast</title>
    <link
      rel="icon"
      type="image/x-icon"
      href="https://overhoren.ivenboxem.nl/assets/img/logo2.png"
    />
    <link rel="preconnect" href="https://rsms.me/" />
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
<script>
    !function(t,e){var o,n,p,r;e.__SV||(window.posthog && window.posthog.__loaded)||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="init Ce js Ls Te Fs Ds capture Ye calculateEventProperties zs register register_once register_for_session unregister unregister_for_session Ws getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSurveysLoaded onSessionId getSurveys getActiveMatchingSurveys renderSurvey displaySurvey canRenderSurvey canRenderSurveyAsync identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty Bs Us createPersonProfile Hs Ms Gs opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing get_explicit_consent_status is_capturing clear_opt_in_out_capturing Ns debug L qs getPageViewId captureTraceFeedback captureTraceMetric".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
    posthog.init('phc_bjbHSOWX3nTZKHLlw7SSkutWkjSfTV38ewAcRmxPsYN', {
        api_host: 'https://eu.i.posthog.com',
        defaults: '2025-05-24',
        person_profiles: 'always', // or 'always' to create profiles for anonymous users as well
    })
</script>
    <style>
      body {
        font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont,
          "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans",
          "Helvetica Neue", sans-serif;
        background: linear-gradient(135deg, #f5f2ff, #ffe6f1);
        color: #231918;
        text-align: center;
        padding: 20px;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        margin: 0;
      }

      .content-container {
        background: #fffbfe;
        padding: 50px;
        border-radius: 25px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        width: 100%;
      }

      .podium-container {
        display: flex;
        justify-content: center;
        align-items: flex-end;
        gap: 20px;
        width: 100%;
        height: 400px;
        margin-top: 40px;
      }

      .podium-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
        padding: 20px 10px;
        border-radius: 10px 10px 0 0;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        position: relative;
        animation: fadeIn 1s ease-in-out forwards;
      }

      .podium-item span,
      .podium-score,
      .podium-rank {
        color: #fff; /* Alleen podium heeft witte tekst */
        text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.6);
      }

      .podium-item span {
        font-size: 1.5rem;
        margin-bottom: 10px;
        font-weight: bold;
      }

      .podium-score {
        font-size: 2.5rem;
        font-weight: bold;
      }

      .podium-rank {
        position: absolute;
        top: -50px;
        font-size: 3.5rem;
        font-weight: bold;
        translate: 0 -8px;
      }

      .first {
        background: linear-gradient(#ffd700, #ffc700);
        height: 100%;
      }
      .second {
        background: linear-gradient(#c0c0c0, #b0b0b0);
        height: 80%;
      }
      .third {
        background: linear-gradient(#cd7f32, #b96a19);
        height: 60%;
      }

      .score-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        border-bottom: 2px solid rgba(0, 0, 0, 0.1);
        font-size: 1.8rem;
        font-weight: bold;
        background: #f9f9f9;
        border-radius: 10px;
        margin: 8px 0;
      }

      .chip {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        padding: 16px 24px;
        border-radius: 3rem;
        background: #eaddff;
        color: #21005e;
        font-weight: 600;
      }

      .chip-small {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        background: #f5f1ff;
        font-size: 1.25rem;
        translate: 0 -36px;
      }

      p {
        margin: 0;
        font-weight: 450;
        letter-spacing: 0.1px;
      }

      h1 {
        font-size: 3rem;
        margin: 0;
      }

      @keyframes fadeIn {
        from {
          opacity: 0;
          transform: translateY(50px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      @keyframes dropIn {
        from {
          opacity: 0;
          transform: translateY(-50px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
    </style>
  </head>
  <body>
    <div class="content-container">
      <?php if ($content_to_show === 'instructions'): ?>
      <p class="chip-small">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          height="24px"
          viewBox="0 -960 960 960"
          width="24px"
          fill="#21005e"
        >
          <path
            d="M720-183v49q0 17-11.5 28.5T680-94q-17 0-28.5-11.5T640-134v-126q0-25 17.5-42.5T700-320h126q17 0 28.5 11.5T866-280q0 17-11.5 28.5T826-240h-50l90 90q11 11 11 27.5T866-94q-12 12-28.5 12T809-94l-89-89ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 10-.5 22t-1.5 22q-2 17-14 26.5t-30 9.5q-16 0-27-14t-9-30q2-10 2-18v-18q0-20-2.5-40t-7.5-40H654q3 20 4.5 40t1.5 40v21.5q0 11.5-1 21.5-2 17-14 27t-29 10q-16 0-27.5-13t-9.5-29q1-10 1-19v-19q0-20-1.5-40t-4.5-40H386q-3 20-4.5 40t-1.5 40q0 20 1.5 40t4.5 40h94q17 0 28.5 11.5T520-360q0 17-11.5 28.5T480-320h-76q12 43 31 82.5t45 75.5q10 0 20 .5t20-.5q17-2 28 8.5t11 27.5q0 18-9 30t-26 14q-10 1-22 1.5t-22 .5ZM170-400h136q-3-20-4.5-40t-1.5-40q0-20 1.5-40t4.5-40H170q-5 20-7.5 40t-2.5 40q0 20 2.5 40t7.5 40Zm206 222q-18-34-31.5-69.5T322-320H204q29 51 73 87.5t99 54.5ZM204-640h118q9-37 22.5-72.5T376-782q-55 18-99 54.5T204-640Zm200 0h152q-12-43-31-82.5T480-798q-26 36-45 75.5T404-640Zm234 0h118q-29-51-73-87.5T584-782q18 34 31.5 69.5T638-640Z"
          />
        </svg>
        overhoren.ivenboxem.nl/leerling
      </p>
      <div class="chip">
        <span style="font-size: 2rem">Klassencode:</span>
        <span style="font-size: 3.5rem; font-weight: bold; color: #2b225c"
          ><?= $klas_code ?></span
        >
      </div>
      <p style="font-size: 1.5rem; margin-top: 25px">
        De overhoring start zodra de docent een vraag verstuurt.
      </p>
      <?php elseif ($content_to_show === 'question'): 
        $stmt = $pdo->prepare('SELECT vraag FROM vragen WHERE id=?');
      $stmt->execute([$current_question_id]); $question_text =
      $stmt->fetchColumn(); ?>
      <h1 style="font-size: 3.5rem">Vraag!</h1>
      <div
        style="
          background-color: white;
          padding: 30px;
          border-radius: 20px;
          box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        "
      >
        <p style="font-size: 3rem; font-weight: bold">
          <?= htmlspecialchars($question_text) ?>
        </p>
        <?php if ($all_answered && $correct_answer_cast !== null): ?>
          <p style="font-size: 2rem; margin-top: 20px; color: #2b225c; font-weight: 700;">Juiste antwoord: <?= htmlspecialchars($correct_answer_cast) ?></p>
          <p style="font-size: 1.25rem; color: #5b547f;">Alle antwoorden zijn binnen.</p>
        <?php else: ?>
          <p style="font-size: 1.5rem">Beantwoord de vraag op je eigen scherm.</p>
          <p style="font-size: 1.25rem; color: #5b547f; margin-top: 8px;">Antwoorden: <?= (int)$answered_count ?><?= $expected_total ? " / " . (int)$expected_total : '' ?></p>
        <?php endif; ?>
      </div>
      <?php elseif ($content_to_show === 'scoreboard'): 
        $stmt = $pdo->prepare(' SELECT l.naam, COALESCE(SUM(r.points), 0) as
      total_points FROM leerlingen l LEFT JOIN resultaten r ON l.id =
      r.leerling_id AND r.sessie_id = ? WHERE l.klas_id = ? GROUP BY l.id,
      l.naam ORDER BY total_points DESC, l.naam ASC ');
      $stmt->execute([$sessie_id, $sessie_data['klas_id']]); $scores =
      $stmt->fetchAll(PDO::FETCH_ASSOC); ?>
      <h1 style="font-size: 3.5rem">Scorebord</h1>
      <div
        style="
          background-color: white;
          padding: 20px;
          border-radius: 15px;
          box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
          width: 100%;
        "
      >
        <?php if (empty($scores)): ?>
        <p>Nog geen scores beschikbaar.</p>
        <?php else: ?>
        <?php foreach ($scores as $score): ?>
        <div class="score-item">
          <span><?= htmlspecialchars($score['naam']) ?></span>
          <span
            ><?= htmlspecialchars($score['total_points']) ?>
            punten</span
          >
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <?php elseif ($content_to_show === 'final_scoreboard'):
        $stmt = $pdo->prepare(' SELECT l.naam, SUM(r.points) as total_points
      FROM resultaten r JOIN leerlingen l ON r.leerling_id = l.id WHERE
      r.sessie_id = ? GROUP BY l.id, l.naam ORDER BY total_points DESC LIMIT 5
      '); $stmt->execute([$sessie_id]); $top_scores =
      $stmt->fetchAll(PDO::FETCH_ASSOC); ?>
      <h1 style="font-size: 3.5rem">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          height="48px"
          viewBox="0 -960 960 960"
          width="48px"
          fill="#ffd700"
        >
          <path
            d="M480-520q33 0 56.5-23.5T560-600q0-33-23.5-56.5T480-680q-33 0-56.5 23.5T400-600q0 33 23.5 56.5T480-520Zm-200-8v-152h-80v40q0 38 22 68.5t58 43.5Zm400 0q36-13 58-43.5t22-68.5v-40h-80v152ZM440-200v-124q-49-11-87.5-41.5T296-442q-75-9-125.5-65.5T120-640v-40q0-33 23.5-56.5T200-760h80q0-33 23.5-56.5T360-840h240q33 0 56.5 23.5T680-760h80q33 0 56.5 23.5T840-680v40q0 76-50.5 132.5T664-442q-18 46-56.5 76.5T520-324v124h120q17 0 28.5 11.5T680-160q0 17-11.5 28.5T640-120H320q-17 0-28.5-11.5T280-160q0-17 11.5-28.5T320-200h120Z"
          />
        </svg>
        Eindscore!
        <svg
          xmlns="http://www.w3.org/2000/svg"
          height="48px"
          viewBox="0 -960 960 960"
          width="48px"
          fill="#ffd700"
        >
          <path
            d="M480-520q33 0 56.5-23.5T560-600q0-33-23.5-56.5T480-680q-33 0-56.5 23.5T400-600q0 33 23.5 56.5T480-520Zm-200-8v-152h-80v40q0 38 22 68.5t58 43.5Zm400 0q36-13 58-43.5t22-68.5v-40h-80v152ZM440-200v-124q-49-11-87.5-41.5T296-442q-75-9-125.5-65.5T120-640v-40q0-33 23.5-56.5T200-760h80q0-33 23.5-56.5T360-840h240q33 0 56.5 23.5T680-760h80q33 0 56.5 23.5T840-680v40q0 76-50.5 132.5T664-442q-18 46-56.5 76.5T520-324v124h120q17 0 28.5 11.5T680-160q0 17-11.5 28.5T640-120H320q-17 0-28.5-11.5T280-160q0-17 11.5-28.5T320-200h120Z"
          />
        </svg>
      </h1>
      <p style="font-size: 2rem; margin-bottom: 52px">
        Gefeliciteerd winnaars!
      </p>
      <div class="podium-container">
        <?php if (isset($top_scores[1])): ?>
        <div class="podium-item second">
          <div class="podium-rank">2</div>
          <span><?= htmlspecialchars($top_scores[1]['naam']) ?></span>
          <div class="podium-score">
            <?= htmlspecialchars($top_scores[1]['total_points']) ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if (isset($top_scores[0])): ?>
        <div class="podium-item first">
          <div class="podium-rank">1</div>
          <span><?= htmlspecialchars($top_scores[0]['naam']) ?></span>
          <div class="podium-score">
            <?= htmlspecialchars($top_scores[0]['total_points']) ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if (isset($top_scores[2])): ?>
        <div class="podium-item third">
          <div class="podium-rank">3</div>
          <span><?= htmlspecialchars($top_scores[2]['naam']) ?></span>
          <div class="podium-score">
            <?= htmlspecialchars($top_scores[2]['total_points']) ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <div
        style="
          background-color: white;
          padding: 20px;
          border-radius: 15px;
          box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
          width: 100%;
          margin-top: 20px;
        "
      >
        <h2 style="font-size: 2rem">Top 5</h2>
        <?php 
                $rank = 1;
                foreach($top_scores as $score):
            ?>
        <div class="score-item" style="justify-content: center; gap: 20px">
          <span>#<?= $rank++ ?></span>
          <span><?= htmlspecialchars($score['naam']) ?>:</span>
          <span
            ><?= htmlspecialchars($score['total_points']) ?>
            punten</span
          >
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <script>
      setTimeout(() => {
        window.location.reload();
      }, 2000);
    </script>
  </body>
</html>