# Roadmap – AdPlaner

Diese Datei bündelt geplante Erweiterungen und offene Produktentscheidungen. Verbindliche Fach-, Sicherheits- und Architekturregeln stehen in `AGENTS.md`.

## Aktueller Fokus

- Die manuellen Prüfungen werden im ausfüllbaren
  [`docs/manual-acceptance.md`](docs/manual-acceptance.md) dokumentiert.
- Produktive Rechte- und Datenschutzprüfung der Wunschdienstplanung.
- Monatsplan, variable Schichten, EB-Koordination und Standalone-Betrieb auf einem realitätsnahen Staging fachlich abnehmen.

## Geplante Erweiterungen

- **ADP-MOBILE – smartphone-taugliche Planung und kompakte Menüs:**
  Monatsplan, persönliche Einsätze, Schichtauswahl und die wichtigsten
  Planungsaktionen erhalten eine auf kleinen Smartphone-Viewports vollständig
  nutzbare responsive Darstellung. Die Lösung darf nicht nur die
  Desktop-Matrix horizontal scrollbar machen; Prioritäten, Status,
  Schichtzeiten, Zuständigkeit und erlaubte Aktionen müssen ohne Verlust des
  fachlichen Kontexts erreichbar bleiben. Menüs und Filter werden kompakter
  gruppiert, wobei häufige Aktionen direkt sichtbar sowie Beschriftungen,
  Tastaturbedienung, Fokus und ausreichend große Touch-Ziele erhalten bleiben.
  Vor der Umsetzung werden die mobilen Kernabläufe für Assistenz und EB
  festgelegt. Tests decken mindestens kleine Viewports, beide Rollen,
  Menü-/Filterbedienung, Fokusreihenfolge, Zoom, lange Beschriftungen sowie
  vertikales und gegebenenfalls lokal begrenztes horizontales Scrollen ab.
- **ADP-L10N – vollständige Lokalisierung (später, nicht freigegeben):**
  AdPlaner wird im Rahmen des suiteweiten L10N-Rollouts auf die aktive
  Nextcloud-Locale und Nextcloud-l10n umgestellt. Manuelle Monats- und
  Wochentagsnamen sowie sichtbare UI-, Status-, Validierungs- und
  Fehlermeldungen werden dabei vollständig migriert. ISO-Daten,
  Monatsnummern, Schichtzeiten, Statuswerte, Teamcodes und API-Schlüssel
  bleiben unverändert; Abkürzungen werden nicht durch Abschneiden gebildet.
  Erforderlich sind Tests für deutsche Ausgabe, mindestens eine weitere
  Locale, Fallback, Monats-/Jahresgrenzen, Pluralformen, Platzhalter und
  Escaping in PHP und JavaScript. Pilot-App, Reihenfolge und Rohtext-Gate
  werden vor Umsetzung suiteweit separat freigegeben.
- Persönliche Monatsansicht „Alle meine Einsätze“ mit PDF-Export und optionaler Verbindung zu gängigen Kalendern.
- Benachrichtigungen für relevante Planungs- und Statusänderungen.
- Teambezogene Konfigurierbarkeit nur dort erweitern, wo konkrete Teams unterschiedliche Regeln benötigen.

## Vor der Umsetzung zu klären

- Exportformate, Zielsysteme und Datenschutzumfang.
- Benachrichtigungskanäle, Empfänger*innen und auslösende Ereignisse.
