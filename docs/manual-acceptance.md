# Manuelles Abnahmeformular – FlzPlaner

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
| Datum und Uhrzeit |  |
| Prüfer*in |  |
| Umgebung und URL |  |
| FlzPlaner-Version |  |
| Nextcloud-Version |  |
| Browser und Version |  |
| Fenstergröße / Zoom |  |
| Neutrale Testkonten, Teams und Rollen |  |
| Aktive optionale Apps |  |

Ergebniskennzeichnung: `[ ] erfolgreich` / `[ ] nicht erfolgreich` /
`[ ] nicht geprüft`. Bei „nicht erfolgreich“ oder „nicht geprüft“ ist eine
Begründung verpflichtend.

## A. Einstieg, Navigation und Monatsplan

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| A1 | Standalone-Einstieg | FlzPlaner ohne aktive OrgSuite öffnen. | Ein eigener Nextcloud-Einstieg ist vorhanden und der Monatsplan wird ohne andere Fachapps geladen. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A2 | Suite-Einstieg | FlzPlaner mit aktiver OrgSuite über den FLZ-Einstieg öffnen und zwischen aktivierten FLZ-Apps wechseln. | Es gibt keinen doppelten Haupteinstieg; FlzPlaner ist im gemeinsamen Menü korrekt markiert. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A3 | Team und Monat | Zwischen mindestens zwei synthetischen Teams sowie vorherigem und nächstem Monat wechseln. | Auswahl, Überschrift, Tage, Schichten und Zuweisungen gehören stets zum gewählten Team und Monat. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A4 | Monatsgrenzen | Februar sowie einen Monats-/Jahreswechsel öffnen. | Kalendertage und gespeicherte Planwerte werden ohne fehlende oder doppelte Tage angezeigt. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A5 | Tastatur und Fokus | Team-, Monats-, Tab- und Plansteuerung nur mit Tastatur bedienen. | Alle Funktionen sind erreichbar, der Fokus ist sichtbar und die Tabs melden Auswahl und Zielbereich korrekt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A6 | Responsivität und Scrollen | Viele Schichten und Personen bei kleinem Fenster anzeigen und horizontal sowie vertikal scrollen. | App-Inhalte bleiben erreichbar; der App-Root scrollt vertikal und breite Planungselemente sprengen nicht die Nextcloud-Seite. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## B. Schichtkonfiguration und Wunschplanung

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| B1 | Variable Schichtliste | Als zuständige EB eine Schicht ergänzen, umbenennen, zeitlich ändern, deaktivieren und wieder aktivieren. | Die strukturierte Teamkonfiguration bleibt nach Neuladen erhalten und steuert den Monatsplan. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B2 | Lücken und Überlappungen | Synthetische Schichten mit einer Lücke und einer zeitlichen Überlappung speichern. | Beide fachlich zulässigen Konfigurationen werden nicht vorschnell abgewiesen und erscheinen nachvollziehbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B3 | Schicht über Mitternacht | Eine Nachtschicht mit Ende am Folgetag konfigurieren und im Monatsplan prüfen. | Die Schicht wird dem Ausgangstag eindeutig zugeordnet und mit ihrem Zeitraum verständlich dargestellt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B4 | Eigener Wunsch | Als schichtfähiges Teammitglied einen eigenen Wunsch hinzufügen, Bemerkung speichern und den Wunsch wieder entfernen. | Nur der eigene Eintrag wird verändert; Status und Bemerkung bleiben nach Neuladen erhalten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B5 | Fremdzuweisung durch EB | Als zuständige EB eine andere schichtfähige Person zuweisen und wieder entfernen. | Die Zuweisung ist möglich und betrifft ausschließlich das gewählte Team und die gewählte Schicht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B6 | EB nicht schichtfähig | Versuchen, das reine EB-Testkonto selbst einer Schicht zuzuweisen. | Das EB-Konto wird nicht als schichtfähige Person angeboten beziehungsweise serverseitig abgewiesen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B7 | Planstatus | Als EB die angebotenen Statuswechsel einschließlich `planned` und `approved` durchführen; als normales Teammitglied wiederholen. | Nur die EB kann den Status wechseln; unberechtigte Versuche verändern den Plan nicht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## C. Team- und Rechteabgrenzung

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| C1 | Teammitgliedschaft | Mit einem neutralen Konto aus Team A Team A und Team B direkt aufrufen. | Nur der erlaubte Teamkontext ist nutzbar; ein direkter unberechtigter Request auf Team B wird serverseitig abgewiesen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C2 | EB-Schnittmenge | Ein Testkonto nur in der EB-Rollengruppe, ein Konto nur im Team und ein Konto in beiden Gruppen vergleichen. | EB-Rechte entstehen nur aus der vorgesehenen Team-und-Rolle-Schnittmenge. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C3 | Fremdänderung durch normales Mitglied | Als normales Teammitglied eine fremde Zuweisung über UI und direkten API-Aufruf ändern. | Beide Wege werden abgewiesen; der bestehende Eintrag bleibt unverändert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C4 | Bereichs- und Teamtrennung | Zwei Teams mit ähnlich benannten neutralen Konten und unterschiedlichen Konfigurationen prüfen. | Zuweisungen, Schichten, Bemerkungen und Rechte werden nicht zwischen Teams vermischt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C5 | Demo-Pack-Schutz | Adminbereich öffnen, Installation ohne Bestätigung versuchen und anschließend nur in einer dafür vorgesehenen Testumgebung bestätigen. | Ohne ausdrückliche Bestätigung bleibt die Aktion gesperrt; es werden ausschließlich synthetische Konten und Teams verwendet. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## D. Optionale Integrationen und Fehlerzustände

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| D1 | Betrieb ohne Filzmann Urlaubsplanung | Filzmann Urlaubsplanung deaktiviert lassen und Monatsplan, Einstellungen und Zuweisungen prüfen. | Der Monatsplan bleibt vollständig nutzbar; fehlende Abwesenheitshinweise blockieren keine Aktion. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| D2 | Betrieb ohne Filzmann Kalender | Filzmann Kalender deaktiviert lassen und dieselben Kernabläufe wiederholen. | Die Assistenzplanung bleibt nutzbar; der fehlende optionale Provider wird nicht als Planfehler behandelt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| D3 | Optionale Hinweise | Mit aktivem, neutral vorbereitetem Urlaubs- oder Kalenderprovider dessen Hinweise im Monatsplan prüfen. | Hinweise werden read-only dargestellt und verändern weder Teamrechte noch die führenden FlzPlaner-Daten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| D4 | Verständliche Validierung | Leere Namen, unvollständige Zeiten und ungültige Eingaben in Einstellungen und Planung versuchen. | Fehler werden am richtigen Kontext verständlich angezeigt; gültige bestehende Daten bleiben erhalten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| D5 | Datensparsame Abnahme | Formular und Screenshots prüfen. | Es wurden nur synthetische Team-, Dienst- und Kontodaten dokumentiert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## Abschlussentscheidung

