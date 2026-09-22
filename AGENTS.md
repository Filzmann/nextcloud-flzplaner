# AGENTS.md - AdPlaner

## Projekt

Nextcloud-App `adplaner` fuer Assistenzdienst-/Dienstplanungsablaeufe.

Lokale App-URL in der gemeinsamen DDEV-Umgebung:

    https://nextcloud-dev.ddev.site/apps/adplaner/

Nextcloud-App-ID:

    adplaner

Die priorisierte Produktplanung und offene Entscheidungen stehen in `ROADMAP.md`; verbindliche Fach-, Sicherheits- und Architekturregeln bleiben in dieser Datei.

## Zielsetzung

AdPlaner bildet die Wunschdienstplanung in Assistenzteams ab. Urlaubsplanung gehört ausschließlich in die separate App `adurlaub`.

Kernprozess:

- Assistenznehmer werden aus Nextcloud-Gruppen mit dem gemeinsam konfigurierten Assistenzteam-Präfix abgeleitet.
- EB-Rechte erhalten Nutzer*innen, die zugleich im Team und in der gemeinsam konfigurierten EB-Rollengruppe sind.
- Monatliche Wunschplaene werden je Assistenzteam erstellt.
- Die Schichtliste ist variabel konfigurierbar; Standard ist 08-14, 14-20 und 20-08.
- Luecken und Ueberlappungen in Schichtdefinitionen sind moeglich und duerfen nicht vorschnell wegvalidiert werden.
- Zuweisungen je Schicht folgen der Rollenlogik: eigene Eintraege durch Assistenz, fremde Zuweisungen nur durch EB.
- EB-Konten selbst sind nicht schichtfaehig.
- Statuswechsel wie `planned` und `approved` erfolgen nur durch EB.

Offene Zielbereiche:

- Produktive Rechte- und Datenschutzpruefung, Export, Benachrichtigungen, Dienstplan-Festschreibung und spaetere bedarfsgesteuerte Konfigurierbarkeit sind in `ROADMAP.md` gebuendelt.

## Repository und gemeinsamer Arbeitsablauf

- Dieses Verzeichnis ist ein eigenstaendiges Git-Repository fuer die AD-App `adplaner`.
- Andere eigene Nextcloud-Apps, zum Beispiel `brtop`, leben in eigenen Repositories.
- Diese Datei und lokal referenzierte Skills bilden bei einem direkten Start in diesem Repository die vollständige Repository-Steuerung.
- Fuer Git-, Sandbox-, DDEV-/`occ`-Sicherheit, Verifikation und Learning Candidates gilt der lokal mitgefuehrte Skill `work-in-nextcloud-app`; die folgenden AdPlaner-Regeln und Pruefungen ergaenzen ihn.

## DDEV

Die gemeinsame Nextcloud-DDEV-Umgebung wird aus dem dokumentierten
Parent-Unterverzeichnis `nextcloud-dev` gesteuert. Bei einem eigenständigen
Checkout ist der lokale DDEV-Pfad zuerst anhand der realen Umgebung zu
ermitteln.

AdPlaner nutzt gemeinsame Basisbausteine aus der Hilfsapp `localbase`. In der lokalen Nextcloud muss `localbase` aktiviert sein, bevor AdPlaner vollstaendig lauffaehig ist.

Wichtige Pruefungen:

    ddev exec -d /var/www/html/html php occ app:list | grep -i localbase
    ddev exec -d /var/www/html/html php occ status
    ddev exec -d /var/www/html/html php occ app:list
    ddev exec -d /var/www/html/html php occ upgrade

## Fachkontext

Gruppenschema fuer `adplaner`:

Die folgenden IDs sind initiale Standardwerte. Assistenzteam-Präfix, sichtbarer Teamname, maximale Kürzellänge, EB-Rollengruppe und Bereiche stammen aus der gemeinsamen `AdOrganizationDefinition` und dürfen nicht zusätzlich im AdPlaner festverdrahtet werden.

