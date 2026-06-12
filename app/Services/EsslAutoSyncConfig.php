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
            ['label' => 'Morning IN', 'from' => '07:00', 'to' => '10:00'],
            ['label' => 'Evening OUT', 'from' => '18:00', 'to' => '20:00'],
        ];
    }

    public static function getRanges(?int $companyId = null): array
    {
        $raw = getSetting('essl_auto_sync_ranges', null, $companyId);

        if ($raw) {
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            if (is_array($decoded) && count($decoded) > 0) {
                return self::normalizeRanges($decoded);
            }
        }

        // Migrate legacy morning/evening point times into short ranges
        $morning = self::normalizeTime(getSetting('essl_auto_sync_morning_time', '08:30', $companyId));
        $evening = self::normalizeTime(getSetting('essl_auto_sync_evening_time', '19:00', $companyId));

        if (getSetting('essl_auto_sync_morning_time', null, $companyId) || getSetting('essl_auto_sync_evening_time', null, $companyId)) {
            return self::normalizeRanges([
                ['label' => 'Morning', 'from' => $morning, 'to' => self::addMinutesToTime($morning, 30)],
                ['label' => 'Evening', 'from' => $evening, 'to' => self::addMinutesToTime($evening, 30)],
            ]);
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
        $interval = self::getIntervalMinutes($companyId);
        $lastRun = getSetting(self::lastRunKey($rangeIndex), null, $companyId);

        if (!$lastRun) {
            return true;
        }

        try {
            return Carbon::parse($lastRun)->diffInMinutes($now) >= $interval;
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
     * @param  array<int, array{label?: string, from?: string, to?: string}>  $ranges
     * @return array<int, array{label: string, from: string, to: string}>
     */
    public static function normalizeRanges(array $ranges): array
    {
        $normalized = [];

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
            ];
        }

        return $normalized ?: self::defaultRanges();
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

    public static function settingsPayload(?int $companyId = null): array
    {
        return [
            'enabled' => self::isEnabled($companyId),
            'ranges' => self::getRanges($companyId),
            'interval_minutes' => self::getIntervalMinutes($companyId),
            'last_run_at' => getSetting('essl_auto_sync_last_run_at', null, $companyId),
            'last_run_slot' => getSetting('essl_auto_sync_last_run_slot', null, $companyId),
        ];
    }
}
