<?php

namespace App\Services;

use Carbon\Carbon;

class EsslAutoSyncConfig
{
    public const MAX_RANGES = 4;

    public const DEFAULT_INTERVAL_MINUTES = 15;

    public static function defaultRanges(): array
    {
        return [
            ['label' => 'Morning IN', 'from' => '07:00', 'to' => '10:00', 'interval_minutes' => self::DEFAULT_INTERVAL_MINUTES],
            ['label' => 'Evening OUT', 'from' => '18:00', 'to' => '20:00', 'interval_minutes' => self::DEFAULT_INTERVAL_MINUTES],
        ];
    }

    public static function getRanges(?int $companyId = null): array
    {
        $raw = getSetting('essl_auto_sync_ranges', null, $companyId);

        if ($raw) {
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            if (is_array($decoded) && count($decoded) > 0) {
                return self::normalizeRanges($decoded, $companyId);
            }
        }

        // Migrate legacy morning/evening point times into short ranges
        $morning = self::normalizeTime(getSetting('essl_auto_sync_morning_time', '08:30', $companyId));
        $evening = self::normalizeTime(getSetting('essl_auto_sync_evening_time', '19:00', $companyId));

        if (getSetting('essl_auto_sync_morning_time', null, $companyId) || getSetting('essl_auto_sync_evening_time', null, $companyId)) {
            return self::normalizeRanges([
                ['label' => 'Morning', 'from' => $morning, 'to' => self::addMinutesToTime($morning, 30), 'interval_minutes' => self::getIntervalMinutes($companyId)],
                ['label' => 'Evening', 'from' => $evening, 'to' => self::addMinutesToTime($evening, 30), 'interval_minutes' => self::getIntervalMinutes($companyId)],
            ], $companyId);
        }

        return self::defaultRanges();
    }

    public static function getIntervalMinutes(?int $companyId = null): int
    {
        $value = (int) getSetting('essl_auto_sync_interval_minutes', self::DEFAULT_INTERVAL_MINUTES, $companyId);

        return max(5, min(60, $value ?: self::DEFAULT_INTERVAL_MINUTES));
    }

    public static function isEnabled(?int $companyId = null): bool
    {
        return in_array((string) getSetting('essl_auto_sync_enabled', '0', $companyId), ['1', 'true'], true);
    }

    /** India (IST) when company setting missing or UTC — matches attendance wall-clock. */
    public static function companyTimezone(?int $companyId = null): string
    {
        $tz = data_get(settings($companyId), 'defaultTimezone', config('app.timezone', 'Asia/Kolkata'));

        if (! $tz || $tz === 'UTC') {
            return 'Asia/Kolkata';
        }

        return $tz;
    }

    public static function applyCompanyTimezone(?int $companyId = null): string
    {
        $tz = self::companyTimezone($companyId);
        config(['app.timezone' => $tz]);
        date_default_timezone_set($tz);

        return $tz;
    }

    /**
     * @return array{index: int, label: string}|null
     */
    public static function activeRange(Carbon $now, ?int $companyId = null): ?array
    {
        $currentMinutes = self::timeToMinutes($now->format('H:i'));

        foreach (self::getRanges($companyId) as $index => $range) {
            $from = self::timeToMinutes($range['from']);
            $to = self::timeToMinutes($range['to']);

            if ($from <= $currentMinutes && $currentMinutes <= $to) {
                return ['index' => $index, 'label' => $range['label']];
            }
        }

        return null;
    }

    public static function shouldRunNow(Carbon $now, int $rangeIndex, ?int $companyId = null): bool
    {
        $ranges = self::getRanges($companyId);
        $interval = $ranges[$rangeIndex]['interval_minutes'] ?? self::getIntervalMinutes($companyId);
        $lastRun = getSetting(self::lastRunKey($rangeIndex), null, $companyId);

        if (!$lastRun) {
            return true;
        }

        try {
            $tz = self::companyTimezone($companyId);
            $lastRunAt = Carbon::parse($lastRun, $tz);
            $nowInTz = $now->copy()->timezone($tz);

            return $lastRunAt->diffInMinutes($nowInTz) >= $interval;
        } catch (\Throwable) {
            return true;
        }
    }

