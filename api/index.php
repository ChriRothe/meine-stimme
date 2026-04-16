<?php
// ============================================================
//  MEINE STIMME – REST API
//  Datei: api/index.php
//
//  Endpunkte:
//    GET  /api/?action=suggestions&context=ICH+MÖCHTE
//    GET  /api/?action=themes
//    GET  /api/?action=theme&key=food
//    POST /api/?action=track
// ============================================================
require_once __DIR__ . '/db.php';

// OPTIONS preflight (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

$action = $_GET['action'] ?? '';
$lang   = $_GET['lang']   ?? DEFAULT_LANG;

match ($action) {
    'suggestions' => handleSuggestions($lang),
    'themes'      => handleThemes($lang),
    'theme'       => handleTheme($lang),
    'track'       => handleTrack(),
    'stats'       => handleStats(),
    default       => jsonResponse(['error' => 'Unbekannte Aktion: ' . $action], 400),
};

// ============================================================
//  Wortvorschläge basierend auf Kontext
// ============================================================
function handleSuggestions(string $lang): void {
    $context = strtoupper(trim($_GET['context'] ?? ''));
    $db = getDB();

    // Versuche exakten Kontext-Treffer (erst 2 Wörter, dann 1)
    $words = explode(' ', $context);
    $keysToTry = [];

    if (count($words) >= 2) {
        $keysToTry[] = implode(' ', array_slice($words, -2)); // letzte 2
    }
    if (count($words) >= 1 && end($words) !== '') {
        $keysToTry[] = end($words); // letztes Wort
    }
    $keysToTry[] = ''; // Satzanfang als Fallback

    foreach ($keysToTry as $key) {
        $stmt = $db->prepare(
            'SELECT suggested_word, use_count FROM word_suggestions
             WHERE context_key = ? AND language = ?
             ORDER BY use_count DESC, sort_order ASC
             LIMIT 8'
        );
        $stmt->execute([$key, $lang]);
        $rows = $stmt->fetchAll();
        if (!empty($rows)) {
            jsonResponse([
                'context_key' => $key,
                'suggestions' => array_column($rows, 'suggested_word'),
            ]);
        }
    }

    jsonResponse(['context_key' => '', 'suggestions' => []]);
}

// ============================================================
//  Alle Themen laden
// ============================================================
function handleThemes(string $lang): void {
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT id, `key`, title, icon, description, color_class
         FROM themes
         WHERE active = 1 AND language = ?
         ORDER BY sort_order ASC'
    );
    $stmt->execute([$lang]);
    jsonResponse(['themes' => $stmt->fetchAll()]);
}

// ============================================================
//  Einzelnes Thema mit allen Steps und Options laden
// ============================================================
function handleTheme(string $lang): void {
    $key = $_GET['key'] ?? '';
    if (!$key) jsonResponse(['error' => 'key fehlt'], 400);

    $db = getDB();

    // Theme
    $stmt = $db->prepare('SELECT * FROM themes WHERE `key` = ? AND language = ? AND active = 1');
    $stmt->execute([$key, $lang]);
    $theme = $stmt->fetch();
    if (!$theme) jsonResponse(['error' => 'Thema nicht gefunden'], 404);

    // Steps
    $stmt = $db->prepare(
        'SELECT id, step_key, type, label, question, final_msg
         FROM theme_steps WHERE theme_id = ? ORDER BY sort_order ASC'
    );
    $stmt->execute([$theme['id']]);
    $steps = $stmt->fetchAll();

    // Options für jeden Step
    $optStmt = $db->prepare(
        'SELECT id, icon, text, next_step, final_msg
         FROM theme_options WHERE step_id = ? ORDER BY sort_order ASC'
    );

    // YesNo-Daten laden (aus step-Feldern ja/nein)
    $yesnoStmt = $db->prepare(
        'SELECT * FROM theme_steps WHERE theme_id = ? AND step_key = ?'
    );

    $stepsMap = [];
    foreach ($steps as $step) {
        $optStmt->execute([$step['id']]);
        $options = $optStmt->fetchAll();

        $stepsMap[$step['step_key']] = [
            'type'    => $step['type'],
            'label'   => $step['label'],
            'question'=> $step['question'],
            'final_msg'=> $step['final_msg'],
            'options' => $options,
        ];
    }

    jsonResponse(['theme' => $theme, 'steps' => $stepsMap]);
}

// ============================================================
//  Nutzungsstatistik tracken (Lernfunktion)
// ============================================================
function handleTrack(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'POST erforderlich'], 405);
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $eventType = $body['event_type'] ?? '';
    $refId     = isset($body['ref_id']) ? (int)$body['ref_id'] : null;
    $refTable  = $body['ref_table'] ?? null;
    $context   = $body['context']   ?? null;

    if (!in_array($eventType, ['word_click','option_click','theme_open','sentence_complete'], true)) {
        jsonResponse(['error' => 'Unbekannter event_type'], 400);
    }

    $db = getDB();
    $sid = getSessionId();

    // Event speichern
    $stmt = $db->prepare(
        'INSERT INTO usage_stats (event_type, ref_id, ref_table, context_value, session_id)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$eventType, $refId, $refTable, $context, $sid]);

    // use_count hochzählen
    if ($refId && $refTable === 'word_suggestions') {
        $db->prepare('UPDATE word_suggestions SET use_count = use_count + 1 WHERE id = ?')
           ->execute([$refId]);
    }
    if ($refId && $refTable === 'theme_options') {
        $db->prepare('UPDATE theme_options SET use_count = use_count + 1 WHERE id = ?')
           ->execute([$refId]);
    }

    jsonResponse(['ok' => true]);
}

// ============================================================
//  Einfache Statistik-Übersicht (für späteres Admin-Panel)
// ============================================================
function handleStats(): void {
    $db = getDB();

    $topWords = $db->query(
        'SELECT context_key, suggested_word, use_count
         FROM word_suggestions ORDER BY use_count DESC LIMIT 20'
    )->fetchAll();

    $topOptions = $db->query(
        'SELECT o.text, o.use_count, t.title as theme
         FROM theme_options o
         JOIN theme_steps s ON o.step_id = s.id
         JOIN themes t ON s.theme_id = t.id
         ORDER BY o.use_count DESC LIMIT 20'
    )->fetchAll();

    $eventCounts = $db->query(
        'SELECT event_type, COUNT(*) as count FROM usage_stats GROUP BY event_type'
    )->fetchAll();

    jsonResponse([
        'top_words'   => $topWords,
        'top_options' => $topOptions,
        'events'      => $eventCounts,
    ]);
}
