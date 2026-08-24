# Manuelles Abnahmeformular – AdPlaner

Dieses Formular dokumentiert die fachliche und visuelle Abnahme der
Assistenzplanung auf einem realitätsnahen Staging-System. Pro Prüffall wird
genau ein Ergebnis markiert und unter „Warum/Beleg/Abweichung“ knapp
festgehalten, was beobachtet wurde.

Keine personenbezogenen Echtdaten, Gesundheits- oder Urlaubsdetails,
Zugangsdaten oder internen Kennungen eintragen. Ausschließlich neutrale
Testkonten, synthetische Teams, Schichten und Bemerkungen verwenden.

## Kopfdaten

| Feld | Eintrag |
|---|---|
| Datum und Uhrzeit | 02.08.2026, ca. 18:26–19:31 Uhr |
| Prüfer*in | Simon |
| Umgebung und URL | DEV/Staging – `https://nextcloud-dev.ddev.site/index.php/apps/adplaner/` |
| AdPlaner-Version | 0.3.0-rc.2 |
| Nextcloud-Version | Nextcloud Hub 26 Spring – 34.0.2 |
| Browser und Version | Google Chrome 150.0.7871.186, offizieller 64-Bit-Build unter Ubuntu |
| Fenstergröße / Zoom | ungefähr zwei Drittel von 1920 px, Zoom 100 % |
| Neutrale Testkonten, Teams und Rollen | `adc-test-eb-sued` (EB-Testkonto), `admin` (in AdPlaner normales schichtfähiges Teammitglied ohne EB-Rechte), `west` (Nur-EB-Testkontext); Teams `KaKü` und `HaHü`; weitere neutrale Testkonten im Verlauf verwendet, aber nicht einzeln dokumentiert |
| Aktive optionale Apps | Grundsätzlich alle, einschließlich OrgSuite, AD Urlaub und AD Kalender; OrgSuite für A1 vorübergehend deaktiviert, AD Urlaub für D1 und AD Kalender für D2 vorübergehend deaktiviert |

Ergebniskennzeichnung: `[x] erfolgreich` / `[x] nicht erfolgreich` /
`[x] nicht geprüft`. Bei „nicht erfolgreich“ oder „nicht geprüft“ ist eine
Begründung verpflichtend.

## A. Einstieg, Navigation und Monatsplan

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| A1 | Standalone-Einstieg | AdPlaner ohne aktive OrgSuite öffnen. | Ein eigener Nextcloud-Einstieg ist vorhanden und der Monatsplan wird ohne andere Fachapps geladen. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Eigener Nextcloud-Einstieg vorhanden; Monatsplan vollständig geladen und ohne OrgSuite bedienbar. Keine Fehler oder fehlenden Komponenten festgestellt. |
| A2 | Suite-Einstieg | AdPlaner mit aktiver OrgSuite über den AD-Einstieg öffnen und zwischen aktivierten AD-Apps wechseln. | Es gibt keinen doppelten Haupteinstieg; AdPlaner ist im gemeinsamen Menü korrekt markiert. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Gemeinsamer AD-Einstieg vorhanden; kein doppelter Haupteinstieg; AdPlaner im gemeinsamen Menü sichtbar und korrekt markiert. Wechsel zwischen AD-Apps und Laden des Monatsplans funktionieren. |
| A3 | Team und Monat | Zwischen mindestens zwei synthetischen Teams sowie vorherigem und nächstem Monat wechseln. | Auswahl, Überschrift, Tage, Schichten und Zuweisungen gehören stets zum gewählten Team und Monat. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Wechsel zwischen zwei Teams sowie vorherigem und nächstem Monat funktionierte korrekt. Überschrift, Tage, Schichten und Zuweisungen gehörten jeweils zum gewählten Team und Monat. Keine vermischten oder veralteten Daten sichtbar. |
| A4 | Monatsgrenzen | Februar sowie einen Monats-/Jahreswechsel öffnen. | Kalendertage und gespeicherte Planwerte werden ohne fehlende oder doppelte Tage angezeigt. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Februar einschließlich Schaltjahr sowie Monats-/Jahreswechsel geprüft. Keine fehlenden oder doppelten Tage; gespeicherte Planwerte korrekt. Verbesserungsbedarf: Es gibt keine Schalter für „vorheriger Monat“ und „nächster Monat“; der Wechsel ist nur über das Datumsfeld möglich. |
| A5 | Tastatur und Fokus | Team-, Monats-, Tab- und Plansteuerung nur mit Tastatur bedienen. | Alle Funktionen sind erreichbar, der Fokus ist sichtbar und die Tabs melden Auswahl und Zielbereich korrekt. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Teamauswahl, Monatssteuerung, Tabs und Plansteuerung waren per Tastatur bedienbar. Der Fokus war sichtbar, die aktive Tab-Auswahl erkennbar und es bestand keine Tastaturfalle. |
| A6 | Responsivität und Scrollen | Viele Schichten und Personen bei kleinem Fenster anzeigen und horizontal sowie vertikal scrollen. | App-Inhalte bleiben erreichbar; der App-Root scrollt vertikal und breite Planungselemente sprengen nicht die Nextcloud-Seite. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Kleines Fenster geprüft; vertikales Scrollen funktioniert, alle Inhalte bleiben erreichbar und die Nextcloud-Seite wird nicht horizontal gesprengt. Keine doppelten oder überlagernden Scrollleisten. Verbesserungsbedarf: Die horizontale Scrollleiste ist erst am Ende des gesamten Inhalts erreichbar und sollte wie im AD Kalender am unteren Rand des sichtbaren Planbereichs verfügbar bleiben. |

