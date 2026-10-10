# Changelog

## Unreleased

- Nextcloud 35.0.1 durch Fresh Install, Upgrade 34→35 sowie Provider-,
  Berechtigungs-, Runtime-, UI-/API- und Asset-Smokes nachgewiesen und den
  unterstützten Bereich auf die lückenlosen Hauptversionen 33 bis 35
  erweitert. Nextcloud 36 bleibt ungeprüft.
- Temporären fachlichen Admin-Vollzugriff auf höchstens 24 Stunden begrenzt:
  Nur `Datenschutzbeauftragte` dürfen aktuelle Nextcloud-Administrationskonten
  in der Hauptoberfläche freigeben oder widerrufen; native Administration
  allein bleibt ohne Freigabe- und Historienzugriff.
- Einen app-eigenen Processing-Metadata-Katalog für Wunschdienstplanung und
  temporäre Adminfreigaben über den öffentlichen V1-Vertrag des
  Datenschutz-Centers veröffentlicht.
- Den unterstützten Nextcloud-Bereich nach grüner Neuinstallation auf 33.0.7
  und grünem Upgrade mit synthetischen Bestandsdaten auf 34.0.2 auf die
  lückenlosen Hauptversionen 33 bis 34 erweitert.
- Permission-Provider-Listener an den Nextcloud-EventListener-Vertrag
  angepasst.
- Die mobile Schichtnotizbearbeitung an die sichtbare Tageslistenprojektion
  gebunden.
- Den Monatsplan auf Smartphone-Viewports als semantische Tagesliste mit
  Schichtzeiten, Status, Hinweisen, Anmerkungen und den bestehenden
  rollenabhängigen Assistenz-/EB-Aktionen umgesetzt.
- Kompakte Tab-Navigation, mindestens 44 Pixel hohe Touchziele und eindeutig
  zugeordnete Zuteilungsdialoge für die mobile Bedienung ergänzt; genehmigte
  und unbekannte Status bleiben auch dort fail-closed.
- Dokumentations- und Steuerungsstruktur vereinheitlicht.
- Belegte Assistenzschichten als versionierte read-only Planungskonflikte
  veröffentlicht; Kalenderdienste blockieren Planvorschlag, Festschichten und
  manuelle Zuweisungen und erscheinen datensparsam als `Dienst/Büro`.

## 0.7.0-rc.2

- Persönliche regelmäßige Festschichten werden wöchentlich materialisiert; bestätigte Einzel-Löschungen bleiben als Ausnahme bestehen.
- Mehrfache feste Belegungen desselben Slots können an die EB eskaliert und vor der Genehmigung aufgelöst werden.
- Die Auslastung öffnet aus dem Tab-Menü als Overlay; Kalenderwochen sind im Plan sichtbar und zählen vollständig über Monatsgrenzen hinweg.

## 0.6.0-rc.1

- Eigene Schichtwünsche können als Lieblingsschicht oder „nur im Notfall“ gekennzeichnet und mit einer teamweit sichtbaren Anmerkung versehen werden.
- Neutrale Schichtchips zeigen nur Name und Löschaktion; Favorit, „nur wenn sonst niemand kann“ und Anmerkungsaktion liegen in einem per Hover oder Tastaturfokus erreichbaren Overlay mit Tooltips. Schichtanmerkungen erscheinen mit Nutzer- und Schichtbezug unter den EB-Bemerkungen und sind dort für den Ersteller bearbeitbar und löschbar.
- Persönliche Wochen- und Monatsgrenzen für gewünschte Schichten sowie eigene und EB-weite Auslastungsübersichten ergänzt.
- Die Auslastung besitzt einen eigenen Tab mit kompakten Mindest-/Maximalwerten; für die EB wird daraus ein unverbindlicher Planvorschlag mit festen Zuteilungen, Lieblings-, neutralen und Notfallwünschen abgeleitet.
- Schichten unter Minimum werden kräftig und textlich, Schichten über Maximum blasser und textlich markiert; Kalenderwochen werden auch über Monatsgrenzen korrekt gezählt.
- Berechtigungs- und Datenschutzprovider, additive Migration sowie selbstbereinigende Integrationsverträge um die neuen Datenklassen erweitert.

## 0.5.0-rc.1

- Subjectgebundene persönliche Datenauskunft für Schichtwünsche, Schichtzuweisungen und eigene Bearbeitungsreferenzen ergänzt.
- Fremde Personenkennungen und potenziell drittpersonenbezogene freie Tagesnotiztexte aus der Self-Service-Auskunft ausgeschlossen.

## 0.4.0-rc.2

- Monatsplanänderungen und Statuswechsel gegen konkurrierende Freigaben serialisiert; genehmigte Schichtdefinitionen bleiben als unveränderlicher Planstand erhalten.
- Strikte Monats-, Datums-, UTF-8-, Längen-, Boolean- und Schichtlistenvalidierung ergänzt.
- Teameinstellungen, Tagesbemerkungen und Zuweisungen beim konkurrierenden erstmaligen Speichern gegen Unique-Constraint-Konflikte abgesichert.
- Geleerte Tagesbemerkungen samt Bearbeitungsmetadaten entfernt; öffentliche Team- und Hinweisantworten auf aktuell schichtfähige Personen und benötigte Anzeigenamen reduziert.
- Technische Monats-Locks von personenbezogenen Änderungsmetadaten getrennt und interne Demo-Fehler ohne Detailleck als Serverfehler beantwortet.
- Fehler optionaler Hinweisprovider isoliert und öffentliche Planpayloads um interne Erstellerkennungen sowie teamfremde Hinweise bereinigt.
- Lade-, Speicher- und Zuteilungsoberfläche gegen veraltete Antworten, Doppelstarts, unklare Nachladefehler sowie Tastatur-, Tabellen- und Fokusprobleme stabilisiert.

## 0.4.0-rc.1

- Kontrollierte Monatsplanstatus `draft`, `planned` und `approved` ergänzt; nur die zuständige Einsatzbegleitung darf wechseln und genehmigte Pläne sind gegen Änderungen gesperrt.
- Optionale, datensparsame Urlaubs- und Kalenderhinweise über die öffentlichen LocalBase-Verträge eingebunden; fehlende Provider bleiben ein gültiger Standalone-Zustand.
- Direkte Navigation zum vorherigen und nächsten Monat sowie sichtbarer Tabellen-Scrollbereich und fixierte Bemerkungsspalte ergänzt.
- Additive Monatsplanstatus-Migration sowie automatisierte Service-, UI-, Migrations-, Datenbank- und DDEV-Rechteprüfungen ergänzt.

## 0.3.0-rc.1

- Eigenständige Navigation ohne OrgSuite ergänzt.
- Assistenzplanfähigkeit über den optionalen LocalBase-Integrationsvertrag veröffentlicht.
- Ungültige harte App-Abhängigkeiten aus den Nextcloud-Metadaten entfernt.

## 0.2.9-rc.1

- Öffentliche Projekt-, Quellcode- und Fehlerkanäle ergänzt.
- Neutrale Assistenzteams Team A, Team B und Team C in Beispielen und Tests.

## 0.2.8-rc.1

- Erster reproduzierbarer Staging-Releasekandidat für Nextcloud 34 und PHP ab 8.3.
- Dynamische Assistenzteams und variable Schichtkonfiguration.
- Gemeinsame Suite-Navigation und barrierefrei bedienbare Plan-/Einstellungstabs.
- Authentifizierter DOM-, CSRF- und API-Smoke.
