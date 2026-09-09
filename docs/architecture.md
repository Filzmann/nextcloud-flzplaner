# Architektur – AdPlaner

## Verantwortung

AdPlaner ist die kanonische Quelle für teambezogene Wunschdienstplanung,
Schichtdefinitionen, Monatspläne, Zuweisungen und Planungsstatus. AD Urlaub
bleibt die einzige schreibende Urlaubsquelle; AD Kalender und weitere Apps
werden ausschließlich über optionale read-only Verträge angebunden.

AdPlaner stellt belegte Schichten über den versionierten
`ScheduleConflictQueryEvent` als `Assistenz` bereit und konsumiert
Kalenderdienste derselben API als `Dienst/Büro`. Requester- und Source-App-ID
verhindern Eigenmeldungen. Halboffene Intervalle erlauben direkte Übergaben,
echte Überlappungen blockieren Planvorschlag, Festschichten und Zuweisungen.
Fehlende Provider sind ein gültiger Standalone-Zustand; Providerfehler werden
bei schreibenden Prüfungen nicht als Konfliktfreiheit behauptet.

## Fach- und Datenmodell

- Assistenzteams, EB-Rolle und Organisationsschlüssel stammen aus der
  gemeinsamen LocalBase-Organisationsdefinition.
- Teambezogene Schichtkonfiguration ist ein AdPlaner-Fachvertrag und wird
  durch die zuständige EB gepflegt.
- Eigene Wünsche und fremde Zuweisungen besitzen getrennte serverseitige
  Rechte; EB-Konten sind nicht selbst schichtfähig.
- Planungsstatus und Konflikte werden über zuständige Services verändert oder
  abgefragt. Optionale Provider dürfen einen Standalone-Monatsplan nicht
  blockieren.

## Schichten und Oberfläche

Controller bleiben dünn; Persistenz liegt in Repositorys, Fachentscheidungen
in Services und Browserlogik in getrennten Modulen und Komponenten. Der
Monatsplan verwendet den App-Root als vertikalen Scrollcontainer und leitet
Rechte niemals aus Sichtbarkeit oder Navigation ab.

Die Desktopmatrix und die bis 700 Pixel eingeblendete mobile Tagesliste sind
zwei Darstellungen desselben bereits serverseitig autorisierten Planpayloads.
Beide verwenden dieselben Komponenten und Aktionskennungen für Wünsche,
Zuweisungen, Präferenzen, Notizen, Konflikte und Status. Verdeckte
Desktop-Bedienelemente nehmen mobil weder an Fokusreihenfolge noch
Interaktion teil; mehrfach dargestellte Zuteilungsdialoge besitzen eindeutige
IDs. Zuteilungsdialoge und Schichtnotiz-Editoren werden relativ zur Oberfläche
ihres auslösenden Steuerelements geöffnet. Der App-Root bleibt der einzige
vertikale Seiten-Scroller.

## Datenschutz und Administration

PersonalDataProvider und PermissionProvider bilden Schichtbezüge,
Bearbeitungsreferenzen und die app-lokale temporäre Adminfreigabe ab. Native
Nextcloud-Administration allein erteilt keinen fachlichen Vollzugriff.

Der zusätzliche `ProcessingMetadataProvider` veröffentlicht den app-eigenen
Katalog `resources/privacy-processing.json` lazy über den öffentlichen
Standalone-V1-Vertrag des Datenschutz-Centers. Der Katalog ist die kanonische
Policyquelle für die Verarbeitungen `shift_planning_management` und
`temporary_admin_full_access`, enthält keine personenbezogenen Laufzeitdaten
und ersetzt fehlende fachliche Entscheidungen nicht durch technische Defaults.