## B. Schichtkonfiguration und Wunschplanung

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| B1 | Variable Schichtliste | Als zuständige EB eine Schicht ergänzen, umbenennen, zeitlich ändern, deaktivieren und wieder aktivieren. | Die strukturierte Teamkonfiguration bleibt nach Neuladen erhalten und steuert den Monatsplan. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Mit `adc-test-eb-sued` im Team `KaKü` wurde eine Schicht ergänzt, nach Neuladen erhalten, umbenannt, zeitlich geändert, deaktiviert und wieder aktiviert. Der Monatsplan reagierte korrekt. Offene UI-Punkte: Der Speicherbutton für Kommentare liegt rechts außerhalb des sichtbaren Bereichs. Die Mitarbeiterauswahl soll zunächst verborgen bleiben und erst nach Betätigung des `+`-Schalters als kompakte Buttonliste in einem Overlay erscheinen. |
| B2 | Lücken und Überlappungen | Synthetische Schichten mit einer Lücke und einer zeitlichen Überlappung speichern. | Beide fachlich zulässigen Konfigurationen werden nicht vorschnell abgewiesen und erscheinen nachvollziehbar. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Schichtkonfigurationen mit zeitlicher Lücke und mit Überlappung wurden gespeichert und blieben nach Neuladen korrekt erhalten. Zeiten wurden nicht automatisch verändert. Testkonto und Testteam nicht dokumentiert. |
| B3 | Schicht über Mitternacht | Eine Nachtschicht mit Ende am Folgetag konfigurieren und im Monatsplan prüfen. | Die Schicht wird dem Ausgangstag eindeutig zugeordnet und mit ihrem Zeitraum verständlich dargestellt. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Die Nachtschicht wurde gespeichert, blieb nach dem Neuladen erhalten, war eindeutig dem Ausgangstag zugeordnet und als über Mitternacht laufend verständlich dargestellt. Es entstand kein doppelter Eintrag am Folgetag. Präzisierung: Eine gesonderte Darstellung am Folgetag oder Prüfung der Monatsgrenze ist fachlich nicht erforderlich; jede Schicht wird einmal dem Tag ihres Beginns zugeordnet, Monatspläne sind voneinander abgegrenzt. |
| B4 | Eigener Wunsch | Als schichtfähiges Teammitglied einen eigenen Wunsch hinzufügen, Bemerkung speichern und den Wunsch wieder entfernen. | Nur der eigene Eintrag wird verändert; Status und Bemerkung bleiben nach Neuladen erhalten. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Mit `admin` als normalem Assistenz-Teammitglied im Team `KaKü` konnte der eigene Wunsch hinzugefügt, nach Neuladen erhalten und wieder entfernt werden. Fremde Einträge blieben unverändert. Präzisiertes Soll: Normale Teammitglieder dürfen keine Bemerkungen speichern; Bemerkungen sind ausschließlich durch die zuständige EB bearbeitbar. |
| B5 | Fremdzuweisung durch EB | Als zuständige EB eine andere schichtfähige Person zuweisen und wieder entfernen. | Die Zuweisung ist möglich und betrifft ausschließlich das gewählte Team und die gewählte Schicht. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Fremdzuweisung durch eine zuständige EB insgesamt erfolgreich geprüft. Konkrete Testperson, Team, Schicht und Einzelschritte wurden nicht separat dokumentiert. Keine Abweichungen angegeben. |
| B6 | EB nicht schichtfähig | Versuchen, das reine EB-Testkonto selbst einer Schicht zuzuweisen. | Das EB-Konto wird nicht als schichtfähige Person angeboten beziehungsweise serverseitig abgewiesen. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Das reine EB-Konto wurde in der Personenauswahl nicht angeboten; eine Zuweisung über die UI war nicht möglich. Direkte serverseitige Negativprüfung nicht durchgeführt. |
| B7 | Planstatus | Als EB die angebotenen Statuswechsel einschließlich `planned` und `approved` durchführen; als normales Teammitglied wiederholen. | Nur die EB kann den Status wechseln; unberechtigte Versuche verändern den Plan nicht. | [ ] erfolgreich [x] nicht erfolgreich [ ] nicht geprüft | Es gibt derzeit keine Option für den Planstatus. Die zuständige EB muss den Monatsplan mindestens als `planned` und `approved` festschreiben können. Ein als `approved` festgeschriebener Plan darf anschließend nicht mehr verändert werden. Normale Teammitglieder dürfen den Status nicht ändern. |

