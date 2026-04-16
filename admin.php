<?php

// ===== ZUGANGSDATEN – BITTE ÄNDERN! =====
define('ADMIN_USER', 'ChrissiRde');
define('ADMIN_PASS', 'adaölfmQ$%452');  // BITTE AENDERN!

// ===== HTTP Basic Auth =====
if (!isset($_SERVER['PHP_AUTH_USER']) ||
    $_SERVER['PHP_AUTH_USER'] !== ADMIN_USER ||
    $_SERVER['PHP_AUTH_PW']   !== ADMIN_PASS) {
    header('WWW-Authenticate: Basic realm="Meine Stimme Admin"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<h2>Zugang verweigert.</h2>';
    exit;
}

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/db.php';

$db  = getDB();
$msg = '';
$tab = $_GET['tab'] ?? 'stats';

// ===== AKTIONEN =====

// Wort hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_word') {
        $ctx  = strtoupper(trim($_POST['context_key'] ?? ''));
        $word = trim($_POST['suggested_word'] ?? '');
        if ($ctx !== '' || $word !== '') {
            $stmt = $db->prepare('INSERT INTO word_suggestions (context_key, suggested_word, sort_order, language)
                                  VALUES (?, ?, 0, "de")
                                  ON DUPLICATE KEY UPDATE suggested_word = suggested_word');
            // Check if exists
            $check = $db->prepare('SELECT id FROM word_suggestions WHERE context_key=? AND suggested_word=? AND language="de"');
            $check->execute([$ctx, $word]);
            if (!$check->fetch()) {
                $stmt = $db->prepare('INSERT INTO word_suggestions (context_key, suggested_word, sort_order, language) VALUES (?,?,0,"de")');
                $stmt->execute([$ctx, $word]);
                $msg = "Wort hinzugefuegt: {$word} (Kontext: {$ctx})";
            } else {
                $msg = "Dieser Eintrag existiert bereits.";
            }
        }
        $tab = 'words';
    }

    if ($action === 'delete_word') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM word_suggestions WHERE id=?')->execute([$id]);
        $msg = "Eintrag geloescht.";
        $tab = 'words';
    }

    if ($action === 'reset_stats') {
        $db->exec('TRUNCATE TABLE usage_stats');
        $db->exec('UPDATE word_suggestions SET use_count = 0');
        $db->exec('UPDATE theme_options SET use_count = 0');
        $msg = "Alle Statistiken wurden zurueckgesetzt.";
        $tab = 'stats';
    }

    if ($action === 'update_option') {
        $id   = (int)($_POST['option_id'] ?? 0);
        $text = trim($_POST['text'] ?? '');
        $fmsg = trim($_POST['final_msg'] ?? '');
        if ($id && $text) {
            $db->prepare('UPDATE theme_options SET text=?, final_msg=? WHERE id=?')
               ->execute([$text, $fmsg ?: null, $id]);
            $msg = "Option gespeichert.";
        }
        $tab = 'themes';
    }
}

// ===== DATEN LADEN =====

// Statistiken
$topThemes = $db->query(
    'SELECT context_value, COUNT(*) as cnt FROM usage_stats WHERE event_type="theme_open" GROUP BY context_value ORDER BY cnt DESC LIMIT 10'
)->fetchAll();

$topOptions = $db->query(
    'SELECT context_value, COUNT(*) as cnt FROM usage_stats WHERE event_type="option_click" GROUP BY context_value ORDER BY cnt DESC LIMIT 15'
)->fetchAll();

$topWords = $db->query(
    'SELECT context_key, suggested_word, use_count FROM word_suggestions ORDER BY use_count DESC LIMIT 15'
)->fetchAll();

$topSentences = $db->query(
    'SELECT context_value, COUNT(*) as cnt FROM usage_stats WHERE event_type="sentence_complete" GROUP BY context_value ORDER BY cnt DESC LIMIT 10'
)->fetchAll();

$eventCounts = $db->query(
    'SELECT event_type, COUNT(*) as cnt FROM usage_stats GROUP BY event_type ORDER BY cnt DESC'
)->fetchAll();

$totalEvents = array_sum(array_column($eventCounts, 'cnt'));

// Wortvorschläge (alle, sortiert)
$allWords = $db->query(
    'SELECT id, context_key, suggested_word, use_count, sort_order FROM word_suggestions ORDER BY context_key ASC, use_count DESC, sort_order ASC'
)->fetchAll();

