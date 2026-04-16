# Meine Stimme 🗣️

Eine kostenlose AAC-App (Unterstützte Kommunikation) für Menschen, die nicht oder kaum sprechen können — z.B. nach einem Schlaganfall, mit ALS oder anderen Erkrankungen.

**Live:** [meine-stimme.info](https://www.meine-stimme.info)

## Funktionen

- **Themen-Modus** – Geführte Kommunikation über Entscheidungsbäume (Essen, Soziales, Hygiene, Schmerzen, Gefühle)
- **Tippen-Modus** – Freies Schreiben mit kontextbasierter Wortvorhersage
- **Text-to-Speech** – Fertige Nachrichten werden vorgelesen
- **Adaptives Lernen** – Häufig genutzte Wörter erscheinen zuerst
- **PWA** – Installierbar auf Tablets und Smartphones, funktioniert offline
- **DSGVO-konform** – Keine externen Ressourcen, keine personenbezogenen Daten

## Installation

### Voraussetzungen
- PHP 8.x
- MySQL / MariaDB
- Webserver (Apache mit mod_rewrite)

### Setup

1. Dateien auf den Server hochladen
2. Datenbank anlegen und `sql/meine_stimme.sql` importieren
3. Konfiguration anlegen:
   ```bash
   cp api/config.example.php api/config.php
   ```
4. `api/config.php` mit echten Zugangsdaten befüllen
5. `.htaccess` aktivieren (Datei umbenennen falls nötig)

## Projektstruktur

```
├── index.html          # Haupt-App (Single Page)
├── impressum.html      # Impressum
├── manifest.json       # PWA-Manifest
├── sw.js               # Service Worker (Offline-Cache)
├── admin.php           # Admin-Panel
├── api/
│   ├── index.php       # REST-API
│   ├── db.php          # Datenbankverbindung
│   └── config.example.php  # Konfigurationsvorlage
├── sql/
│   └── meine_stimme.sql    # Datenbankschema + Seed-Daten
└── assets/
    ├── fonts/          # Nunito (lokal, kein Google Fonts)
    ├── logo.svg
    ├── icon-192.svg
    └── icon-512.svg
```

## Lizenz

Dieses Projekt ist frei nutzbar für nicht-kommerzielle Zwecke.