## C. Team- und Rechteabgrenzung

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| C1 | Teammitgliedschaft | Mit einem neutralen Konto aus Team A Team A und Team B direkt aufrufen. | Nur der erlaubte Teamkontext ist nutzbar; ein direkter unberechtigter Request auf Team B wird serverseitig abgewiesen. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Der erlaubte Teamkontext war nutzbar; der Zugriff auf ein nicht erlaubtes Team wurde insgesamt erfolgreich als abgegrenzt bewertet. Testkonto, Teams und Einzelprüfungen wurden nicht dokumentiert. |
| C2 | EB-Schnittmenge | Ein Testkonto nur in der EB-Rollengruppe, ein Konto nur im Team und ein Konto in beiden Gruppen vergleichen. | EB-Rechte entstehen nur aus der vorgesehenen Team-und-Rolle-Schnittmenge. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Die vorgesehenen Rechte entstanden nur bei der Kombination aus Teamzugehörigkeit und EB-Rolle. Als Nur-EB-Testkontext wurde `west` angegeben. Weitere Konten und Einzelprüfungen wurden nicht separat dokumentiert. |
| C3 | Fremdänderung durch normales Mitglied | Als normales Teammitglied eine fremde Zuweisung über UI und direkten API-Aufruf ändern. | Beide Wege werden abgewiesen; der bestehende Eintrag bleibt unverändert. | [ ] erfolgreich [ ] nicht erfolgreich [x] nicht geprüft | Mit `admin` im Team `KaKü` war eine fremde Änderung über die UI nicht möglich und der bestehende Eintrag blieb unverändert. Die erforderliche serverseitige Prüfung über einen direkten API-Aufruf wurde nicht durchgeführt. |
| C4 | Bereichs- und Teamtrennung | Zwei Teams mit ähnlich benannten neutralen Konten und unterschiedlichen Konfigurationen prüfen. | Zuweisungen, Schichten, Bemerkungen und Rechte werden nicht zwischen Teams vermischt. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Die Teams `HaHü` und `KaKü` wurden verglichen. Schichten, Zuweisungen, Bemerkungen und Rechte blieben getrennt. Auch nach Team- und Monatswechsel trat keine Vermischung auf. |
| C5 | Demo-Pack-Schutz | Adminbereich öffnen, Installation ohne Bestätigung versuchen und anschließend nur in einer dafür vorgesehenen Testumgebung bestätigen. | Ohne ausdrückliche Bestätigung bleibt die Aktion gesperrt; es werden ausschließlich synthetische Konten und Teams verwendet. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Installation ohne Bestätigung wurde blockiert und erzeugte keine Daten. Nach ausdrücklicher Bestätigung war die Installation möglich. Es wurden ausschließlich synthetische Konten, Teams und Plandaten erzeugt; bestehende Daten blieben unverändert. Eine erneute Installation wurde sicher behandelt. |

