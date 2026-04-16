<?php
// ============================================================
//  MEINE STIMME – Konfiguration (Vorlage)
//
//  ANLEITUNG:
//  1. Diese Datei kopieren: cp config.example.php config.php
//  2. Die Werte unten mit Ihren echten Zugangsdaten befüllen
//  3. config.php wird NICHT ins Git-Repository eingecheckt
// ============================================================

define('DB_HOST', 'localhost');       // Bei ALL-INKL immer "localhost"
define('DB_NAME', 'DEIN_DATENBANKNAME'); // z.B. "w0123456_meinestimme"
define('DB_USER', 'DEIN_DATENBANKUSER'); // z.B. "w0123456_admin"
define('DB_PASS', 'DEIN_PASSWORT');      // Ihr gewähltes Datenbankpasswort
define('DB_CHARSET', 'utf8mb4');

// CORS – erlaubte Herkunftsdomains
define('ALLOWED_ORIGIN', 'https://meine-stimme.info'); // Nur eigene Domain!

// Sprache
define('DEFAULT_LANG', 'de');

// Debug (immer false wenn live!)
define('DEBUG_MODE', false);
