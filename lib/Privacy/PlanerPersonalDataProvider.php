<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Privacy;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use OCA\FlzPlaner\AppInfo\Application;
use OCA\FlzPlaner\Repository\ShiftPlanRepository;
use OCA\FlzPlaner\Repository\TemporaryAdminAccessRepositoryInterface;
use OCA\FlzDataProtection\PublicApi\V1\PersonalDataEntry;
use OCA\FlzDataProtection\PublicApi\V1\PersonalDataPage;
use OCA\FlzDataProtection\PublicApi\V1\PersonalDataProvider;
use OCA\FlzDataProtection\PublicApi\V1\PersonalDataRequest;
use OCA\FlzDataProtection\PublicApi\V1\ProviderDescriptor;
use OCP\IL10N;

final class PlanerPersonalDataProvider implements PersonalDataProvider {
    
    public function __construct(private ShiftPlanRepository $repository, private ?TemporaryAdminAccessRepositoryInterface $adminAccess = null, private ?IL10N $l10n = null) {}

    /** Lokalisierung fachlicher Texte; im Test ohne IL10N identity. */
    private function t(string $text): string
    {
        return isset($this->l10n) ? $this->l10n->t($text) : $text;
    }

    public function descriptor(): ProviderDescriptor {
        return new ProviderDescriptor(Application::APP_ID, $this->t('Filzmann Assistenzplanung'), '1.0', ['nextcloud-user'], ['personal-data'], 500);
    }

    public function collect(PersonalDataRequest $request): PersonalDataPage {
        if ($request->subject()->subjectType() !== 'nextcloud-user') return new PersonalDataPage('not_applicable');
        if ($request->cursor() !== null) throw new InvalidArgumentException('Filzmann Assistenzplanung unterstützt kein Cursor-Paging.');
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
        foreach ($data['workloadLimits'] ?? [] as $row) $items[] = $this->workloadLimitItem($row);
        foreach ($data['regularShifts'] ?? [] as $row) $items[] = $this->regularShiftItem($row);
        foreach ($data['fixedConflicts'] ?? [] as $row) $items[] = $this->fixedConflictItem($row);
        $complete = count($items) <= $limit;
        $items = array_slice($items, 0, $limit);
        if ($items === []) return new PersonalDataPage('not_applicable');
        return new PersonalDataPage($complete ? 'complete' : 'partial', $items, $complete ? [] : [$this->t('Ausgabelimit erreicht; weitere Planungsdaten können vorhanden sein.')]);
    }

    private function adminAccessItem(array $row, string $subjectUid): PersonalDataEntry {
        $roles = [];
        if ($row['targetUid'] === $subjectUid) $roles[] = $this->t('Ziel der Vollzugriffsfreigabe');
        if ($row['grantedBy'] === $subjectUid) $roles[] = $this->t('Freigebende berechtigte Person');
        if ($row['revokedBy'] === $subjectUid) $roles[] = $this->t('Widerrufende berechtigte Person');
        $actualEnd = $row['revokedAt'] ?? $row['endsAt'];
        return new PersonalDataEntry(
            'admin-access',
            $this->t('Zeitlich begrenzter Admin-Vollzugriff'),
            'admin-access:' . (string)$row['id'],
            $this->shortDateTime($row['startsAt']),
            $this->t('Nachweis einer zeitlich begrenzten administrativen Planer-Freigabe'),
            $this->t('App-lokale Freigabesteuerung im Filzmann Assistenzplanung'),
            [$this->t('Betroffene Person und ausdrücklich berechtigte Prüfrolle')],
            $this->t('Keine feste Löschfrist festgelegt; gespeichert bis zur fachlich oder gesetzlich veranlassten Löschung.'),
            $this->t('Durch Filzmann Assistenzplanung sind keine Drittlandübermittlungen vorgesehen.'),
            $this->t('Der Server beendet den Vollzugriff spätestens nach 24 Stunden automatisch; es findet keine Entscheidung mit rechtlicher oder vergleichbar erheblicher Wirkung statt.'),
            $this->t('Kennungen anderer beteiligter Personen werden nicht ausgegeben.'),
            [
                $this->t('Eigene Rolle im Vorgang') => implode(', ', $roles),
                $this->t('Beginn') => $row['startsAt']->format(DATE_ATOM),
                $this->t('Geplantes Ende') => $row['endsAt']->format(DATE_ATOM),
                $this->t('Tatsächliches Ende') => $actualEnd->format(DATE_ATOM),
                $this->t('Status') => $row['revokedAt'] === null ? $this->t('planmäßig beendet oder noch aktiv') : 'widerrufen',
            ],
        );
    }