    public static function markRangeRun(int $rangeIndex, Carbon $now, string $label, ?int $companyId = null): void
    {
        updateSetting(self::lastRunKey($rangeIndex), $now->format('Y-m-d H:i:s'), $companyId);
        updateSetting('essl_auto_sync_last_run_at', $now->format('Y-m-d H:i:s'), $companyId);
        updateSetting('essl_auto_sync_last_run_slot', $label, $companyId);
    }

    public static function lastRunKey(int $rangeIndex): string
    {
        return 'essl_auto_sync_range_' . $rangeIndex . '_last_at';
    }

    /**
     * @param  array<int, array{label?: string, from?: string, to?: string, interval_minutes?: int|string}>  $ranges
     * @return array<int, array{label: string, from: string, to: string, interval_minutes: int}>
     */
    public static function normalizeRanges(array $ranges, ?int $companyId = null): array
    {
        $normalized = [];
        $fallbackInterval = self::getIntervalMinutes($companyId);

        foreach (array_slice($ranges, 0, self::MAX_RANGES) as $i => $range) {
            $from = self::normalizeTime($range['from'] ?? '07:00');
            $to = self::normalizeTime($range['to'] ?? '10:00');
            $label = trim((string) ($range['label'] ?? '')) ?: 'Range ' . ($i + 1);

            if (self::timeToMinutes($from) >= self::timeToMinutes($to)) {
                continue;
            }

            $normalized[] = [
                'label' => $label,
                'from' => $from,
                'to' => $to,
                'interval_minutes' => self::normalizeIntervalMinutes($range['interval_minutes'] ?? null, $fallbackInterval),
            ];
        }

        return $normalized ?: self::defaultRanges();
    }

    public static function normalizeIntervalMinutes(mixed $value, ?int $fallback = null): int
    {
        $interval = (int) ($value ?: $fallback ?: self::DEFAULT_INTERVAL_MINUTES);

        return max(5, min(60, $interval ?: self::DEFAULT_INTERVAL_MINUTES));
    }

    public static function normalizeTime(?string $time): string
    {
        if (!$time) {
            return '07:00';
        }

        $parts = explode(':', $time);

        return sprintf('%02d:%02d', (int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0));
    }

    private static function timeToMinutes(string $time): int
    {
        $parts = explode(':', self::normalizeTime($time));

        return ((int) $parts[0]) * 60 + (int) $parts[1];
    }

    private static function addMinutesToTime(string $time, int $minutes): string
    {
        $total = self::timeToMinutes($time) + $minutes;

        return sprintf('%02d:%02d', intdiv($total, 60) % 24, $total % 60);
    }

    public static function isSchedulerRunning(?int $companyId = null): bool
    {
        $ping = getSetting('laravel_scheduler_last_ping', null, $companyId);
        if (! $ping) {
            return false;
        }

        try {
            $tz = self::companyTimezone($companyId);

            return Carbon::parse($ping, $tz)->diffInMinutes(Carbon::now($tz)) <= 2;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function settingsPayload(?int $companyId = null): array
    {
        $tz = self::companyTimezone($companyId);
        $now = Carbon::now($tz);
        $active = self::activeRange($now, $companyId);
        $schedulerRunning = self::isSchedulerRunning($companyId);

        return [
            'enabled' => self::isEnabled($companyId),
            'ranges' => self::getRanges($companyId),
            'last_run_at' => getSetting('essl_auto_sync_last_run_at', null, $companyId),
            'last_run_slot' => getSetting('essl_auto_sync_last_run_slot', null, $companyId),
            'timezone' => $tz,
            'timezone_label' => $tz === 'Asia/Kolkata' ? 'IST (India)' : $tz,
            'current_time' => $now->format('Y-m-d H:i:s'),
            'active_range' => $active['label'] ?? null,
            'scheduler_last_ping' => getSetting('laravel_scheduler_last_ping', null, $companyId),
            'scheduler_running' => $schedulerRunning,
        ];
    }
}