- Assistenznehmer-Gruppen: `ad-ASN-<Kuerzel>`, zum Beispiel `ad-ASN-TeamA`, `ad-ASN-TeamB`, `ad-ASN-TeamC`.
- `<Kuerzel>` ist das Kuerzel eines Assistenznehmers und darf Unicode-Buchstaben sowie Ziffern enthalten.
- EB-Rechte: Nutzer*innen, die zugleich in der Assistenznehmer-Gruppe und der gemeinsamen Rollengruppe `ad-EB` sind. Rollen-/Bereichskombinationen werden nicht als eigene Gruppen akzeptiert.
- Bereichszuordnungen werden app-uebergreifend separat als `ad-Bereich-<Name>` gepflegt; kombinierte Rollen-/Bereichsgruppen werden dynamisch abgeleitet.
- AdPlaner und AD Urlaub verwenden dieselben Assistenzteam-Gruppen; separate Suffix-Gruppen werden nicht unterstützt.
- Der app-eigene Adminabschnitt installiert Demo-Inhalte nur nach ausdrücklicher Bestätigung. Das Pack legt Team A, Team B und Team C mit ausschließlich synthetischen lokalen Konten und Standardschichten an; WordPress-Bestandsdaten werden nicht importiert.
- Native Nextcloud-Administration allein erteilt kein Recht auf den fachlichen
  Demo-Datenpfad. Dafür ist je Admin eine app-lokale Freigabe von höchstens 24
  Stunden erforderlich; Beginn, geplantes Ende und Widerruf werden
  protokolliert. Ausschließlich Mitglieder der kanonischen Nextcloud-Gruppe
  `Datenschutzbeauftragte` dürfen die Freigabe in der Hauptoberfläche erteilen
  oder widerrufen; nativer Adminstatus allein reicht dafür nicht. Der sichere
  Eintrittshinweis erscheint nur für betroffene native Administrationskonten
  und verlinkt die Steuerung nur bei zusätzlicher Datenschutzrolle.
- Fremde oder LDAP-verwaltete Konten werden nicht als Demokonten übernommen. Bestehende read-only LDAP-Team- oder Rollengruppen brechen die Demo-Installation im Preflight vor jeder Mutation ab.
- Ausschließlich lokal erzeugte Test- und Demokonten erhalten initial ihr
  jeweiliges Benutzerkürzel als Passwort. Diese bewusst einfache Vorgabe darf
  nicht auf Staging, Produktion, echte Konten oder externe Benutzer-Backends
  übertragen werden.
- Schichten werden ausschließlich über die strukturierte Schichtkonfiguration verwaltet. Frühere einzelne Legacy-Parameter für Früh-, Spät- oder Nachtschichten werden nicht weitergeführt.
- Die Schichtkonfiguration eines Assistenzteams ist eine delegierte fachliche Teamkonfiguration und wird durch die zuständige EB im AdPlaner gepflegt. Sie ist keine ausschließlich für Nextcloud-Admins bestimmte organisationsweite Einstellung und gehört deshalb nicht in den Suite-Adminbereich.

## Architekturregeln

- Der lokale Skill `work-in-nextcloud-app` ist die kanonische Quelle für
  gemeinsame Schichtungs-, Modell-, Sicherheits-, UI- und Testregeln.
- Teambezogene Schichtkonfiguration bleibt ein AdPlaner-Fachvertrag und wird
  durch die zuständige EB gepflegt; sie wird nicht in eine allgemeine
  Suite-Einstellung verschoben.
- AdPlaner-spezifische API-Pfade, Modelle, Workflows und Darstellung bleiben
  in diesem Repository.
- Gemeinsame Bausteine werden erst nach LocalBase verschoben, wenn mindestens
  zwei Apps denselben semantischen und testbaren Vertrag benötigen.
- WordPress-Kompatibilität und parallele Urlaubspersistenz sind unzulässig.
- Der `PersonalDataProvider` registriert sich lazy über den öffentlichen
  Standalone-V1-Vertrag von `filzmann_data_protection` und weist eigene
  Schichtwünsche/-zuweisungen sowie
  interne Bearbeitungsreferenzen an Zuweisungen, Tagesnotizen und Monatsplänen
  aus. Fremde Personenkennungen und unkontrollierte freie Tagesnotiztexte
  werden nicht in die Self-Service-Auskunft übernommen.
- Der `ProcessingMetadataProvider` registriert sich ebenfalls lazy über den
  öffentlichen V1-Vertrag des Datenschutz-Centers. Seine einzige fachliche
  Policyquelle ist `resources/privacy-processing.json`; sie enthält keine
  personenbezogenen Laufzeitdaten und weist ungeklärte Entscheidungen als
  `PRIVACY-DECISION-REQUIRED` aus.
- PersonalDataProvider und PermissionProvider umfassen die app-lokale
  Adminfreigabe; fremde Admin-Kennungen werden in der Selbstauskunft nicht
  offengelegt.

## Verbindliche Navigation und optionale Integration

- Ohne aktive OrgSuite registriert AdPlaner einen eigenen Nextcloud-Hauptnavigationseintrag. Ab zwei AD-Produkten ersetzt `orgsuite` diesen durch den gemeinsamen Einstieg `AD`.
- Das Template stellt den optionalen Menühost mit `data-suite="ad"` und `data-current-app="adplaner"` bereit, lädt aber keine OrgSuite-Assets direkt.
- Ohne AD Urlaub oder AD Kalender bleibt die Assistenzplanung eigenständig nutzbar; optionale Abwesenheits- und Konflikthinweise dürfen den Monatsplan nicht blockieren.
- Team- und Planungsrechte bleiben ausschliesslich serverseitig im AdPlaner; Menuesichtbarkeit ist keine Berechtigung.
- Der deckende Hintergrund und das vertikale Scrolling liegen am App-Root `#adplaner-app`; globale Nextcloud-Container wie `#content` werden nicht ueberschrieben.