    private function candidateItem(array $row, string $subjectUid): PersonalDataEntry {
        $selfCreated = (string)$row['created_by_uid'] === $subjectUid;
        return $this->entry(
            'shift_assignment',
            $this->t('Schichtwunsch oder Schichtzuweisung'),
            $this->shortDate((string)$row['work_date']) . ' – ' . (string)$row['label'],
            'shift-candidate:' . (string)$row['id'],
            [
                $this->t('Team') => (string)$row['team_code'],
                $this->t('Datum') => $this->shortDate((string)$row['work_date']),
                $this->t('Schicht') => (string)$row['label'],
                $this->t('Beginn') => (string)$row['starts_at'] . $this->t(' Uhr'),
                $this->t('Ende') => (string)$row['ends_at'] . $this->t(' Uhr'),
                $this->t('Eintragung') => $selfCreated ? $this->t('Von dir selbst eingetragen') : $this->t('Von einer berechtigten Person eingetragen'),
                $this->t('Eingetragen am') => $this->shortDateTime($row['created_at'] ?? ''),
                $this->t('Präferenz') => ['favorite'=>$this->t('Lieblingsschicht'),'emergency'=>$this->t('Nur im Notfall'),'neutral'=>$this->t('Neutral')][(string)($row['preference'] ?? 'neutral')] ?? $this->t('Neutral'),
                'Schichtstatus' => ($row['assignment_source'] ?? 'manual') === 'regular' ? $this->t('Regelmäßige feste Schicht') : $this->t('Manueller Schichtwunsch'),
                $this->t('Ausnahme') => (bool)($row['fixed_deleted'] ?? false) ? $this->t('Für dieses Datum ausdrücklich nicht möglich') : $this->t('Keine Lösch-Ausnahme'),
                $this->t('Individuelle Konfliktauflösung') => (bool)($row['fixed_modified'] ?? false) ? $this->t('Für dieses Datum in einen normalen Schichtwunsch umgewandelt') : $this->t('Keine individuelle Umwandlung'),
                $this->t('Eigene Anmerkung') => trim((string)($row['candidate_note'] ?? '')) === '' ? $this->t('Keine Anmerkung gespeichert') : $this->t('Inhalt wird wegen möglicher Angaben zu anderen Personen nicht automatisch ausgegeben.'),
            ],
            $this->t('Erfassung deines Schichtwunsches oder deiner Dienstzuweisung'),
            $selfCreated ? null : $this->t('Die eintragende Person wird zum Schutz ihrer Datenschutzrechte nicht genannt.'),
        );
    }

    private function activityItem(array $row): PersonalDataEntry {
        return $this->entry(
            'planning_activity',
            $this->t('Planungsaktivität'),
            $this->shortDate((string)$row['work_date']) . ' – ' . (string)$row['label'],
            'shift-candidate-activity:' . (string)$row['id'],
            [
                $this->t('Team') => (string)$row['team_code'],
                $this->t('Datum') => $this->shortDate((string)$row['work_date']),
                $this->t('Schicht') => (string)$row['label'],
                $this->t('Beginn') => (string)$row['starts_at'] . $this->t(' Uhr'),
                $this->t('Ende') => (string)$row['ends_at'] . $this->t(' Uhr'),
                $this->t('Eingetragen am') => $this->shortDateTime($row['created_at'] ?? ''),
            ],
            $this->t('Nachvollziehbarkeit einer von dir vorgenommenen Planungsänderung'),
            $this->t('Die von der Aktivität betroffene andere Person wird nicht genannt.'),
        );
    }

    private function dayNoteItem(array $row): PersonalDataEntry {
        return $this->entry(
            'day_note_activity',
            $this->t('Bearbeitete Tagesnotiz'),
            $this->shortDate((string)$row['work_date']),
            'day-note:' . (string)$row['id'],
            [
                $this->t('Team') => (string)$row['team_code'],
                $this->t('Datum') => $this->shortDate((string)$row['work_date']),
                $this->t('Bearbeitet am') => $this->shortDateTime($row['updated_at'] ?? ''),
                'Notizinhalt' => $this->t('Inhalt wird wegen möglicher Angaben zu anderen Personen nicht automatisch ausgegeben.'),
            ],
            $this->t('Planungsbezogene Tagesinformation und Nachvollziehbarkeit der letzten Bearbeitung'),
            $this->t('Freie Tagesnotizen können Angaben über andere Personen enthalten.'),
        );
    }

    private function monthPlanItem(array $row): PersonalDataEntry {
        $status = ['draft'=>$this->t('Entwurf'),'planned'=>$this->t('Geplant'),'approved'=>$this->t('Genehmigt')][(string)$row['status']] ?? (string)$row['status'];
        return $this->entry(
            'month_plan_activity',
            $this->t('Bearbeiteter Monatsplan'),
            $this->shortMonth((string)$row['plan_month']) . ' – ' . $status,
            'month-plan:' . (string)$row['id'],
            [
                $this->t('Team') => (string)$row['team_code'],
                'Planungsmonat' => $this->shortMonth((string)$row['plan_month']),
                $this->t('Status') => $status,
                $this->t('Bearbeitet am') => $this->shortDateTime($row['updated_at'] ?? ''),
            ],
            $this->t('Nachvollziehbarkeit der von dir zuletzt bearbeiteten Monatsplanung'),
            null,
        );
    }