## D. Optionale Integrationen und Fehlerzustände

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| D1 | Betrieb ohne AD Urlaub | AD Urlaub deaktiviert lassen und Monatsplan, Einstellungen und Zuweisungen prüfen. | Der Monatsplan bleibt vollständig nutzbar; fehlende Abwesenheitshinweise blockieren keine Aktion. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Bei deaktiviertem AD Urlaub wurden Monatsplan, Team- und Monatswechsel, Einstellungen, eigene Wünsche und Fremdzuweisungen vollständig genutzt. Die fehlende Integration erzeugte weder Fehler noch blockierte Aktionen. |
| D2 | Betrieb ohne AD Kalender | AD Kalender deaktiviert lassen und dieselben Kernabläufe wiederholen. | Die Assistenzplanung bleibt nutzbar; der fehlende optionale Provider wird nicht als Planfehler behandelt. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Bei deaktiviertem AD Kalender blieben Monatsplan, Team- und Monatswechsel, Schichten, Einstellungen, eigene Wünsche und Fremdzuweisungen vollständig nutzbar. Der fehlende Kalenderprovider erzeugte weder Fehler noch blockierte Aktionen. Zusätzliche Beobachtung: Vorhandener Urlaub hatte zu diesem Zeitpunkt noch keine sichtbare Auswirkung auf AdPlaner; dies wurde in D3 bewertet. |
| D3 | Optionale Hinweise | Mit aktivem, neutral vorbereitetem Urlaubs- oder Kalenderprovider dessen Hinweise im Monatsplan prüfen. | Hinweise werden read-only dargestellt und verändern weder Teamrechte noch die führenden AdPlaner-Daten. | [ ] erfolgreich [x] nicht erfolgreich [ ] nicht geprüft | AD Urlaub und AD Kalender waren aktiv. Ein vorhandener synthetischer Urlaub wurde im Monatsplan nicht als Hinweis dargestellt; die Read-only-Eigenschaft war daher nicht prüfbar. Ein Kalenderhinweis war nicht vorhanden. Teamrechte und führende AdPlaner-Daten wurden nicht verändert. |
| D4 | Verständliche Validierung | Leere Namen, unvollständige Zeiten und ungültige Eingaben in Einstellungen und Planung versuchen. | Fehler werden am richtigen Kontext verständlich angezeigt; gültige bestehende Daten bleiben erhalten. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Leere Namen, unvollständige Zeiten und weitere ungültige Eingaben wurden verständlich und am richtigen Kontext abgewiesen. Bestehende gültige Daten blieben erhalten; die Eingaben konnten unmittelbar korrigiert werden. Fehlermeldungen erscheinen vor einem etwaigen Neuladen, sodass kein kommentarloser Reload erfolgt. |
| D5 | Datensparsame Abnahme | Formular und Screenshots prüfen. | Es wurden nur synthetische Team-, Dienst- und Kontodaten dokumentiert. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Ausschließlich neutrale Testkonten sowie synthetische oder freigegebene Teams und Schichten dokumentiert. Keine personenbezogenen Bemerkungen, Gesundheits- oder Urlaubsdetails, Zugangsdaten, Tokens oder unzulässigen Echtdaten in Screenshots. |

## Abschlussentscheidung

