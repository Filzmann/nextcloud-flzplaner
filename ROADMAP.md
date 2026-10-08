# Roadmap – FlzPlaner

Diese Datei enthält ausschließlich offene Arbeit, zurückgestellte Vorhaben
und Freigabegates. Der aktuelle Funktionsumfang steht in `README.md`,
erledigte Änderungen in `CHANGELOG.md` und geltende Architektur in
`docs/architecture.md`.

## Aktueller Fokus

- Die manuellen Prüfungen werden im ausfüllbaren
  [`docs/manual-acceptance.md`](docs/manual-acceptance.md) dokumentiert.
- Den umgesetzten temporären Admin-Vollzugriff einschließlich DPO-Steuerung,
  Rollenmatrix, CSRF und Tastaturbedienung in DDEV und auf Staging abnehmen.
- Produktive Rechte- und Datenschutzprüfung der Wunschdienstplanung.
- Monatsplan, variable Schichten, EB-Koordination und Standalone-Betrieb auf einem realitätsnahen Staging fachlich abnehmen.
- Die mobile Tagesliste für Assistenz und EB in realen Smartphone-Browsern
  mit langen Beschriftungen, Tastatur/Fokus, 200-Prozent-Zoom, Touchzielen
  und vertikalem Scrollen abnehmen.

### FLZP-STAGING-FOLLOWUP – Abweichungen der laufenden Abnahme schließen

- Die Planstatus `planned` und `approved` einschließlich der fachlichen
  Änderungssperre vollständig in Oberfläche und serverseitigem Vertrag
  abbilden.
- Optionale Urlaubs- und Kalenderhinweise ausschließlich read-only anzeigen;
  ihr Fehlen darf den Standalone-Monatsplan weiterhin nicht blockieren.
- Fremdänderungen durch normale Teammitglieder auch per direktem Request
  negativ prüfen und die Mutationsfreiheit belegen.
- Monatsnavigation, dauerhaft erreichbare horizontale Scrollleiste,
  Kommentar-Speicheraktion und Mitarbeiterauswahl nacharbeiten.
- Die in der Abnahme zusätzlich unterhalb des Monatsplans erschienene
  Schichtdarstellung prüfen und eine unbeabsichtigte doppelte Darstellung
  entfernen.

## Geplante Erweiterungen

- Persönliche Monatsansicht „Alle meine Einsätze“ mit PDF-Export und optionaler Verbindung zu gängigen Kalendern.
- Benachrichtigungen für relevante Planungs- und Statusänderungen.
- Teambezogene Konfigurierbarkeit nur dort erweitern, wo konkrete Teams unterschiedliche Regeln benötigen.

## Vor der Umsetzung zu klären

- Exportformate, Zielsysteme und Datenschutzumfang.
- Benachrichtigungskanäle, Empfänger*innen und auslösende Ereignisse.

## Bewusst zurückgestellt – niedrigste Priorität

### FLZP-L10N – app-lokaler Umsetzungsschnitt

Status seit 17. September 2026: Die Umsetzung beginnt erst nach allen höher
priorisierten Roadmap-Aufgaben und einer erneuten ausdrücklichen Freigabe des
Root-Vorhabens `ZM-06`. Neue Funktionen und Codeänderungen berücksichtigen
die spätere Lokalisierbarkeit an den jeweils berührten Stellen, lösen aber
keine flächige Umstellung oder Übersetzungsimplementierung aus.

Bei der späteren Umsetzung werden sichtbare Texte sowie Monats- und
Wochentagsnamen auf Nextcloud-l10n umgestellt. ISO-Daten, Monatsnummern,
Schichtzeiten, Statuswerte, Teamcodes und API-Schlüssel bleiben
sprachneutral; Deutsch, eine weitere Locale, Fallback, Plural, Platzhalter
und Escaping werden app-lokal getestet.