## Tests

Vor groesseren Refactorings zuerst Charakterisierungstests fuer das bestehende gewuenschte Verhalten schreiben oder aktualisieren.

- Tests sind Teil der Architekturarbeit und kein optionaler Nachtrag. Neue oder refaktorierte AdPlaner-Fachlogik bekommt passende Charakterisierungs-, Unit-, Contract- oder Smoke-Tests, bevor darauf weiter aufgebaut wird.
- Schnelle PHP-Suite: `php tests/run.php`
- Schnelle JavaScript-Suite: `node tests/run-js.mjs`
- Authentifizierter DOM-/CSRF-/API-Smoke: `ADP_BASE_URL=... ADP_USER=... ADP_PASSWORD=... tests/http-smoke.sh`
- Nach LocalBase-Aenderungen mindestens die betroffenen AdPlaner-Smoke-/Contract-Tests laufen lassen.
- Gemeinsame LocalBase-Test-Helper nutzen, wenn dadurch echte Setup-Duplizierung verschwindet, ohne die Lesbarkeit des einzelnen Tests zu verschlechtern.
- Bei Controller-, DI-, Migrations- oder Nextcloud-Container-Aenderungen zusaetzlich gezielte DDEV-/`occ`-Checks ausfuehren.

Wichtige lokale Pruefungen:

    php tests/run.php
    node tests/run-js.mjs

