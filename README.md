# AdPlaner

Monatliche Wunschdienstplanung für Assistenzteams. Urlaubsplanung liegt ausschließlich in der separaten App `adurlaub`.

## Staging-Kompatibilität

- Nextcloud 34
- PHP 8.3 oder neuer innerhalb des von Nextcloud 34 unterstützten Bereichs
- Laufzeitbasis: `localbase`; `orgsuite` ist ab zwei AD-Fachprodukten optional aktiv
- App-ID und Installationsordner: `adplaner`

## Installation

Für Staging und Auslieferung das Produktbundle `ad-product-adplaner-<release>.tar.gz` und dessen enthaltenes `install.sh` verwenden. Es prüft und installiert LocalBase automatisch; ab dem zweiten AD-Fachprodukt aktiviert es OrgSuite.

AdPlaner funktioniert einzeln; optionale Abwesenheits- oder Kalenderhinweise entfallen ohne die jeweilige Fachapp, ohne den Monatsplan zu blockieren.

Assistenzteams werden aus den zentral konfigurierten Nextcloud-Gruppen abgeleitet. Teambezogene Schichtkonfigurationen werden durch berechtigte Einsatzbegleitungen gepflegt.

Die zuständige Einsatzbegleitung führt Monatspläne kontrolliert von `draft` über `planned` nach `approved`. Genehmigte Pläne sind bis zu einer ausdrücklichen Rücknahme gegen Wünsche, Zuweisungen und Bemerkungsänderungen gesperrt. Optionale Urlaubs- und Kalenderprovider liefern ausschließlich datensparsame, schreibgeschützte Planungshinweise.

Genehmigte Pläne frieren die damaligen Schichtdefinitionen ein, speichern aber keine zusätzlichen historischen Personenstammdaten. Zuweisungen werden weiterhin nur für aktuell schichtfähige Mitglieder des jeweiligen Assistenzteams angezeigt.

## Roadmap

Geplante Erweiterungen und offene Produktentscheidungen stehen in der [Roadmap](ROADMAP.md).

Für die fachliche, visuelle und sicherheitsbezogene Staging-Prüfung steht ein
ausfüllbares [manuelles Abnahmeformular](docs/manual-acceptance.md) bereit.
Zugangsdaten und personenbezogene Echtdaten werden darin nicht dokumentiert.

Installations-, Betriebs- und Abnahmeunterlagen stehen im öffentlichen [AD-Suite-Projekt](https://github.com/Filzmann/ad-suite).
