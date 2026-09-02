# Roadmap – AdPlaner

Diese Datei bündelt geplante Erweiterungen und offene Produktentscheidungen. Verbindliche Fach-, Sicherheits- und Architekturregeln stehen in `AGENTS.md`.

## Nextcloud-Kompatibilitätsgate

### ADP-NC-COMPAT – OpenDesk-Boden 33 und künftige Majors nachweisen

Status: `info.xml` bleibt bei 34/34; NC 33.0.7 ist bislang nur statisch
plausibel. Vor `min-version="33"` müssen der vorhandene rote PHP-Konflikttest
unabhängig geklärt sowie Fresh Install/Upgrade, DI, Migrationen,
Wunschdienst-/Konfliktpfade, Jobs, Standalone- und Kalenderkombination,
Assets und sichtbare Oberfläche auf NC 33 grün sein. Anschließend wird jede
weitere deklarierte Major lückenlos mit
`verify-nextcloud-future-compatibility` geprüft; die Obergrenze ist die
höchste grüne Major und kein festes „latest“.

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
- **ADP-L10N – app-lokaler Umsetzungsschnitt (systemweit gegatet):** Erst
  nach Freigabe des Root-Vorhabens `ZM-06` sichtbare Texte, Monats- und
  Wochentagsnamen auf Nextcloud-l10n umstellen. ISO-Daten, Monatsnummern,
  Schichtzeiten, Statuswerte, Teamcodes und API-Schlüssel bleiben
  sprachneutral; Deutsch, eine weitere Locale, Fallback, Plural,
  Platzhalter und Escaping werden app-lokal getestet.
- Persönliche Monatsansicht „Alle meine Einsätze“ mit PDF-Export und optionaler Verbindung zu gängigen Kalendern.
- Benachrichtigungen für relevante Planungs- und Statusänderungen.
- Teambezogene Konfigurierbarkeit nur dort erweitern, wo konkrete Teams unterschiedliche Regeln benötigen.

## Vor der Umsetzung zu klären

- Exportformate, Zielsysteme und Datenschutzumfang.
- Benachrichtigungskanäle, Empfänger*innen und auslösende Ereignisse.
