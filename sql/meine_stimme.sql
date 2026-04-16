-- ============================================================
--  MEINE STIMME – Datenbankschema v1.0
--  Import über phpMyAdmin auf ALL-INKL
--  Erstellt eine Datenbank mit allen Tabellen für Phase 1
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- ============================================================
-- TABELLE: words
-- Alle bekannten Wörter + Folgewort-Beziehungen (Wortbaum)
-- ============================================================
CREATE TABLE IF NOT EXISTS `words` (
  `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `word`        VARCHAR(100)     NOT NULL COMMENT 'Das Wort selbst',
  `category`    VARCHAR(50)      DEFAULT 'general' COMMENT 'Kategorie: general, greeting, food, social, ...',
  `language`    VARCHAR(5)       NOT NULL DEFAULT 'de',
  `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_word_lang` (`word`, `language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: word_suggestions
-- Welches Wort folgt auf welches? (Wortbaum-Kanten)
-- context_key = letztes Wort oder "VORWORT LETZTESWORT"
-- ============================================================
CREATE TABLE IF NOT EXISTS `word_suggestions` (
  `id`            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `context_key`   VARCHAR(200)   NOT NULL COMMENT 'z.B. "ICH" oder "ICH MÖCHTE"',
  `suggested_word` VARCHAR(100)  NOT NULL,
  `sort_order`    SMALLINT       NOT NULL DEFAULT 0 COMMENT 'Anzeigereihenfolge',
  `use_count`     INT UNSIGNED   NOT NULL DEFAULT 0 COMMENT 'Wie oft wurde dieser Vorschlag genutzt',
  `language`      VARCHAR(5)     NOT NULL DEFAULT 'de',
  `created_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_context` (`context_key`, `language`),
  KEY `idx_use_count` (`use_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: themes
-- Themenbereiche (Essen, Soziales, Hygiene, ...)
-- ============================================================
CREATE TABLE IF NOT EXISTS `themes` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `key`         VARCHAR(50)    NOT NULL COMMENT 'Eindeutiger Schlüssel, z.B. "food"',
  `title`       VARCHAR(100)   NOT NULL,
  `icon`        VARCHAR(10)    NOT NULL COMMENT 'Emoji',
  `description` VARCHAR(200)   DEFAULT NULL,
  `color_class` VARCHAR(50)    DEFAULT 'theme-default',
  `sort_order`  SMALLINT       NOT NULL DEFAULT 0,
  `active`      TINYINT(1)     NOT NULL DEFAULT 1,
  `language`    VARCHAR(5)     NOT NULL DEFAULT 'de',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_theme_key` (`key`, `language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: theme_steps
-- Schritte/Knoten im Entscheidungsbaum eines Themas
-- ============================================================
CREATE TABLE IF NOT EXISTS `theme_steps` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `theme_id`    INT UNSIGNED   NOT NULL,
  `step_key`    VARCHAR(50)    NOT NULL COMMENT 'Eindeutiger Schlüssel innerhalb des Themas, z.B. "start"',
  `type`        ENUM('options','yesno','final') NOT NULL DEFAULT 'options',
  `label`       VARCHAR(200)   DEFAULT NULL COMMENT 'Überschrift / Frage',
  `question`    VARCHAR(300)   DEFAULT NULL COMMENT 'Frage für yesno-Typ',
  `final_msg`   TEXT           DEFAULT NULL COMMENT 'Ausgabenachricht bei type=final',
  `sort_order`  SMALLINT       NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_step` (`theme_id`, `step_key`),
  CONSTRAINT `fk_step_theme` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: theme_options
-- Antwortoptionen / Buttons innerhalb eines Steps
-- ============================================================
CREATE TABLE IF NOT EXISTS `theme_options` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `step_id`     INT UNSIGNED   NOT NULL,
  `icon`        VARCHAR(10)    DEFAULT '💬',
  `text`        VARCHAR(300)   NOT NULL COMMENT 'Beschriftung des Buttons',
  `next_step`   VARCHAR(50)    NOT NULL COMMENT 'step_key des nächsten Schrittes',
  `final_msg`   TEXT           DEFAULT NULL COMMENT 'Direkte Ausgabenachricht, falls next_step=final',
  `sort_order`  SMALLINT       NOT NULL DEFAULT 0,
  `use_count`   INT UNSIGNED   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_step` (`step_id`),
  CONSTRAINT `fk_option_step` FOREIGN KEY (`step_id`) REFERENCES `theme_steps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELLE: usage_stats
-- Jeder Klick wird anonym protokolliert (Lernfunktion)
-- ============================================================
CREATE TABLE IF NOT EXISTS `usage_stats` (
  `id`            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `event_type`    ENUM('word_click','option_click','theme_open','sentence_complete') NOT NULL,
  `ref_id`        INT UNSIGNED   DEFAULT NULL COMMENT 'ID des geklickten Elements',
  `ref_table`     VARCHAR(50)    DEFAULT NULL COMMENT 'Tabellenname: word_suggestions / theme_options',
  `context_value` VARCHAR(300)   DEFAULT NULL COMMENT 'Freitext-Kontext, z.B. der Satzanfang',
  `session_id`    VARCHAR(64)    DEFAULT NULL COMMENT 'Anonyme Session-ID',
  `created_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event` (`event_type`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED-DATEN: Wortbaum
-- ============================================================
INSERT INTO `word_suggestions` (`context_key`, `suggested_word`, `sort_order`) VALUES
-- Satzanfang
('', 'Ich', 1), ('', 'Bitte', 2), ('', 'Danke', 3),
('', 'Hallo', 4), ('', 'Hilfe', 5), ('', 'Ja', 6), ('', 'Nein', 7),

-- Nach "Ich"
('ICH', 'möchte', 1), ('ICH', 'habe', 2), ('ICH', 'bin', 3),
('ICH', 'fühle', 4), ('ICH', 'brauche', 5), ('ICH', 'freue', 6),

-- Nach "Ich möchte"
('ICH MÖCHTE', 'bitte', 1), ('ICH MÖCHTE', 'gerne', 2), ('ICH MÖCHTE', 'etwas', 3),
('ICH MÖCHTE', 'nicht', 4), ('ICH MÖCHTE', 'schlafen', 5), ('ICH MÖCHTE', 'trinken', 6),

-- Nach "Ich habe"
('ICH HABE', 'Hunger', 1), ('ICH HABE', 'Durst', 2), ('ICH HABE', 'Schmerzen', 3),
('ICH HABE', 'Angst', 4), ('ICH HABE', 'kein', 5),

-- Nach "Ich bin"
('ICH BIN', 'müde', 1), ('ICH BIN', 'traurig', 2), ('ICH BIN', 'froh', 3),
('ICH BIN', 'krank', 4), ('ICH BIN', 'hungrig', 5), ('ICH BIN', 'durstig', 6),

-- Nach "Ich fühle"
('ICH FÜHLE', 'mich', 1), ('ICH FÜHLE', 'Schmerzen', 2),
('ICH FÜHLE MICH', 'gut', 1), ('ICH FÜHLE MICH', 'schlecht', 2),
('ICH FÜHLE MICH', 'müde', 3), ('ICH FÜHLE MICH', 'unwohl', 4),

-- Nach "Bitte"
('BITTE', 'helfen', 1), ('BITTE', 'bringen', 2), ('BITTE', 'kommen', 3),
('BITTE', 'warten', 4), ('BITTE', 'nicht', 5),
('BITTE BRINGEN SIE MIR', 'Wasser', 1), ('BITTE BRINGEN SIE MIR', 'Tee', 2),
('BITTE BRINGEN SIE MIR', 'Essen', 3), ('BITTE BRINGEN SIE MIR', 'etwas', 4),

-- Nach "Danke"
('DANKE', 'sehr', 1), ('DANKE', 'für', 2), ('DANKE', 'schön', 3), ('DANKE', 'Ihnen', 4),

-- Allgemein
('MÖCHTE', 'bitte', 1), ('MÖCHTE', 'gerne', 2), ('MÖCHTE', 'nicht', 3), ('MÖCHTE', 'etwas', 4),
('HABE', 'Hunger', 1), ('HABE', 'Durst', 2), ('HABE', 'Schmerzen', 3),
('MIR', 'geht', 1), ('MIR', 'ist', 2), ('MIR', 'tut', 3),
('MIR GEHT', 'es', 1),
('MIR GEHT ES', 'gut', 1), ('MIR GEHT ES', 'schlecht', 2), ('MIR GEHT ES', 'besser', 3),
('JA', 'bitte', 1), ('JA', 'natürlich', 2), ('JA', 'gerne', 3),
('NEIN', 'danke', 1), ('NEIN', 'bitte', 2), ('NEIN', 'nicht', 3),
('KÖNNTEN', 'Sie', 1),
('KÖNNTEN SIE', 'mir', 1), ('KÖNNTEN SIE', 'bitte', 2),
('KÖNNTEN SIE MIR', 'bitte', 1), ('KÖNNTEN SIE MIR', 'helfen', 2),
('WASSER', 'bitte', 1), ('WASSER', '.', 2),
('HUNGER', 'Bitte', 1), ('DURST', 'Bitte', 1),
('GUT', 'Danke', 1), ('GUT', '.', 2), ('GUT', '!', 3),
('MÜDE', 'Bitte', 1), ('MÜDE', '.', 2);

-- ============================================================
-- SEED-DATEN: Themen
-- ============================================================
INSERT INTO `themes` (`key`, `title`, `icon`, `description`, `color_class`, `sort_order`) VALUES
('food',    'Essen & Trinken',       '🍽️', 'Hunger, Durst und Essenswünsche',       'theme-food',    1),
('social',  'Soziales & Gemeinschaft','👥', 'Gemeinschaft, Gefühle, Besuch',          'theme-social',  2),
('hygiene', 'Körperhygiene',         '🚿', 'Waschen, Pflege, Toilette',              'theme-hygiene', 3),
('pain',    'Schmerzen & Wohlbefinden','😣','Schmerzen lokalisieren und beschreiben', 'theme-pain',    4),
('feelings','Gefühle',               '💛', 'Stimmung und emotionaler Zustand',       'theme-feelings',5);

-- ============================================================
-- SEED-DATEN: Steps & Options – Essen & Trinken
-- ============================================================
SET @food_id = (SELECT id FROM themes WHERE `key` = 'food');

INSERT INTO `theme_steps` (`theme_id`,`step_key`,`type`,`label`,`question`) VALUES
(@food_id, 'start',       'options', 'Was möchten Sie?', NULL),
(@food_id, 'hungry',      'yesno',   NULL, '🍽️\nKönnten Sie mir etwas zu essen bringen?'),
(@food_id, 'hungry_what', 'options', 'Was möchten Sie essen?', NULL),
(@food_id, 'thirsty',     'options', 'Was möchten Sie trinken?', NULL),
(@food_id, 'tea',         'yesno',   NULL, '☕\nMöchten Sie den Tee mit Milch oder Zucker?'),
(@food_id, 'specific',    'options', 'Möchten Sie etwas sagen?', NULL),
(@food_id, 'dislike',     'yesno',   NULL, '😕\nKann ich etwas anderes bekommen?'),
(@food_id, 'final',       'final',   NULL, NULL);

SET @s_start      = (SELECT id FROM theme_steps WHERE theme_id=@food_id AND step_key='start');
SET @s_hungry     = (SELECT id FROM theme_steps WHERE theme_id=@food_id AND step_key='hungry');
SET @s_hungry_w   = (SELECT id FROM theme_steps WHERE theme_id=@food_id AND step_key='hungry_what');
SET @s_thirsty    = (SELECT id FROM theme_steps WHERE theme_id=@food_id AND step_key='thirsty');
SET @s_tea        = (SELECT id FROM theme_steps WHERE theme_id=@food_id AND step_key='tea');
SET @s_specific   = (SELECT id FROM theme_steps WHERE theme_id=@food_id AND step_key='specific');
SET @s_dislike    = (SELECT id FROM theme_steps WHERE theme_id=@food_id AND step_key='dislike');

INSERT INTO `theme_options` (`step_id`,`icon`,`text`,`next_step`,`final_msg`,`sort_order`) VALUES
(@s_start, '🍽️', 'Ich habe Hunger',           'hungry',   NULL, 1),
(@s_start, '💧', 'Ich habe Durst',             'thirsty',  NULL, 2),
(@s_start, '🙏', 'Ich möchte etwas bestimmtes','specific', NULL, 3),
(@s_start, '🚫', 'Ich mag das nicht',          'dislike',  NULL, 4),
(@s_start, '😋', 'Das hat mir geschmeckt!',    'final',    'Das hat mir sehr gut geschmeckt. Danke!', 5),

(@s_hungry_w, '🍞', 'Brot oder Brötchen', 'final', 'Ich möchte bitte Brot oder ein Brötchen.', 1),
(@s_hungry_w, '🥣', 'Suppe',             'final', 'Ich hätte gerne Suppe, bitte.', 2),
(@s_hungry_w, '🥗', 'Salat',             'final', 'Ich würde gerne etwas Leichtes essen, zum Beispiel Salat.', 3),
(@s_hungry_w, '🍰', 'Etwas Süßes',       'final', 'Ich hätte gerne etwas Süßes, zum Beispiel Kuchen.', 4),

(@s_thirsty, '💧', 'Wasser',  'final', 'Bitte bringen Sie mir ein Glas Wasser.', 1),
(@s_thirsty, '☕', 'Tee',     'tea',   NULL, 2),
(@s_thirsty, '☕', 'Kaffee',  'final', 'Ich möchte gerne einen Kaffee, bitte.', 3),
(@s_thirsty, '🥤', 'Saft',   'final', 'Bitte einen Saft. Danke.', 4),

(@s_specific, '🌡️', 'Das Essen ist zu heiß',   'final', 'Das Essen ist mir zu heiß. Bitte warten Sie kurz.', 1),
(@s_specific, '❄️', 'Das Essen ist kalt',       'final', 'Das Essen ist leider kalt. Könnten Sie es aufwärmen?', 2),
(@s_specific, '🧂', 'Mehr Salz',                'final', 'Ich würde gerne etwas mehr Salz haben.', 3),
(@s_specific, '😐', 'Ich bin noch nicht fertig','final', 'Ich bin noch nicht fertig. Bitte nicht abräumen.', 4);

-- ============================================================
-- SEED-DATEN: Steps & Options – Soziales & Gemeinschaft
-- ============================================================
SET @social_id = (SELECT id FROM themes WHERE `key` = 'social');

INSERT INTO `theme_steps` (`theme_id`,`step_key`,`type`,`label`,`question`) VALUES
(@social_id, 'start',            'options', 'Was möchten Sie ausdrücken?', NULL),
(@social_id, 'good',             'options', 'Möchten Sie mehr sagen?', NULL),
(@social_id, 'notgood',          'yesno',   NULL, '😔\nHaben Sie körperliche Beschwerden?'),
(@social_id, 'notgood_emotional','options', 'Was fühlen Sie?', NULL),
(@social_id, 'visitors',         'options', 'Wen möchten Sie sehen?', NULL),
(@social_id, 'activity',         'options', 'Was möchten Sie tun?', NULL),
(@social_id, 'thanks',           'options', 'Wofür möchten Sie danken?', NULL),
(@social_id, 'final',            'final',   NULL, NULL);

SET @ss_start  = (SELECT id FROM theme_steps WHERE theme_id=@social_id AND step_key='start');
SET @ss_good   = (SELECT id FROM theme_steps WHERE theme_id=@social_id AND step_key='good');
SET @ss_notg   = (SELECT id FROM theme_steps WHERE theme_id=@social_id AND step_key='notgood');
SET @ss_notge  = (SELECT id FROM theme_steps WHERE theme_id=@social_id AND step_key='notgood_emotional');
SET @ss_vis    = (SELECT id FROM theme_steps WHERE theme_id=@social_id AND step_key='visitors');
SET @ss_act    = (SELECT id FROM theme_steps WHERE theme_id=@social_id AND step_key='activity');
SET @ss_thanks = (SELECT id FROM theme_steps WHERE theme_id=@social_id AND step_key='thanks');

INSERT INTO `theme_options` (`step_id`,`icon`,`text`,`next_step`,`final_msg`,`sort_order`) VALUES
(@ss_start, '😊', 'Ich fühle mich gut',           'good',     NULL, 1),
(@ss_start, '😔', 'Mir geht es nicht so gut',      'notgood',  NULL, 2),
(@ss_start, '👨‍👩‍👧', 'Ich möchte jemanden sehen',    'visitors', NULL, 3),
(@ss_start, '📺', 'Ich möchte etwas unternehmen',  'activity', NULL, 4),
(@ss_start, '🙏', 'Ich möchte Danke sagen',        'thanks',   NULL, 5),
(@ss_start, '😴', 'Ich möchte Ruhe',               'final',    'Ich bin müde und möchte gerne ruhen. Bitte nicht stören.', 6),

(@ss_good, '😁', 'Ich bin sehr gut gelaunt!',   'final', 'Ich bin heute sehr gut gelaunt!', 1),
(@ss_good, '☀️', 'Das schöne Wetter freut mich','final', 'Das schöne Wetter freut mich sehr!', 2),
(@ss_good, '💝', 'Ich freue mich über Besuch',  'final', 'Es freut mich sehr, Sie zu sehen!', 3),
(@ss_good, '✅', 'Mir geht es gut. Danke.',     'final', 'Mir geht es gut. Danke der Nachfrage!', 4),

(@ss_notge, '😢', 'Ich bin traurig',           'final', 'Ich bin gerade traurig. Ich würde mich über Gesellschaft freuen.', 1),
(@ss_notge, '😟', 'Ich mache mir Sorgen',      'final', 'Ich mache mir Sorgen. Könnten wir darüber sprechen?', 2),
(@ss_notge, '😤', 'Ich bin unruhig',           'final', 'Ich bin gerade unruhig. Ich brauche etwas Ablenkung.', 3),
(@ss_notge, '🌧️', 'Ich brauche jemanden',     'final', 'Ich würde gerne mit jemandem reden. Haben Sie Zeit für mich?', 4),

(@ss_vis, '👨‍👩‍👧', 'Meine Familie',        'final', 'Ich würde mich sehr freuen, meine Familie zu sehen.', 1),
(@ss_vis, '👫', 'Einen Freund',           'final', 'Ich würde gerne einen Freund oder eine Freundin sehen.', 2),
(@ss_vis, '👩‍⚕️', 'Einen Arzt',           'final', 'Ich möchte gerne mit einem Arzt oder einer Ärztin sprechen.', 3),
(@ss_vis, '🧑‍💼', 'Jemanden vom Personal','final', 'Könnten Sie bitte jemanden vom Personal zu mir schicken?', 4),

(@ss_act, '📺', 'Fernsehen',       'final', 'Ich würde gerne fernsehen. Können Sie mir helfen?', 1),
(@ss_act, '🎵', 'Musik hören',     'final', 'Ich möchte gerne Musik hören. Könnten Sie Musik anmachen?', 2),
(@ss_act, '🌿', 'Frische Luft',    'final', 'Ich würde gerne etwas frische Luft schnappen.', 3),
(@ss_act, '🃏', 'Spielen',         'final', 'Ich würde mich gerne beschäftigen. Haben Sie ein Spiel für mich?', 4),

(@ss_thanks, '🙏', 'Danke für Ihre Fürsorge',    'final', 'Ich möchte Danke sagen für Ihre tolle Fürsorge. Das bedeutet mir sehr viel!', 1),
(@ss_thanks, '💝', 'Danke für den schönen Besuch','final', 'Vielen Dank für Ihren Besuch! Es hat mich sehr gefreut.', 2),
(@ss_thanks, '😊', 'Danke, mir geht es besser',  'final', 'Danke für alles! Mir geht es jetzt viel besser.', 3),
(@ss_thanks, '⭐', 'Sie machen einen tollen Job!','final', 'Sie machen einen wirklich tollen Job. Danke!', 4);
