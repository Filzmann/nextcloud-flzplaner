<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Service;

use OCA\AdPlaner\Model\ShiftDefinition;

class ShiftConfigService {
    public function defaults(): array {
        return [
            'meetingDay' => '',
            'shifts' => [
                $this->shift('early', 'Früh', '08:00', '14:00', true),
                $this->shift('late', 'Spät', '14:00', '20:00', true),
                $this->shift('night', 'Nacht', '20:00', '08:00', true),
            ],
        ];
    }

    public function normalize(array $settings): array {
        $defaults = $this->defaults();
        $shifts = $settings['shifts'] ?? $defaults['shifts'];
        if (!is_array($shifts)) throw new \InvalidArgumentException('Schichten müssen als Liste übergeben werden.');

        return [
            'meetingDay' => $this->normalizeOptionalDate((string)($settings['meetingDay'] ?? $defaults['meetingDay'])),
            'shifts' => $this->normalizeShifts($shifts),
        ];
    }

    public function segments(array $settings): array {
        return $this->normalize($settings)['shifts'];
    }

    public function monthDays(string $month): array {
        $month = $this->normalizeMonth($month);
        $first = new \DateTimeImmutable($month . '-01');
        $last = $first->modify('last day of this month');
        $days = [];

        for ($day = $first; $day <= $last; $day = $day->modify('+1 day')) {
            $days[] = [
                'date' => $day->format('Y-m-d'),
                'dayOfMonth' => (int)$day->format('j'),
                'weekday' => (int)$day->format('N'),
            ];
        }

        return $days;
    }

    public function yearDays(int $year): array {
        if ($year < 2000 || $year > 2100) {
            throw new \InvalidArgumentException('Das Jahr ist außerhalb des erlaubten Bereichs.');
        }

        $first = new \DateTimeImmutable(sprintf('%04d-01-01', $year));
        $last = new \DateTimeImmutable(sprintf('%04d-12-31', $year));
        $days = [];

        for ($day = $first; $day <= $last; $day = $day->modify('+1 day')) {
            $days[] = [
                'date' => $day->format('Y-m-d'),
                'dayOfYear' => (int)$day->format('z') + 1,
                'dayOfMonth' => (int)$day->format('j'),
                'month' => (int)$day->format('n'),
                'weekday' => (int)$day->format('N'),
            ];
        }

        return $days;
    }

    public function normalizeMonth(string $month): string {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new \InvalidArgumentException('Der Monat muss im Format JJJJ-MM angegeben werden.');
        }

        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        if ($year < 2000 || $year > 2100 || $monthNumber < 1 || $monthNumber > 12) {
            throw new \InvalidArgumentException('Der Monat ist außerhalb des erlaubten Bereichs.');
        }

        return sprintf('%04d-%02d', $year, $monthNumber);
    }

    public function normalizeDate(string $date): string {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException('Das Datum muss im Format JJJJ-MM-TT angegeben werden.');
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('Das Datum ist ungültig.');
        }

        return $date;
    }

    private function normalizeTime(string $time): string {
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw new \InvalidArgumentException('Schichtzeiten müssen im Format HH:MM angegeben werden.');
        }

        return $time;
    }

    private function normalizeShifts(array $shifts): array {
        if ($shifts === []) {
            throw new \InvalidArgumentException('Mindestens eine Schicht muss konfiguriert sein.');
        }

        if (!array_is_list($shifts)) {
            throw new \InvalidArgumentException('Schichten müssen als Liste übergeben werden.');
        }

        if (count($shifts) > 64) {
            throw new \InvalidArgumentException('Höchstens 64 Schichten können konfiguriert werden.');
        }

        $normalized = [];
        $keys = [];
        foreach (array_values($shifts) as $index => $shift) {
            if (!is_array($shift)) {
                throw new \InvalidArgumentException('Schichten müssen als Liste übergeben werden.');
            }

            $key = $this->normalizeShiftKey((string)($shift['key'] ?? ''), $index);
            if (isset($keys[$key])) {
                throw new \InvalidArgumentException('Schichtschlüssel müssen eindeutig sein.');
            }

            $label = trim((string)($shift['label'] ?? ''));
            if ($label === '') {
                throw new \InvalidArgumentException('Schichtnamen dürfen nicht leer sein.');
            }

            $labelLength = preg_match_all('/./us', $label);
            if ($labelLength === false) {
                throw new \InvalidArgumentException('Schichtnamen müssen gültiges UTF-8 enthalten.');
            }
            if ($labelLength > 64) {
                throw new \InvalidArgumentException('Schichtnamen dürfen höchstens 64 Zeichen lang sein.');
            }

            $startsAt = $this->normalizeTime((string)($shift['startsAt'] ?? ''));
            $endsAt = $this->normalizeTime((string)($shift['endsAt'] ?? ''));
            $this->assertShiftFitsInDay($startsAt, $endsAt);

            $keys[$key] = true;
            $normalized[] = $this->shift($key, $label, $startsAt, $endsAt, $this->normalizeBoolean($shift['enabled'] ?? true));
        }

        return $normalized;
    }

    private function normalizeShiftKey(string $key, int $index): string {
        $key = trim($key);
        if ($key === '') {
            return 'shift_' . ($index + 1);
        }

        if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $key)) {
            throw new \InvalidArgumentException('Schichtschlüssel dürfen nur Buchstaben, Ziffern, Unterstriche und Bindestriche enthalten.');
        }

        return $key;
    }

    private function assertShiftFitsInDay(string $startsAt, string $endsAt): void {
        $duration = $this->minutesOfDay($endsAt) - $this->minutesOfDay($startsAt);
        if ($duration <= 0) {
            $duration += 1440;
        }

        if ($duration < 1 || $duration > 1440) {
            throw new \InvalidArgumentException('Eine Schicht muss innerhalb von 24 Stunden liegen.');
        }
    }

    private function minutesOfDay(string $time): int {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * 60) + $minutes;
    }

    private function normalizeBoolean(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            if ($value === 0 || $value === 1) {
                return $value === 1;
            }
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        throw new \InvalidArgumentException('Der Aktivstatus einer Schicht muss eindeutig wahr oder falsch sein.');
    }

    private function shift(string $key, string $label, string $startsAt, string $endsAt, bool $enabled): array {
        return (new ShiftDefinition($key, $label, $startsAt, $endsAt, $enabled))->toArray();
    }

    private function normalizeOptionalDate(string $date): string {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        return $this->normalizeDate($date);
    }
}
