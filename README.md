# Radar

Außenwahrnehmung ohne Deutung (VSM System 4): Radar abonniert Umwelt-Feeds (RSS 2.0, RSS 1.0/RDF, Atom)
und sammelt ihre Einträge **roh und dedupliziert** – Titel, Link, Auszug, Autor, Datum.

Radar **bewertet nichts**: keine Zusammenfassung, keine Relevanz, kein Sprachmodell. Was davon zählt, entscheiden
Agenten und Menschen beim Lesen. Radar kennt kein anderes Fachmodul; andere Module können über die Morph-Aliase
`radar_source` / `radar_item` auf Quellen und Einträge verweisen.

## Daten

- `radar_sources` – Quelle: `name`, `url`, `source_type` (`rss`), `topic`, `cluster`, `note` (wofür die Quelle da ist),
  `pull_interval_hours`, `is_active`, `last_pulled_at`, `last_error`, `error_count`.
- `radar_items` – Eintrag: `title`, `url`, `excerpt`, `author`, `published_at`, `fetched_at`; eindeutig je Quelle über
  `fingerprint` (guid → Link → Titel+Datum).

## Abläufe

- `radar:pull` – stündlich (:07): ruft fällige aktive Quellen ab (Intervall je Quelle). `--source=ID`, `--force`.
  Fehler landen an der Quelle (`last_error`, `error_count`); der Abrufzeitpunkt wird trotzdem gesetzt.
- `radar:prune` – täglich 03:37: löscht Einträge älter als `radar.retention_days` (Standard 90, `RADAR_RETENTION_DAYS`, 0 = nie).

**Datenhaltung:** Einträge sind Durchlauf, keine Archivierung. Was bleiben soll, ist die Erkenntnis – sie lebt beim Leser
(Gedächtnis, Signal, Management Book, Notiz) und trägt Titel und Link des Eintrags als Quellenangabe. Radar weiß nicht,
wer was zitiert, und hält deshalb auch nichts zurück.

## MCP-Tools

| Tool | Zweck |
|---|---|
| `radar.items.GET` | Einträge lesen. Filter: `source_id`, `topic`, `cluster`, `search`, `since` (abgerufen ab), `published_after/before`, `id` |
| `radar.sources.GET` | Quellen mit Thema, Notiz, letztem Abruf, Fehler, Anzahl Einträge |
| `radar.sources.POST` | Quelle abonnieren (gleiche URL im Team → bestehende zurück) |
| `radar.sources.PUT` | Quelle ändern / pausieren |
| `radar.sources.DELETE` | Abbestellen (Soft-Delete, Einträge bleiben) |
| `radar.sources.pull.POST` | Quelle sofort abrufen |

## Oberfläche

- `/radar` – Einträge, neueste zuerst, Filter nach Thema, Quelle, Suche.
- `/radar/sources` – Quellen pflegen, pausieren, sofort abrufen.

## Tests

`tests/run-in-taiste.sh tests/Feature/RadarTest.php` (sqlite in der lokalen taiste-Kopie; nur Core + Radar).
Ein Regel-Test stellt sicher, dass `src/` kein anderes Fachmodul und kein Sprachmodell kennt.