| Feld | Eintrag |
|---|---|
| Anzahl erfolgreich | 20 |
| Anzahl nicht erfolgreich | 2 |
| Anzahl nicht geprüft | 1 |
| Kritische Abweichungen / Ticketreferenzen | B7: Planstatus `planned`/`approved` und Änderungssperre fehlen.<br>D3: Optionale Urlaubs- und Kalenderhinweise werden nicht wie vorgesehen read-only dargestellt.<br>C3: Direkte serverseitige Negativprüfung einer Fremdänderung steht aus.<br>Weitere UI-Punkte: Monatsnavigation A4, sichtbare horizontale Scrollleiste A6, Kommentar-Speicherbutton und Mitarbeiterauswahl B1. |
| Erneute Prüfung erforderlich bis | Vor Freigabe einer abnahmefähigen Version; kein konkretes Datum festgelegt |
| Gesamtentscheidung | [ ] abgenommen [ ] mit Auflagen abgenommen [x] nicht abgenommen |
| Begründung der Gesamtentscheidung | Die zentrale Planfreigabe einschließlich Statusverwaltung und Änderungssperre fehlt. Außerdem werden optionale Urlaubs- beziehungsweise Kalenderhinweise nicht wie vorgesehen dargestellt. C3 ist serverseitig noch nicht vollständig geprüft. |
| Name / Datum | Simon / 02.08.2026 |

## Zu bearbeitende Punkte

### Hohe Priorität

1. **B7 – Planstatus ergänzen**
   - Status `planned` und `approved` implementieren.
   - Nur die zuständige EB darf den Status ändern.
   - Ein `approved`-Plan muss gegen Änderungen an Schichten, Zuweisungen, Wünschen und Bemerkungen gesperrt sein.
   - Status muss nach Neuladen erhalten bleiben.
   - Ein ausdrücklicher, berechtigter Entsperr- oder Rücksetzweg muss festgelegt werden.

2. **D3 – Optionale Hinweise darstellen**
   - Vorhandenen Urlaub einer schichtfähigen Person am betroffenen Tag im Monatsplan sichtbar machen.
   - Hinweise ausschließlich read-only darstellen.
   - Optional vorhandene Kalenderhinweise ebenfalls read-only anzeigen.
   - Hinweise dürfen weder Teamrechte noch führende AdPlaner-Daten verändern.
   - Die fachliche Wirkung auf Wünsche und Zuweisungen ist noch ausdrücklich festzulegen.

### Mittlere Priorität

3. **A6 – Horizontale Scrollleiste erreichbar halten**
   - Horizontale Scrollleiste am unteren Rand des sichtbaren Plan-Viewports bereitstellen.
   - Umsetzung am Verhalten des AD Kalenders orientieren.

4. **B1 – Kommentar-Speicherbutton im sichtbaren Bereich halten**
   - Speicheraktion ohne horizontales Scrollen erreichbar machen.

5. **B1 – Mitarbeiterauswahl verdichten**
   - Mitarbeiterauswahl zunächst verbergen.
   - Erst nach Betätigung des `+`-Schalters als kompakte Buttonliste in einem Overlay öffnen.

### Niedrige Priorität / Nachprüfung

6. **A4 – Monatsnavigation ergänzen**
   - Schalter für vorherigen und nächsten Monat ergänzen.

7. **B4 – Prüfkriterium und Dokumentation korrigieren**
   - Normale Teammitglieder dürfen eigene Wünsche hinzufügen und entfernen.
   - Bemerkungen bleiben ausschließlich durch die zuständige EB bearbeitbar.

8. **C3 – Serverseitige Rechteprüfung nachholen**
   - Fremdänderung durch ein normales Teammitglied über einen direkten API-Aufruf versuchen.
   - Verifizieren, dass der Server die Änderung abweist und der bestehende Eintrag unverändert bleibt.

## Automatisierter Nachweis nach der historischen Abnahme

Die vorstehenden Ergebnisse und die Gesamtentscheidung bleiben als historischer
manueller Stand vom 02.08.2026 unverändert. Für `0.4.0-rc.1` sind B7 und D3
technisch umgesetzt und durch Service-, API-, JavaScript- und Layouttests
abgesichert: Die zuständige EB kann ausschließlich die erlaubten Übergänge
`draft` → `planned` → `approved` sowie die ausdrücklichen Rückwege ausführen,
ein genehmigter Monatsplan ist serverseitig und in der Oberfläche gesperrt,
und optionale Urlaubs-/Kalenderhinweise bleiben read-only. Der reale
DDEV-Integrationslauf belegt Statuspersistenz und unzulässige Übergänge gegen
die Nextcloud-34-Datenbank. Die selbstbereinigende DDEV-Rechtematrix deckt den
direkten serverseitigen Negativfall aus C3 ab.

