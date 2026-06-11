<?php

declare(strict_types=1);

namespace Fissible\Phone\Services;

use Carbon\CarbonInterface;
use Fissible\Phone\Models\PhoneNumber;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Carbon;

class BusinessHours
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function isOpen(PhoneNumber $phoneNumber, ?CarbonInterface $now = null): bool
    {
        $hours = $this->hoursFor($phoneNumber);
        $timezone = $this->timezone($hours);
        $localNow = $now instanceof CarbonInterface
            ? Carbon::parse($now->toDateTimeString(), $now->getTimezone())->setTimezone($timezone)
            : Carbon::now($timezone);

        $holidayWindows = $this->holidayWindows($hours, $localNow);

        if ($holidayWindows !== null) {
            return $this->matchesWindows($holidayWindows, $localNow, includePreviousDay: false);
        }

        $weekly = $this->array($hours['weekly'] ?? []);

        if ($weekly === []) {
            return true;
        }

        return $this->matchesWindows($this->windowsForDay($weekly, $localNow), $localNow)
            || $this->matchesPreviousDayWindows($weekly, $localNow);
    }

    /** @return array<string, mixed> */
    public function hoursFor(PhoneNumber $phoneNumber): array
    {
        $default = $this->array($this->config->get('phone.business_hours', []));
        $override = $this->array($phoneNumber->business_hours);

        return array_replace($default, $override);
    }

    /** @param array<string, mixed> $hours */
    public function afterHoursMode(array $hours): ?string
    {
        return $this->string($hours['after_hours_mode'] ?? null);
    }

    /** @param array<string, mixed> $hours */
    private function timezone(array $hours): string
    {
        return $this->string($hours['timezone'] ?? null)
            ?: (string) $this->config->get('app.timezone', 'UTC');
    }

    /**
     * @param  array<string, mixed>  $hours
     * @return list<array{start: string, end: string}>|null
     */
    private function holidayWindows(array $hours, CarbonInterface $localNow): ?array
    {
        $holidays = $this->array($hours['holidays'] ?? []);
        $date = $localNow->toDateString();

        foreach ($holidays as $key => $value) {
            if (is_int($key) && is_string($value) && $value === $date) {
                return [];
            }

            if (is_string($key) && $key === $date) {
                return is_array($value) ? $this->normalizeWindows($value) : [];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $weekly
     * @return list<array{start: string, end: string}>
     */
    private function windowsForDay(array $weekly, CarbonInterface $localNow): array
    {
        foreach ($this->dayKeys($localNow) as $key) {
            if (array_key_exists($key, $weekly)) {
                return $this->normalizeWindows($weekly[$key]);
            }
        }

        return [];
    }

    /** @return list<string> */
    private function dayKeys(CarbonInterface $localNow): array
    {
        $day = strtolower($localNow->format('l'));
        $short = strtolower($localNow->format('D'));

        return [
            $day,
            $short,
            (string) $localNow->dayOfWeekIso,
        ];
    }

    /**
     * @param  list<array{start: string, end: string}>  $windows
     */
    private function matchesWindows(array $windows, CarbonInterface $localNow, bool $includePreviousDay = true): bool
    {
        $minute = ((int) $localNow->format('H')) * 60 + (int) $localNow->format('i');

        foreach ($windows as $window) {
            $start = $this->timeToMinute($window['start']);
            $end = $this->timeToMinute($window['end']);

            if ($start === null || $end === null) {
                continue;
            }

            if ($start === $end) {
                return true;
            }

            if ($end > $start && $minute >= $start && $minute < $end) {
                return true;
            }

            if ($includePreviousDay && $end < $start && $minute >= $start) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $weekly */
    private function matchesPreviousDayWindows(array $weekly, CarbonInterface $localNow): bool
    {
        $yesterday = Carbon::parse($localNow->toDateTimeString(), $localNow->getTimezone())->subDay();
        $minute = ((int) $localNow->format('H')) * 60 + (int) $localNow->format('i');

        foreach ($this->windowsForDay($weekly, $yesterday) as $window) {
            $start = $this->timeToMinute($window['start']);
            $end = $this->timeToMinute($window['end']);

            if ($start !== null && $end !== null && $end < $start && $minute < $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{start: string, end: string}>
     */
    private function normalizeWindows(mixed $value): array
    {
        if (is_bool($value)) {
            return $value ? [['start' => '00:00', 'end' => '00:00']] : [];
        }

        if (is_string($value)) {
            return $this->windowFromString($value);
        }

        if (! is_array($value)) {
            return [];
        }

        if (isset($value['start'], $value['end'])) {
            return [[
                'start' => (string) $value['start'],
                'end' => (string) $value['end'],
            ]];
        }

        $windows = [];

        foreach ($value as $window) {
            if (is_string($window)) {
                array_push($windows, ...$this->windowFromString($window));

                continue;
            }

            if (is_array($window) && isset($window['start'], $window['end'])) {
                $windows[] = [
                    'start' => (string) $window['start'],
                    'end' => (string) $window['end'],
                ];
            }
        }

        return $windows;
    }

    /**
     * @return list<array{start: string, end: string}>
     */
    private function windowFromString(string $value): array
    {
        $value = trim($value);

        if ($value === '' || strtolower($value) === 'closed') {
            return [];
        }

        if (strtolower($value) === 'open') {
            return [['start' => '00:00', 'end' => '00:00']];
        }

        if (! str_contains($value, '-')) {
            return [];
        }

        [$start, $end] = array_map('trim', explode('-', $value, 2));

        return [['start' => $start, 'end' => $end]];
    }

    private function timeToMinute(string $value): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return $hour * 60 + $minute;
    }

    /** @return array<string, mixed> */
    private function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function string(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
