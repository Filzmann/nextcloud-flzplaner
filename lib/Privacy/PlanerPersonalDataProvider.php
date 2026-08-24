<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Privacy;

use DateTimeImmutable;
use DateTimeInterface;
use OCA\AdPlaner\AppInfo\Application;
use OCA\AdPlaner\Repository\ShiftPlanRepository;
use OCA\LocalBase\Privacy\PersonalDataItem;
use OCA\LocalBase\Privacy\PersonalDataProcessingInfo;
use OCA\LocalBase\Privacy\PersonalDataProvider;
use OCA\LocalBase\Privacy\PersonalDataReport;
use OCA\LocalBase\Privacy\PersonalDataRequest;
use OCA\LocalBase\Privacy\PersonalDataSubject;

final class PlanerPersonalDataProvider implements PersonalDataProvider {
    private const RETENTION = 'Keine feste Löschfrist festgelegt; gespeichert bis zur fachlich oder gesetzlich veranlassten Löschung.';

    public function __construct(private ShiftPlanRepository $repository) {}

    public function appId(): string { return Application::APP_ID; }
    public function supportedSubjectTypes(): array { return [PersonalDataSubject::NEXTCLOUD_USER]; }

    public function collect(PersonalDataRequest $request): PersonalDataReport {
        $limit = $request->limit();
        $data = $this->repository->personalDataForUid($request->subject()->id(), $limit);
        $items = [];
        foreach ($data['candidates'] ?? [] as $row) {
            $subjectIsCandidate = (string)$row['assistant_uid'] === $request->subject()->id();
            $items[] = $subjectIsCandidate ? $this->candidateItem($row, $request->subject()->id()) : $this->activityItem($row);
        }
        foreach ($data['dayNotes'] ?? [] as $row) $items[] = $this->dayNoteItem($row);
        foreach ($data['monthPlans'] ?? [] as $row) $items[] = $this->monthPlanItem($row);
        $complete = count($items) < $limit;
        $items = array_slice($items, 0, $limit);

        return new PersonalDataReport(
            $items,
            new PersonalDataProcessingInfo(
                purposes: ['Erfassung von Schichtwünschen und Dienstzuweisungen', 'Bearbeitung und Freigabe monatlicher Dienstpläne', 'Dokumentation planungsbezogener Änderungen'],
                categories: ['Nextcloud-Kennung in Schichtwünschen und Zuweisungen', 'Bearbeitungsreferenzen an Tagesnotizen und Monatsplänen', 'Zeit-, Team- und Schichtangaben'],
                recipients: ['Mitglieder des jeweiligen Assistenzteams im zulässigen Planungsscope', 'Berechtigte Einsatzbegleitungen', 'Nextcloud-Administrator*innen mit Verwaltungsrechten'],
                source: 'Eigene Eingaben sowie Eingaben berechtigter Einsatzbegleitungen im Dienstplan',
                retentionCriteria: self::RETENTION,
                thirdCountryTransfers: 'Durch AD Planer sind keine Drittlandübermittlungen vorgesehen.',
                automatedDecisionMaking: 'Planungshinweise und Konfliktprüfungen unterstützen die Bearbeitung; sie treffen keine Entscheidung mit rechtlicher oder vergleichbar erheblicher Wirkung.',
            ),
            complete: $complete,
            limitations: $complete ? [] : ['Ausgabelimit erreicht; weitere Planungsdaten können vorhanden sein.'],
            appName: 'AD Planer',
        );
    }

    private function candidateItem(array $row, string $subjectUid): PersonalDataItem {
        $selfCreated = (string)$row['created_by_uid'] === $subjectUid;
        return new PersonalDataItem(
            'shift_assignment',
            self::shortDate((string)$row['work_date']) . ' – ' . (string)$row['label'],
            'shift-candidate:' . (string)$row['id'],
            [
                'Team' => (string)$row['team_code'],
                'Datum' => self::shortDate((string)$row['work_date']),
                'Schicht' => (string)$row['label'],
                'Beginn' => (string)$row['starts_at'] . ' Uhr',
                'Ende' => (string)$row['ends_at'] . ' Uhr',
                'Eintragung' => $selfCreated ? 'Von dir selbst eingetragen' : 'Von einer berechtigten Person eingetragen',
                'Eingetragen am' => self::shortDateTime($row['created_at'] ?? ''),
            ],
            'Erfassung deines Schichtwunsches oder deiner Dienstzuweisung',
            self::RETENTION,
            'Folgende Schichtwünsche oder Schichtzuweisungen sind mit deinen Daten gespeichert:',
            $selfCreated ? null : 'Die eintragende Person wird zum Schutz ihrer Datenschutzrechte nicht genannt.',
            'Schichtwunsch oder Schichtzuweisung',
        );
    }

