<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Privacy;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use OCA\AdPlaner\AppInfo\Application;
use OCA\AdPlaner\Repository\ShiftPlanRepository;
use OCA\AdPlaner\Repository\TemporaryAdminAccessRepositoryInterface;
use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataEntry;
use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataPage;
use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataProvider;
use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataRequest;
use OCA\FilzmannDataProtection\PublicApi\V1\ProviderDescriptor;

final class PlanerPersonalDataProvider implements PersonalDataProvider {
    private const RETENTION = 'Keine feste Löschfrist festgelegt; gespeichert bis zur fachlich oder gesetzlich veranlassten Löschung.';

    public function __construct(private ShiftPlanRepository $repository, private ?TemporaryAdminAccessRepositoryInterface $adminAccess = null) {}

    public function descriptor(): ProviderDescriptor {
        return new ProviderDescriptor(Application::APP_ID, 'AD Planer', '1.0', ['nextcloud-user'], ['personal-data'], 500);
    }

    public function collect(PersonalDataRequest $request): PersonalDataPage {
        if ($request->subject()->subjectType() !== 'nextcloud-user') return new PersonalDataPage('not_applicable');
        if ($request->cursor() !== null) throw new InvalidArgumentException('AD Planer does not support cursor paging.');
        $limit = $request->pageLimit();
        $subjectUid = $request->subject()->subjectId();
        $data = $this->repository->personalDataForUid($subjectUid, $limit + 1);
        $items = [];
        foreach ($this->adminAccess?->historyForUid($subjectUid,$limit+1)??[] as $row) $items[]=$this->adminAccessItem($row,$subjectUid);
        foreach ($data['candidates'] ?? [] as $row) {
            $subjectIsCandidate = (string)$row['assistant_uid'] === $subjectUid;
            $items[] = $subjectIsCandidate ? $this->candidateItem($row, $subjectUid) : $this->activityItem($row);
        }
        foreach ($data['dayNotes'] ?? [] as $row) $items[] = $this->dayNoteItem($row);
        foreach ($data['monthPlans'] ?? [] as $row) $items[] = $this->monthPlanItem($row);
        $complete = count($items) <= $limit;
        $items = array_slice($items, 0, $limit);
        if ($items === []) return new PersonalDataPage('not_applicable');
        return new PersonalDataPage($complete ? 'complete' : 'partial', $items, $complete ? [] : ['Ausgabelimit erreicht; weitere Planungsdaten können vorhanden sein.']);
    }

    private function adminAccessItem(array $row,string $subjectUid):PersonalDataEntry {
        $roles=[];if($row['targetUid']===$subjectUid)$roles[]='Ziel der Vollzugriffsfreigabe';if($row['grantedBy']===$subjectUid)$roles[]='Freigebende Administration';if($row['revokedBy']===$subjectUid)$roles[]='Widerrufende Administration';$actualEnd=$row['revokedAt']??$row['endsAt'];
        return $this->entry('admin-access','Zeitlich begrenzter Admin-Vollzugriff',self::shortDateTime($row['startsAt']),'admin-access:'.(string)$row['id'],['Eigene Rolle im Vorgang'=>implode(', ',$roles),'Beginn'=>$row['startsAt']->format(DATE_ATOM),'Geplantes Ende'=>$row['endsAt']->format(DATE_ATOM),'Tatsächliches Ende'=>$actualEnd->format(DATE_ATOM),'Status'=>$row['revokedAt']===null?'planmäßig beendet oder noch aktiv':'widerrufen'],'Nachweis einer zeitlich begrenzten administrativen Planer-Freigabe','Kennungen anderer beteiligter Administrator*innen werden nicht ausgegeben.');
    }

    private function candidateItem(array $row, string $subjectUid): PersonalDataEntry {
        $selfCreated = (string)$row['created_by_uid'] === $subjectUid;
        return $this->entry(
            'shift_assignment',
            'Schichtwunsch oder Schichtzuweisung',
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
            $selfCreated ? null : 'Die eintragende Person wird zum Schutz ihrer Datenschutzrechte nicht genannt.',
        );
    }

    private function activityItem(array $row): PersonalDataEntry {
        return $this->entry(
            'planning_activity',
            'Planungsaktivität',
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
            'Die von der Aktivität betroffene andere Person wird nicht genannt.',
        );
    }

    private function dayNoteItem(array $row): PersonalDataEntry {
        return $this->entry(
            'day_note_activity',
            'Bearbeitete Tagesnotiz',
            self::shortDate((string)$row['work_date']),
            'day-note:' . (string)$row['id'],
            [
                'Team' => (string)$row['team_code'],
                'Datum' => self::shortDate((string)$row['work_date']),
                'Bearbeitet am' => self::shortDateTime($row['updated_at'] ?? ''),
                'Notizinhalt' => 'Inhalt wird wegen möglicher Angaben zu anderen Personen nicht automatisch ausgegeben.',
            ],
            'Planungsbezogene Tagesinformation und Nachvollziehbarkeit der letzten Bearbeitung',
            'Freie Tagesnotizen können Angaben über andere Personen enthalten.',
        );
    }

    private function monthPlanItem(array $row): PersonalDataEntry {
        $status = ['draft'=>'Entwurf','planned'=>'Geplant','approved'=>'Genehmigt'][(string)$row['status']] ?? (string)$row['status'];
        return $this->entry(
            'month_plan_activity',
            'Bearbeiteter Monatsplan',
            self::shortMonth((string)$row['plan_month']) . ' – ' . $status,
            'month-plan:' . (string)$row['id'],
            [
                'Team' => (string)$row['team_code'],
                'Planungsmonat' => self::shortMonth((string)$row['plan_month']),
                'Status' => $status,
                'Bearbeitet am' => self::shortDateTime($row['updated_at'] ?? ''),
            ],
            'Nachvollziehbarkeit der von dir zuletzt bearbeiteten Monatsplanung',
            null,
        );
    }

    private function entry(string $categoryId, string $categoryLabel, string $summary, string $reference, array $attributes, string $purpose, ?string $thirdPartyNotice): PersonalDataEntry {
        return new PersonalDataEntry(
            $categoryId,
            $categoryLabel,
            $reference,
            $summary,
            $purpose,
            'Eigene Eingaben sowie Eingaben berechtigter Einsatzbegleitungen im Dienstplan',
            ['Mitglieder des jeweiligen Assistenzteams im zulässigen Planungsscope', 'Berechtigte Einsatzbegleitungen', 'Nextcloud-Administrator*innen mit Verwaltungsrechten'],
            self::RETENTION,
            'Durch AD Planer sind keine Drittlandübermittlungen vorgesehen.',
            'Planungshinweise und Konfliktprüfungen unterstützen die Bearbeitung; sie treffen keine Entscheidung mit rechtlicher oder vergleichbar erheblicher Wirkung.',
            $thirdPartyNotice,
            $attributes,
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
