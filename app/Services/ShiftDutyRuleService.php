<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\ShiftSlot;
use Carbon\Carbon;

class ShiftDutyRuleService
{
    public static function calculateDurationMinutes(string $startTime, string $endTime): int
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        $minutes = $start->diffInMinutes($end, false);

        if ($minutes <= 0) {
            $minutes += 24 * 60;
        }

        return $minutes;
    }

    public static function buildDutyRulesFromThresholds(int $halfDayMinutes, int $fullDayMinutes): array
    {
        return [
            [
                'rule_name' => 'Absent',
                'min_minutes' => 0,
                'max_minutes' => max(0, $halfDayMinutes - 1),
                'status' => 'A',
                'duty_value' => 0.0,
                'color' => 'red',
                'priority' => 1,
            ],
            [
                'rule_name' => 'Half Day',
                'min_minutes' => $halfDayMinutes,
                'max_minutes' => max($halfDayMinutes, $fullDayMinutes - 1),
                'status' => 'HD',
                'duty_value' => 0.5,
                'color' => 'orange',
                'priority' => 2,
            ],
            [
                'rule_name' => 'Present',
                'min_minutes' => $fullDayMinutes,
                'max_minutes' => 1440,
                'status' => 'P',
                'duty_value' => 1.0,
                'color' => 'green',
                'priority' => 3,
            ],
        ];
    }

    public static function buildDynamicDutyRules(string $startTime, string $endTime): array
    {
        $durationMinutes = self::calculateDurationMinutes($startTime, $endTime);
        $halfDayMinutes = (int) round($durationMinutes * 0.5);
        $fullDayMinutes = (int) round($durationMinutes * 0.75);

        return self::buildDutyRulesFromThresholds($halfDayMinutes, $fullDayMinutes);
    }

    public static function finalizeDutyRules(array $dutyRules, string $startTime, string $endTime): array
    {
        $halfDayRule = collect($dutyRules)->first(fn ($rule) => (float) ($rule['duty_value'] ?? -1) === 0.5);
        $fullDayRule = collect($dutyRules)->first(fn ($rule) => (float) ($rule['duty_value'] ?? -1) === 1.0);

        if (!$halfDayRule || !$fullDayRule) {
            return self::buildDynamicDutyRules($startTime, $endTime);
        }

        $halfDayMinutes = (int) $halfDayRule['min_minutes'];
        $fullDayMinutes = (int) $fullDayRule['min_minutes'];

        if ($halfDayMinutes >= $fullDayMinutes) {
            return self::buildDynamicDutyRules($startTime, $endTime);
        }

        return self::buildDutyRulesFromThresholds($halfDayMinutes, $fullDayMinutes);
    }

    public static function syncSlotDutyRules(ShiftSlot $slot): void
    {
        $rules = self::buildDynamicDutyRules($slot->start_time, $slot->end_time);

        $slot->dutyRules()->delete();

        foreach ($rules as $rule) {
            $slot->dutyRules()->create($rule);
        }
    }

    public static function defaultFixedSlot(string $shortCode): ?array
    {
        $templates = [
            'PD' => ['slot_name' => 'GENERAL', 'start_time' => '08:00:00', 'end_time' => '20:00:00', 'grace_before_in' => 0, 'grace_after_out' => 0],
            'PG' => ['slot_name' => 'GENERAL', 'start_time' => '09:00:00', 'end_time' => '19:00:00', 'grace_before_in' => 0, 'grace_after_out' => 0],
            'PN' => ['slot_name' => 'GENERAL', 'start_time' => '20:00:00', 'end_time' => '08:00:00', 'grace_before_in' => 0, 'grace_after_out' => 0],
            'PP' => ['slot_name' => 'GENERAL', 'start_time' => '09:00:00', 'end_time' => '18:30:00', 'grace_before_in' => 0, 'grace_after_out' => 0],
            'RO' => ['slot_name' => 'GENERAL', 'start_time' => '10:00:00', 'end_time' => '18:30:00', 'grace_before_in' => 0, 'grace_after_out' => 0],
            'B' => ['slot_name' => 'GENERAL', 'start_time' => '10:00:00', 'end_time' => '16:30:00', 'grace_before_in' => 0, 'grace_after_out' => 0],
            'G' => ['slot_name' => 'GENERAL', 'start_time' => '10:00:00', 'end_time' => '19:00:00', 'grace_before_in' => 0, 'grace_after_out' => 0],
            'H' => ['slot_name' => 'GENERAL', 'start_time' => '10:00:00', 'end_time' => '20:00:00', 'grace_before_in' => 0, 'grace_after_out' => 0],
            'HOD' => ['slot_name' => 'GENERAL', 'start_time' => '09:30:00', 'end_time' => '18:30:00', 'grace_before_in' => 0, 'grace_after_out' => 0],
        ];

        return $templates[$shortCode] ?? null;
    }

    public static function defaultMultiSlots(): array
    {
        return [
            ['slot_name' => 'D', 'start_time' => '08:00:00', 'end_time' => '20:00:00', 'grace_before_in' => 0, 'grace_after_out' => 0, 'priority' => 1],
            ['slot_name' => 'N', 'start_time' => '20:00:00', 'end_time' => '08:00:00', 'grace_before_in' => 0, 'grace_after_out' => 0, 'priority' => 2],
        ];
    }

    public static function syncAll(bool $createMissingSlots = true): array
    {
        $stats = [
            'slots_synced' => 0,
            'slots_created' => 0,
            'shifts_skipped' => 0,
        ];

        $shifts = Shift::with(['slots.dutyRules'])->get();

        foreach ($shifts as $shift) {
            if ($shift->slots->isEmpty()) {
                if (!$createMissingSlots) {
                    $stats['shifts_skipped']++;
                    continue;
                }

                $slotTemplates = $shift->is_multi
                    ? self::defaultMultiSlots()
                    : (self::defaultFixedSlot($shift->short_code) ? [self::defaultFixedSlot($shift->short_code)] : []);

                if (empty($slotTemplates)) {
                    $stats['shifts_skipped']++;
                    continue;
                }

                foreach ($slotTemplates as $index => $slotData) {
                    $slotData['priority'] = $slotData['priority'] ?? ($index + 1);
                    $slot = $shift->slots()->create($slotData);
                    self::syncSlotDutyRules($slot);
                    $stats['slots_created']++;
                    $stats['slots_synced']++;
                }

                continue;
            }

            foreach ($shift->slots as $slot) {
                self::syncSlotDutyRules($slot);
                $stats['slots_synced']++;
            }
        }

        return $stats;
    }
}