    private function activityItem(array $row): PersonalDataItem {
        return new PersonalDataItem(
            'planning_activity',
            self::shortDate((string)$row['work_date']) . ' – ' . (string)$row['label'],
            'shift-candidate-activity:' . (string)$row['id'],
            [
                'Team' => (string)$row['team_code'],
                'Datum' => self::shortDate((string)$row['work_date']),
                'Schicht' => (string)$row['label'],
                'Beginn' => (string)$row['starts_at'] . ' Uhr',
                'Ende' => (string)$row['ends_at'] . ' Uhr',
                'Eingetragen am' => self::shortDateTime($row['created_at'] ?? ''),
            ],
            'Nachvollziehbarkeit einer von dir vorgenommenen Planungsänderung',
            self::RETENTION,
            'Folgende Planungsaktivitäten sind mit deiner Kennung gespeichert:',
            'Die von der Aktivität betroffene andere Person wird nicht genannt.',
            'Planungsaktivität',
        );
    }

    private function dayNoteItem(array $row): PersonalDataItem {
        return new PersonalDataItem(
            'day_note_activity',
            self::shortDate((string)$row['work_date']),
            'day-note:' . (string)$row['id'],
            [
                'Team' => (string)$row['team_code'],
                'Datum' => self::shortDate((string)$row['work_date']),
                'Bearbeitet am' => self::shortDateTime($row['updated_at'] ?? ''),
                'Notizinhalt' => 'Inhalt wird wegen möglicher Angaben zu anderen Personen nicht automatisch ausgegeben.',
            ],
            'Planungsbezogene Tagesinformation und Nachvollziehbarkeit der letzten Bearbeitung',
            self::RETENTION,
            'Folgende Tagesnotizen tragen deine Kennung als letzte Bearbeitung:',
            'Freie Tagesnotizen können Angaben über andere Personen enthalten.',
            'Bearbeitete Tagesnotiz',
        );
    }

    private function monthPlanItem(array $row): PersonalDataItem {
        $status = ['draft'=>'Entwurf','planned'=>'Geplant','approved'=>'Genehmigt'][(string)$row['status']] ?? (string)$row['status'];
        return new PersonalDataItem(
            'month_plan_activity',
            self::shortMonth((string)$row['plan_month']) . ' – ' . $status,
            'month-plan:' . (string)$row['id'],
            [
                'Team' => (string)$row['team_code'],
                'Planungsmonat' => self::shortMonth((string)$row['plan_month']),
                'Status' => $status,
                'Bearbeitet am' => self::shortDateTime($row['updated_at'] ?? ''),
            ],
            'Nachvollziehbarkeit der von dir zuletzt bearbeiteten Monatsplanung',
            self::RETENTION,
            'Folgende Monatsplanstände tragen deine Kennung als letzte Bearbeitung:',
            dataType: 'Bearbeiteter Monatsplan',
        );
    }

    private static function shortDate(string $value): string {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));
        return $date === false ? $value : $date->format('d.m.y');
    }

    private static function shortMonth(string $value): string {
        $date = DateTimeImmutable::createFromFormat('!Y-m', $value);
        return $date === false ? $value : $date->format('m.y');
    }

    private static function shortDateTime(mixed $value): string {
        if ($value instanceof DateTimeInterface) return $value->format('d.m.y, H:i') . ' Uhr';
        try { return (new DateTimeImmutable((string)$value))->format('d.m.y, H:i') . ' Uhr'; }
        catch (\Throwable) { return (string)$value; }
    }
}
