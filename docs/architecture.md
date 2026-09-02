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

## Datenschutz und Administration

PersonalDataProvider und PermissionProvider bilden Schichtbezüge,
Bearbeitungsreferenzen und die app-lokale temporäre Adminfreigabe ab. Native
Nextcloud-Administration allein erteilt keinen fachlichen Vollzugriff.