    private function workloadLimitItem(array $row): PersonalDataEntry {
        $value = static fn(mixed $item): string => $item === null ? $this->t('Keine Grenze') : (string)$item;
        return $this->entry(
            'workload_limits',
            $this->t('Persönliche Schichtgrenzen'),
            $this->t('Team ') . (string)$row['team_code'],
            'workload-limits:' . (string)$row['id'],
            [
                $this->t('Team')=>(string)$row['team_code'],
                $this->t('Minimum pro Woche')=>$value($row['weekly_min'] ?? null),
                $this->t('Maximum pro Woche')=>$value($row['weekly_max'] ?? null),
                $this->t('Minimum pro Monat')=>$value($row['monthly_min'] ?? null),
                $this->t('Maximum pro Monat')=>$value($row['monthly_max'] ?? null),
                $this->t('Bearbeitet am')=>$this->shortDateTime($row['updated_at'] ?? ''),
            ],
            $this->t('Persönliche Orientierung und Auslastungsdarstellung in der Wunschdienstplanung'),
            null,
        );
    }

    private function regularShiftItem(array $row): PersonalDataEntry {
        $days=[1=>$this->t('Montag'),2=>$this->t('Dienstag'),3=>$this->t('Mittwoch'),4=>$this->t('Donnerstag'),5=>$this->t('Freitag'),6=>$this->t('Samstag'),7=>$this->t('Sonntag')];
        return $this->entry('regular_shift',$this->t('Regelmäßige feste Schicht'),($days[(int)$row['weekday']]??$this->t('Wochentag')).' – '.(string)$row['segment_key'],'regular-shift:'.(string)$row['id'],[
            $this->t('Team')=>(string)$row['team_code'],$this->t('Wochentag')=>$days[(int)$row['weekday']]??(string)$row['weekday'],'Schichtkennung'=>(string)$row['segment_key'],$this->t('Bearbeitet am')=>$this->shortDateTime($row['updated_at']??''),
        ],$this->t('Automatische Vorbelegung persönlicher fester Schichten'),null);
    }

    private function fixedConflictItem(array $row): PersonalDataEntry {
        return $this->entry('fixed_shift_conflict',$this->t('Festschichtkonflikt'),$this->t('Status ').(string)$row['status'],'fixed-conflict:'.(string)$row['id'],[
            $this->t('Status')=>(string)$row['status'],$this->t('Weitergegeben am')=>$this->shortDateTime($row['reported_at']??''),$this->t('Gelöst am')=>empty($row['resolved_at'])?$this->t('Noch nicht gelöst'):$this->shortDateTime($row['resolved_at']),
        ],$this->t('Weitergabe und Auflösung einer mehrfach fest belegten Schicht'),$this->t('Kennungen anderer beteiligter Personen werden nicht ausgegeben.'));
    }

    private function entry(string $categoryId, string $categoryLabel, string $summary, string $reference, array $attributes, string $purpose, ?string $thirdPartyNotice): PersonalDataEntry {
        return new PersonalDataEntry(
            $categoryId,
            $categoryLabel,
            $reference,
            $summary,
            $purpose,
            $this->t('Eigene Eingaben sowie Eingaben berechtigter Einsatzbegleitungen im Dienstplan'),
            ['Mitglieder des jeweiligen Assistenzteams im zulässigen Planungsscope', 'Berechtigte Einsatzbegleitungen', 'Nextcloud-Administrator*innen mit Verwaltungsrechten'],
            $this->t('Keine feste Löschfrist festgelegt; gespeichert bis zur fachlich oder gesetzlich veranlassten Löschung.'),
            $this->t('Durch Filzmann Assistenzplanung sind keine Drittlandübermittlungen vorgesehen.'),
            $this->t('Planungshinweise und Konfliktprüfungen unterstützen die Bearbeitung; sie treffen keine Entscheidung mit rechtlicher oder vergleichbar erheblicher Wirkung.'),
            $thirdPartyNotice,
            $attributes,
        );
    }

    private function shortDate(string $value): string {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));
        return $date === false ? $value : $date->format('d.m.y');
    }

    private function shortMonth(string $value): string {
        $date = DateTimeImmutable::createFromFormat('!Y-m', $value);
        return $date === false ? $value : $date->format('m.y');
    }

    private function shortDateTime(mixed $value): string {
        if ($value instanceof DateTimeInterface) return $value->format('d.m.y, H:i') . $this->t(' Uhr');
        try { return (new DateTimeImmutable((string)$value))->format('d.m.y, H:i') . $this->t(' Uhr'); }
        catch (\Throwable) { return (string)$value; }
    }
}