| Feld | Eintrag |
|---|---|
| Anzahl erfolgreich | 4 |
| Anzahl nicht erfolgreich | 0 |
| Anzahl nicht geprüft | 19 |
| Kritische Abweichungen / Ticketreferenzen | Siehe `FLZP-STAGING-FOLLOWUP` in `ROADMAP.md`.<br>Beobachtet wurden fehlende Planstatus/Änderungssperre, fehlende read-only Integrationshinweise, der ausstehende direkte Fremdänderungs-Deny sowie Abweichungen bei Monatsnavigation, Scrollleiste, Kommentar-Speicheraktion und Mitarbeiterauswahl. Zusätzlich erschien unterhalb des Monatsplans eine weitere Schichtdarstellung. |
| Erneute Prüfung erforderlich bis | Vor Freigabe einer abnahmefähigen Version; kein konkretes Datum festgelegt |
| Gesamtentscheidung | [ ] abgenommen [ ] mit Auflagen abgenommen [ ] nicht abgenommen |
| Begründung der Gesamtentscheidung | Die zentrale Planfreigabe einschließlich Statusverwaltung und Änderungssperre fehlt. Außerdem werden optionale Urlaubs- beziehungsweise Kalenderhinweise nicht wie vorgesehen dargestellt. C3 ist serverseitig noch nicht vollständig geprüft. |
| Name / Datum | Simon / 02.08.2026 |



## Temporärer Admin-Vollzugriff

In einer freigegebenen DDEV- oder Staging-Umgebung mit synthetischen Konten prüfen:

- `Datenschutzbeauftragte` ohne nativen Adminstatus können Historie lesen und
  ein bestätigtes Administrationskonto freigeben sowie widerrufen.
- Native Administration ohne Datenschutzrolle erhält weder Steuerung noch
  Direktlink und direkte Schreibrequests bleiben mutationsfrei mit HTTP 403.
- Ein gewöhnliches Konto sieht weder Eintrittshinweis noch Steuerung und
  erhält keine Historie.
- Ungültige Zielkennung, mehr als 24 Stunden und fehlendes Requesttoken
  ändern keine Freigabehistorie.
- Ablauf, Widerruf und Verlust des nativen Adminstatus entziehen den
  fachlichen Vollzugriff; der rollenabhängige Einstieg bleibt per Tastatur
  erreichbar und legt gewöhnlichen Konten keinen Schutzstatus offen.