// Themenpfade
$allThemes = $db->query('SELECT * FROM themes ORDER BY sort_order ASC')->fetchAll();
$allSteps  = $db->query('SELECT s.*, t.title as theme_title FROM theme_steps s JOIN themes t ON s.theme_id=t.id ORDER BY t.sort_order, s.sort_order')->fetchAll();
$allOptions = $db->query('SELECT o.*, s.step_key, s.theme_id FROM theme_options o JOIN theme_steps s ON o.step_id=s.id ORDER BY s.theme_id, s.sort_order, o.sort_order')->fetchAll();

?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin – Meine Stimme</title>
<style>
  :root {
    --primary: #2563eb; --primary-dark: #1d4ed8; --primary-light: #dbeafe;
    --green: #16a34a; --green-light: #dcfce7;
    --red: #dc2626; --red-light: #fee2e2;
    --amber: #d97706; --amber-light: #fef3c7;
    --text: #1e293b; --text-soft: #64748b;
    --border: #e2e8f0; --bg: #f0f4f8; --surface: #fff;
    --radius: 12px; --shadow: 0 2px 10px rgba(0,0,0,0.07);
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); font-size: 15px; }

  header {
    background: var(--primary); color: white; padding: 14px 24px;
    display: flex; align-items: center; justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  }
  .logo { font-size: 1.3rem; font-weight: 800; letter-spacing: -0.3px; }
  .logo span { opacity: 0.7; font-weight: 400; font-size: 0.9rem; margin-left: 8px; }
  .header-right { font-size: 0.85rem; opacity: 0.8; }

  .tabs {
    background: var(--surface); border-bottom: 2px solid var(--border);
    display: flex; gap: 0; padding: 0 24px;
  }
  .tab {
    padding: 14px 20px; font-weight: 700; font-size: 0.95rem;
    color: var(--text-soft); text-decoration: none; border-bottom: 3px solid transparent;
    margin-bottom: -2px; transition: all 0.15s; display: flex; align-items: center; gap: 6px;
  }
  .tab:hover { color: var(--primary); }
  .tab.active { color: var(--primary); border-bottom-color: var(--primary); }

  main { max-width: 1100px; margin: 0 auto; padding: 24px 20px 60px; }

  .msg {
    padding: 12px 16px; border-radius: var(--radius); margin-bottom: 20px;
    font-weight: 600; background: var(--green-light); color: var(--green);
    border: 1.5px solid #bbf7d0;
  }

  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
  @media(max-width:800px) { .grid-2, .grid-3 { grid-template-columns: 1fr; } }

  .card {
    background: var(--surface); border-radius: var(--radius);
    border: 1.5px solid var(--border); padding: 20px;
    box-shadow: var(--shadow);
  }
  .card-title {
    font-size: 1rem; font-weight: 800; color: var(--primary);
    margin-bottom: 14px; padding-bottom: 10px; border-bottom: 2px solid var(--primary-light);
    display: flex; align-items: center; gap: 6px;
  }

  /* Stats */
  .stat-row { display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); }
  .stat-row:last-child { border-bottom: none; }
  .stat-label { font-size: 0.9rem; color: var(--text); flex: 1; padding-right: 10px; }
  .stat-bar-wrap { width: 120px; height: 8px; background: var(--border); border-radius: 10px; overflow: hidden; margin: 0 10px; }
  .stat-bar { height: 100%; background: var(--primary); border-radius: 10px; }
  .stat-count { font-weight: 800; font-size: 0.9rem; color: var(--primary); min-width: 30px; text-align: right; }

  .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px; }
  .kpi {
    background: var(--surface); border-radius: var(--radius); border: 1.5px solid var(--border);
    padding: 16px; text-align: center; box-shadow: var(--shadow);
  }
  .kpi-num { font-size: 2rem; font-weight: 900; color: var(--primary); }
  .kpi-label { font-size: 0.8rem; color: var(--text-soft); font-weight: 600; margin-top: 4px; }

  /* Tabelle */
  table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
  th { background: var(--primary-light); color: var(--primary-dark); font-weight: 700; padding: 9px 12px; text-align: left; }
  td { padding: 8px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: #f8fafc; }

  /* Formulare */
  .form-row { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 16px; }
  .form-group { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 160px; }
  label { font-size: 0.82rem; font-weight: 700; color: var(--text-soft); }
  input[type=text], textarea, select {
    padding: 9px 12px; border-radius: 8px; border: 1.5px solid var(--border);
    font-size: 0.9rem; font-family: inherit; color: var(--text);
    background: var(--bg); transition: border-color 0.15s;
  }
  input[type=text]:focus, textarea:focus { outline: none; border-color: var(--primary); background: white; }
  textarea { resize: vertical; min-height: 70px; }

  .btn { padding: 9px 18px; border-radius: 8px; border: none; font-family: inherit; font-size: 0.9rem; font-weight: 700; cursor: pointer; transition: all 0.15s; }
  .btn-primary { background: var(--primary); color: white; }
  .btn-primary:hover { background: var(--primary-dark); }
  .btn-danger { background: var(--red-light); color: var(--red); border: 1.5px solid #fca5a5; }
  .btn-danger:hover { background: var(--red); color: white; }
  .btn-sm { padding: 5px 12px; font-size: 0.82rem; }
  .btn-warning { background: var(--amber-light); color: var(--amber); border: 1.5px solid #fcd34d; }
  .btn-warning:hover { background: var(--amber); color: white; }

  .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
  .badge-blue { background: var(--primary-light); color: var(--primary-dark); }
  .badge-green { background: var(--green-light); color: var(--green); }
  .badge-gray { background: var(--bg); color: var(--text-soft); border: 1px solid var(--border); }

  .section-title { font-size: 1rem; font-weight: 800; color: var(--text); margin: 24px 0 12px; }
  .hint { font-size: 0.82rem; color: var(--text-soft); margin-top: 6px; }

  /* Accordion für Themes */
  details { background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius); margin-bottom: 10px; overflow: hidden; }
  summary { padding: 14px 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; list-style: none; }
  summary::-webkit-details-marker { display: none; }
  summary::after { content: '▸'; margin-left: auto; color: var(--text-soft); transition: transform 0.2s; }
  details[open] summary::after { transform: rotate(90deg); }
  .details-body { padding: 0 16px 16px; }

  .reset-zone { border: 2px dashed var(--red); border-radius: var(--radius); padding: 20px; background: var(--red-light); margin-top: 30px; }
  .reset-zone h3 { color: var(--red); margin-bottom: 10px; }
  .reset-zone p { color: #7f1d1d; font-size: 0.9rem; margin-bottom: 14px; }
</style>
</head>
<body>

<header>
  <div class="logo">🗣️ Meine Stimme <span>Admin-Panel</span></div>
  <div class="header-right">Eingeloggt als: <?= htmlspecialchars(ADMIN_USER) ?> &nbsp;·&nbsp; <a href="index.html" style="color:white">← Zur App</a></div>
</header>

<div class="tabs">
  <a class="tab <?= $tab==='stats' ? 'active' : '' ?>" href="?tab=stats">📊 Statistiken</a>
  <a class="tab <?= $tab==='words' ? 'active' : '' ?>" href="?tab=words">✏️ Wortvorschläge</a>
  <a class="tab <?= $tab==='themes' ? 'active' : '' ?>" href="?tab=themes">💬 Themenpfade</a>
</div>

<main>

<?php if ($msg): ?>
  <div class="msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php // ================================================================
      // TAB: STATISTIKEN
      if ($tab === 'stats'): ?>

  <?php
    $themeCount   = (int)$db->query('SELECT COUNT(*) FROM themes WHERE active=1')->fetchColumn();
    $wordCount    = (int)$db->query('SELECT COUNT(*) FROM word_suggestions')->fetchColumn();
    $learnedWords = (int)$db->query('SELECT COUNT(*) FROM word_suggestions WHERE use_count > 0')->fetchColumn();
    $maxCount     = max(1, max(array_column($topThemes, 'cnt') ?: [1]));
    $maxOpt       = max(1, max(array_column($topOptions, 'cnt') ?: [1]));
    $maxWord      = max(1, max(array_column($topWords, 'use_count') ?: [1]));
  ?>

  <div class="kpi-grid">
    <div class="kpi"><div class="kpi-num"><?= $totalEvents ?></div><div class="kpi-label">Klicks gesamt</div></div>
    <?php foreach ($eventCounts as $e): ?>
    <div class="kpi">
      <?php
        $label = $e['event_type'];
        if ($e['event_type'] === 'theme_open')        $label = 'Themen geoeffnet';
        elseif ($e['event_type'] === 'option_click')  $label = 'Optionen geklickt';
        elseif ($e['event_type'] === 'word_click')    $label = 'Woerter geklickt';
        elseif ($e['event_type'] === 'sentence_complete') $label = 'Saetze fertig';
      ?>
      <div class="kpi-num"><?= $e['cnt'] ?></div>
      <div class="kpi-label"><?= htmlspecialchars($label) ?></div>
    </div>
    <?php endforeach; ?>
    <div class="kpi"><div class="kpi-num"><?= $wordCount ?></div><div class="kpi-label">Wortvorschläge</div></div>
    <div class="kpi"><div class="kpi-num"><?= $learnedWords ?></div><div class="kpi-label">gelernte Wörter</div></div>
  </div>

  <div class="grid-2">
    <div class="card">
      <div class="card-title">🗂️ Meistgenutzte Themen</div>
      <?php if (empty($topThemes)): ?>
        <p style="color:var(--text-soft);font-size:0.9rem">Noch keine Daten vorhanden.</p>
      <?php else: foreach ($topThemes as $r): ?>
        <div class="stat-row">
          <span class="stat-label"><?= htmlspecialchars($r['context_value']) ?></span>
          <div class="stat-bar-wrap"><div class="stat-bar" style="width:<?= round($r['cnt']/$maxCount*100) ?>%"></div></div>
          <span class="stat-count"><?= $r['cnt'] ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <div class="card">
      <div class="card-title">📈 Meistgelernte Wörter</div>
      <?php if (empty($topWords)): ?>
        <p style="color:var(--text-soft);font-size:0.9rem">Noch keine Daten vorhanden.</p>
      <?php else: foreach ($topWords as $r): ?>
        <div class="stat-row">
          <span class="stat-label">
            <span class="badge badge-gray"><?= htmlspecialchars($r['context_key'] ?: '(Start)') ?></span>
            &rarr; <?= htmlspecialchars($r['suggested_word']) ?>
          </span>
          <div class="stat-bar-wrap"><div class="stat-bar" style="width:<?= round($r['use_count']/$maxWord*100) ?>%"></div></div>
          <span class="stat-count"><?= $r['use_count'] ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="grid-2" style="margin-top:20px">
    <div class="card">
      <div class="card-title">🔘 Meistgeklickte Optionen</div>
      <?php if (empty($topOptions)): ?>
        <p style="color:var(--text-soft);font-size:0.9rem">Noch keine Daten vorhanden.</p>
      <?php else: foreach ($topOptions as $r): ?>
        <div class="stat-row">
          <span class="stat-label" style="font-size:0.85rem"><?= htmlspecialchars($r['context_value']) ?></span>
          <div class="stat-bar-wrap"><div class="stat-bar" style="width:<?= round($r['cnt']/$maxOpt*100) ?>%"></div></div>
          <span class="stat-count"><?= $r['cnt'] ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <div class="card">
      <div class="card-title">💬 Häufigste fertige Sätze</div>
      <?php if (empty($topSentences)): ?>
        <p style="color:var(--text-soft);font-size:0.9rem">Noch keine Daten vorhanden.</p>
      <?php else: foreach ($topSentences as $r): ?>
        <div class="stat-row">
          <span class="stat-label" style="font-size:0.85rem"><?= htmlspecialchars(substr($r['context_value'], 0, 60)) ?>…</span>
          <span class="stat-count"><?= $r['cnt'] ?>×</span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="reset-zone">
    <h3>⚠️ Statistiken zurücksetzen</h3>
    <p>Alle Klick-Statistiken werden gelöscht und alle use_count-Zähler auf 0 zurückgesetzt. Die Wortvorschläge selbst bleiben erhalten. Diese Aktion kann nicht rückgängig gemacht werden.</p>
    <form method="post" onsubmit="return confirm('Wirklich alle Statistiken löschen?')">
      <input type="hidden" name="action" value="reset_stats">
      <button type="submit" class="btn btn-danger">🗑️ Alle Statistiken zurücksetzen</button>
    </form>
  </div>

<?php // ================================================================
      // TAB: WORTVORSCHLÄGE
      elseif ($tab === 'words'): ?>

  <div class="card" style="margin-bottom:20px">
    <div class="card-title">➕ Neuen Wortvorschlag hinzufügen</div>
    <form method="post">
      <input type="hidden" name="action" value="add_word">
      <div class="form-row">
        <div class="form-group">
          <label>Kontext (GROSSBUCHSTABEN, z.B. "ICH MÖCHTE")</label>
          <input type="text" name="context_key" placeholder='z.B. ICH MÖCHTE oder leer für Satzanfang' style="text-transform:uppercase">
        </div>
        <div class="form-group" style="max-width:200px">
          <label>Vorgeschlagenes Wort</label>
          <input type="text" name="suggested_word" placeholder="z.B. gerne" required>
        </div>
        <div style="padding-bottom:1px">
          <button type="submit" class="btn btn-primary">➕ Hinzufügen</button>
        </div>
      </div>
    </form>
    <p class="hint">💡 Tipp: Der Kontext ist das/die Wort(e) davor in Großbuchstaben. "ICH MÖCHTE" → zeigt Vorschläge nach "Ich möchte". Leer lassen = Satzanfang.</p>
  </div>

  <div class="card">
    <div class="card-title">📋 Alle Wortvorschläge (<?= count($allWords) ?>)</div>
    <table>
      <tr>
        <th>Kontext</th>
        <th>Vorschlag</th>
        <th>Genutzt</th>
        <th>Aktion</th>
      </tr>
      <?php foreach ($allWords as $w): ?>
      <tr>
        <td><span class="badge badge-gray"><?= htmlspecialchars($w['context_key'] ?: '(Satzanfang)') ?></span></td>
        <td><strong><?= htmlspecialchars($w['suggested_word']) ?></strong></td>
        <td>
          <?php if ($w['use_count'] > 0): ?>
            <span class="badge badge-green"><?= $w['use_count'] ?>× genutzt</span>
          <?php else: ?>
            <span class="badge badge-gray">ungenutzt</span>
          <?php endif; ?>
        </td>
        <td>
          <form method="post" style="display:inline" onsubmit="return confirm('Wirklich löschen?')">
            <input type="hidden" name="action" value="delete_word">
            <input type="hidden" name="id" value="<?= $w['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

<?php // ================================================================
      // TAB: THEMENPFADE
      elseif ($tab === 'themes'): ?>

  <p style="color:var(--text-soft);margin-bottom:16px;font-size:0.9rem">
    Hier können Sie die Texte der Antwort-Buttons und die finalen Nachrichten bearbeiten, die in der App erscheinen.
  </p>

  <?php
  // Gruppiere Options nach theme
  $optionsByTheme = [];
  foreach ($allOptions as $opt) {
      $optionsByTheme[$opt['theme_id']][] = $opt;
  }
  $stepsByTheme = [];
  foreach ($allSteps as $step) {
      $stepsByTheme[$step['theme_id']][] = $step;
  }
  ?>

  <?php foreach ($allThemes as $theme): ?>
  <details>
    <summary>
      <?= $theme['icon'] ?> <?= htmlspecialchars($theme['title']) ?>
      <span class="badge badge-blue" style="margin-left:8px"><?= count($optionsByTheme[$theme['id']] ?? []) ?> Optionen</span>
    </summary>
    <div class="details-body">
      <?php foreach ($optionsByTheme[$theme['id']] ?? [] as $opt): ?>
        <form method="post" style="margin-bottom:14px;padding:14px;background:var(--bg);border-radius:8px;border:1px solid var(--border)">
          <input type="hidden" name="action" value="update_option">
          <input type="hidden" name="option_id" value="<?= $opt['id'] ?>">
          <div style="font-size:0.78rem;color:var(--text-soft);margin-bottom:8px;font-weight:700">
            Schritt: <span class="badge badge-gray"><?= htmlspecialchars($opt['step_key']) ?></span>
            &rarr; n&auml;chster Schritt: <span class="badge badge-gray"><?= htmlspecialchars($opt['next_step']) ?></span>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Button-Text (mit Emoji)</label>
              <input type="text" name="text" value="<?= htmlspecialchars($opt['text']) ?>" required>
            </div>
            <div class="form-group" style="flex:2">
              <label>Finale Nachricht (wird in Ausgabe übernommen)</label>
              <textarea name="final_msg"><?= htmlspecialchars($opt['final_msg'] ?? '') ?></textarea>
            </div>
            <div style="padding-bottom:1px">
              <button type="submit" class="btn btn-primary btn-sm">💾 Speichern</button>
            </div>
          </div>
        </form>
      <?php endforeach; ?>
      <?php if (empty($optionsByTheme[$theme['id']])): ?>
        <p style="color:var(--text-soft);font-size:0.9rem">Keine Optionen in der Datenbank für dieses Thema. Bitte SQL-Seed importieren.</p>
      <?php endif; ?>
    </div>
  </details>
  <?php endforeach; ?>

<?php endif; ?>

</main>
</body>
</html>