Einzelne Checks, die durch die Testlaeufer gebuendelt werden:

    find js -name '*.js' -print0 | xargs -0 -n1 node --check
    node tests/js/model-smoke.js
    node tests/js/plan-repository-smoke.js
    for f in tests/Service/*.php; do php "$f"; done

## Dokumentenverantwortung

- `README.md` beschreibt ausschließlich den aktuellen nutzbaren Stand,
  Installation, Betrieb, Tests und den Dokumentationsindex.
- `ROADMAP.md` enthält ausschließlich offene, zurückgestellte oder
  freigabepflichtige Arbeit und Entscheidungen.
- `CHANGELOG.md` dokumentiert erledigte Änderungen releasebezogen; erledigte
  Checklisten verbleiben nicht in der Roadmap.
- `docs/architecture.md` ist die ausführliche Quelle für geltende fachliche
  und technische Architekturverträge.
- `docs/manual-acceptance.md` enthält wiederholbare manuelle Prüfungen und
  keine Produktplanung.
- `AGENTS.md` enthält ausschließlich verbindliche Arbeits-, Sicherheits-,
  Architektur- und Prüfregeln. Zusätzliche Dokumente werden in `README.md`
  mit eindeutiger Zuständigkeit eingeordnet.

## Parent-Governance-Vertrag: 2

- Die für dieses Subrepository anwendbaren Regeln des Parent-Workspaces sind
  verbindlich. Dazu gehören insbesondere app-übergreifende ADRs und
  öffentliche Verträge, Repositorygrenzen sowie Workspace-, Delivery- und
  Release-Gates.
- Diese lokale `AGENTS.md` und die lokalen Skills bleiben die vollständige,
  ohne Parent-Checkout arbeitsfähige Repository-Steuerung. Die anwendbaren
  Parent-Regeln werden dafür hier oder in den lokalen Skills mitgeführt.
- Repository-lokale Regeln dürfen Parent-Verträge konkretisieren und verschärfen,
  aber nicht abschwächen oder umgehen.
- Bei einem Widerspruch gilt bis zur Klärung die strengere Regel. Die Arbeit
  stoppt, bis die kanonische Quelle bestimmt, die Regelprojektionen
  synchronisiert und eine erforderliche Entscheidung dokumentiert ist.
- Ist der Parent-Workspace nicht verfügbar, bleibt die lokale Steuerung
  wirksam. Vor Cross-App-, Release- oder Delivery-Arbeit muss ein vermuteter
  neuerer Parent-Stand oder eine Regelungslücke zuerst gegen den Parent
  geprüft werden.

### Entwicklungsphase und Kompatibilitätsbedarf

Entscheidung vom 5. September 2026: Das Gesamtprojekt befindet sich vollständig
in der Entwicklung. Es gibt kein PROD, keinen produktiven Datenbestand und
keinen bereits betriebenen Bestand mit zu erhaltendem Upgradepfad. STAGING
ist eine wegwerfbare Entwicklungs- und Integrationsumgebung und darf im
konkret beauftragten Reinstall vollständig neu aufgebaut werden. Wenige
externe Testnutzer ändern diese Einordnung nicht.

Vor einer Datenmigration, Legacy-Unterstützung, Compatibility Layer,
Deprecated API, Dual-Read/Dual-Write, einem Altschema-Fallback, Übergangsformat
oder der Unterstützung historischer Entwicklungsstände wird geprüft:

1. Wurde der betroffene Zustand jemals produktiv eingesetzt?
2. Benötigen reale Daten oder Nutzer seine Erhaltung?
3. Gibt es einen anderen konkreten technischen Erhaltungsgrund, insbesondere
   einen geltenden Plattform- oder externen API-Vertrag?

Sind alle relevanten Antworten nein, ist die saubere Breaking-Change-/
Reinstall-Lösung der Standard. Frühere rein interne Entwicklungsstände
begründen weder Abwärtskompatibilität noch eine Deprecationfrist.
Entwicklungsschemata dürfen durch ein kanonisches Installationsschema ersetzt,
alte interne APIs und Konfigurationsformate samt ausschließlich dafür
benötigten Adaptern und Tests entfernt werden. Architekturqualität und der
saubere Zielzustand haben Vorrang. Nextclouds nötige Installationsmigrationen
bleiben erhalten; ein Verzeichnisname `Migration` beweist keine Altlast.

Breaking Changes werden im selben Änderungskontext vollständig durchgezogen:
betroffene Provider, Consumer, standardisierte APIs, Vertragsversionen,
Metadaten, Tests und Dokumentation müssen zusammenpassen. Unterstützte
Nextcloud-/openDesk-Plattformverträge, externe Standards, Autorisierung und
Datenschutz gelten unverändert. Fehlende oder inkompatible optionale Provider
bleiben kontrolliert sichtbar. Ein Reinstall erlaubt keine privaten
Fremdtabellenzugriffe oder parallel erfundenen Plattformmechanismen.

Vor destruktiver Arbeit werden die tatsächlich benötigten externen
Testidentitäten, Gruppen, Rollen und nicht reproduzierbaren Testdaten gezielt
gesichert oder über bestehende native Setup-Strukturen reproduzierbar gemacht.
Echte Personen- und Zugangsdaten bleiben außerhalb von Git. Diese begrenzte
Sicherung begründet keine allgemeine Legacy-Unterstützung. Ein Reinstall
bleibt ein normaler unterstützter Entwicklungsweg; der vorhandene
Compatibility-Workflow besitzt den Fresh-Install-Nachweis, dessen aktueller
Belegstatus in `docs/workspace.md` beschrieben ist.

Diese Phase endet ausschließlich durch einen ausdrücklich dokumentierten,
von Simon freigegebenen **Production-Readiness-/Production-Freeze-Entscheid**.
Ein Release Candidate, eine Versionsnummer, ein Staging-Deployment oder ein
externer Testzugang lösen den Wechsel nicht aus. Der Entscheid wird in dieser
kanonischen Lifecycle-Quelle mit Datum, Geltungsbereich und betroffenem
Versions-/Datenstand festgehalten und in die lokale Steuerung projiziert.
Dann werden Upgradepfade, Datenbankmigrationen, Persistenz, Backup/Restore,
Rollback, Release-/API-Kompatibilitätszusagen, Deployment-/Freigabeprozess und
PROD→STAGING/COPY-Strategie neu bewertet. Eine vollständige PROD-Governance
wird jetzt nicht vorweggenommen.

Diese Regel entscheidet den Kompatibilitätsbedarf, erweitert aber keinen
Repository-Schreibauftrag und ersetzt keine Freigabe für eine konkrete
destruktive Aktion. Lokale Regelprojektionen folgen dem bestehenden
`docs/parent-governance-contract.md`; ein unsynchronisierter Einzel-Checkout
darf keinen abweichenden Phasenstand stillschweigend annehmen.

### Prüfaufwand

- Vor einem neuen Test, Scan, Linter, Architektur- oder Systemcheck wird
  geprüft, welcher bestehende Check dieselbe Eigenschaft bereits nachweist.
  Diesen erweitern oder sein nachweislich passendes Ergebnis wiederverwenden;
  ein zusätzlicher Check braucht eine benannte zusätzliche Fehlerklasse oder
  Vertrauensgrenze. Gleicher Input, gleiche Prüfung, gleiche Fehlerklasse und
  gleiche Phase begründen keinen zweiten Lauf. Gestaffelte Unit-, Contract-
  und Runtime-Nachweise bleiben erhalten. Die dokumentierten lokalen
  Prüfeinstiege bestimmen Umfang und Ergebnisgültigkeit.
