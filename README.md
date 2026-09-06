# AdPlaner

Monatliche Wunschdienstplanung für Assistenzteams. Urlaubsplanung liegt ausschließlich in der separaten App `adurlaub`.

## Staging-Kompatibilität

- Nextcloud 33 und 34
- PHP 8.3 oder neuer innerhalb des von der jeweiligen Nextcloud-Version unterstützten Bereichs
- Laufzeitbasis: `localbase`; `orgsuite` ist ab zwei AD-Fachprodukten optional aktiv
- App-ID und Installationsordner: `adplaner`

Der deklarierte Bereich wurde mit einer frischen Installation auf Nextcloud
33.0.7 und einem anschließenden Upgrade mit synthetischen Bestandsdaten auf
34.0.2 geprüft. Dabei waren Datenbankmigration, Kommandoauflösung,
Rollen-/Negativpfade, Privacy- und Permission-Provider, Assets sowie die
sichtbare mobile Oberfläche grün.

## Installation

Für Staging und Auslieferung das Produktbundle `ad-product-adplaner-<release>.tar.gz` und dessen enthaltenes `install.sh` verwenden. Es prüft und installiert LocalBase automatisch; ab dem zweiten AD-Fachprodukt aktiviert es OrgSuite.

AdPlaner funktioniert einzeln; optionale Abwesenheits- oder Kalenderhinweise entfallen ohne die jeweilige Fachapp, ohne den Monatsplan zu blockieren.

Assistenzteams werden aus den zentral konfigurierten Nextcloud-Gruppen abgeleitet. Teambezogene Schichtkonfigurationen werden durch berechtigte Einsatzbegleitungen gepflegt.

Die zuständige Einsatzbegleitung führt Monatspläne kontrolliert von `draft` über `planned` nach `approved`. Genehmigte Pläne sind bis zu einer ausdrücklichen Rücknahme gegen Wünsche, Zuweisungen und Bemerkungsänderungen gesperrt. Optionale Urlaubs- und Kalenderprovider liefern ausschließlich datensparsame, schreibgeschützte Planungshinweise.

Ist AD Kalender aktiv, blockieren dortige Dienste über den versionierten
LocalBase-Konfliktvertrag überlappende Assistenzzuweisungen und erscheinen im
Plan als `Dienst/Büro`. Umgekehrt veröffentlicht AdPlaner belegte
Assistenzschichten als read-only `Assistenz`-Konflikte. Ohne die jeweils andere
App bleiben beide Produkte eigenständig nutzbar.

Genehmigte Pläne frieren die damaligen Schichtdefinitionen ein, speichern aber keine zusätzlichen historischen Personenstammdaten. Zuweisungen werden weiterhin nur für aktuell schichtfähige Mitglieder des jeweiligen Assistenzteams angezeigt.

Schichtfähige Teammitglieder können eigene Wünsche als Lieblingsschicht oder
„nur im Notfall“ kennzeichnen und eine für das Team sichtbare kurze Anmerkung
hinterlegen. Persönliche Mindest- und Höchstwerte pro Kalenderwoche und Monat
werden im Einstellungstab gepflegt. Die eigene Auslastung ist einblendbar;
die zuständige EB sieht die Teamübersicht und erkennt Unter- beziehungsweise
Überschreitungen zusätzlich zu ihrer visuellen Markierung immer auch als Text.

Auf Smartphone-Viewports wird derselbe Monatsplan als semantische Tagesliste
mit Schichtzeiten, Zuständigkeiten, Status, Hinweisen und Anmerkungen
dargestellt. Assistenz und EB verwenden dort dieselben rollenabhängigen
Aktionen wie in der Desktopmatrix; genehmigte und unbekannte Planstatus
bleiben auch mobil ohne fachliche Mutationsangebote.

Native Nextcloud-Administration erteilt keinen automatischen Zugriff auf den
fachlichen Demo-Datenpfad. Dieser wird je Admin app-lokal für höchstens 24
Stunden freigegeben; Beginn, geplantes Ende und Widerruf bleiben auditierbar.
PermissionProvider und PersonalDataProvider bilden die kombinierte
Adminbedingung beziehungsweise den eigenen Freigabebezug ab, ohne andere
Admin-Kennungen in der Selbstauskunft offenzulegen.

## Roadmap

Geplante Erweiterungen und offene Produktentscheidungen stehen in der [Roadmap](ROADMAP.md).

Für die fachliche, visuelle und sicherheitsbezogene Staging-Prüfung steht ein
ausfüllbares [manuelles Abnahmeformular](docs/manual-acceptance.md) bereit.
Zugangsdaten und personenbezogene Echtdaten werden darin nicht dokumentiert.

Installations-, Betriebs- und Abnahmeunterlagen stehen im öffentlichen [AD-Suite-Projekt](https://github.com/Filzmann/ad-suite).

## Dokumentation

- [Architektur](docs/architecture.md)
- [Manuelle Abnahme](docs/manual-acceptance.md)
- [Roadmap](ROADMAP.md)
- [Changelog](CHANGELOG.md)
- [Arbeitsregeln](AGENTS.md)