Auch die technischen Korrekturen zu A4 und A6 sind automatisiert belegt:
direkte Vor-/Zurücknavigation und ein begrenzter, horizontal wie vertikal
scrollbarer Plan-Viewport mit sichtbarer rechter Aktionsspalte. Die manuelle
visuelle und fachliche Wiederholungsabnahme bleibt vor einer Produktfreigabe
erforderlich. Die fachliche Wirkung der optionalen Hinweise ist inzwischen
verbindlich festgelegt: Sie sind ausschließlich read-only und blockieren oder
verändern weder Wünsche noch Zuweisungen.

Für `0.4.0-rc.2` belegen zusätzliche Repository-, Service-, Routen- und
JavaScript-Tests die Serialisierung konkurrierender Planänderungen, strikte
Eingabegrenzen, idempotente Erstschreibvorgänge, das datensparsame Entfernen
geleerter Tagesbemerkungen, ausschließlich aktuell schichtfähige Personen in
öffentlichen Teamantworten sowie UID-freie read-only Planungshinweise. Der
technische Monats-Lock verändert keine personenbezogenen fachlichen
Änderungsmetadaten. Unbekannte Planstatus bleiben auch in der Oberfläche
gesperrt; Tabellenüberschriften und Bemerkungsfelder sind programmatisch
beschriftet. Diese automatisierten Nachweise ersetzen die ausstehende visuelle
und fachliche Wiederholungsabnahme nicht.

Ein zusätzlicher selbstbereinigender Nextcloud-34-DDEV-Smoke bestätigt diese
Datenschutzverträge über den echten Gruppen-, HTTP-/CSRF- und Datenbankpfad:
Deaktivierte Konten und EB-Konten werden nicht als schichtfähig ausgeliefert,
das Leeren einer Tagesbemerkung entfernt deren Datenbankzeile, und technische
Monats-Locks überschreiben keine fachlichen Änderungsmetadaten. Die getrennte
Cleanup-Nachprüfung fand anschließend weder synthetische AdPlaner-Daten noch
die temporären Konten oder die temporäre Gruppe.

Eine selbstbereinigende Headless-Chrome-Wiederholungsabnahme belegt zusätzlich
die gerenderte und interaktive Oberfläche in Nextcloud 34 mit einem
synthetischen EB- und einem normalen Teamkonto. Geprüft wurden direkte
Monatsnavigation, Tastaturwechsel der Tabs, Fremdzuweisung durch EB, eigener
Wunsch, persistierte Tagesbemerkung und Schichtkonfiguration, die Übergänge
`draft` → `planned` → `approved` einschließlich Sperre und ausdrücklichem
Entsperren, schreibgeschützte Einstellungen für normale Mitglieder sowie das
reale vertikale und horizontale Scrollverhalten bei schmalem Viewport. Ein
temporärer Sichtnachweis wurde nur im Testverzeichnis unter `/tmp` erzeugt und
mit dem Browserprofil gelöscht. Eine unabhängige Nachprüfung fand danach keine
synthetischen Konten oder über deren Bearbeiterkennung auffindbaren Plan- und
Bemerkungsdaten. Nicht Bestandteil dieses Laufs war das Umschalten von
OrgSuite oder optionalen Provider-Apps.

Am 22.08.2026 wurden Rechtematrix, Datenschutz-Runtime und die vollständige
Headless-Chrome-Abnahme erneut gegen Nextcloud 34 ausgeführt. Alle drei Läufe
waren grün und bestätigten zugleich den lokalen Testkontenvertrag
Benutzername = Passwort. Die anschließenden Cleanup-Prüfungen fanden keine
synthetischen Konten, Gruppen oder Plandaten. Die historische manuelle
Gesamtentscheidung vom 02.08.2026 bleibt als damaliger Befund unverändert.
