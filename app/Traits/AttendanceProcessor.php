<?php

namespace App\Traits;

use App\Models\BiometricAttendance;
use App\Models\ShiftSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

trait AttendanceProcessor
{
    protected function saveAttendanceRecord($emp, $record)
    {
        $shift = $emp->shift;
        $dateStr = $record['attendance_date'];

        // Protect manually edited records from being overwritten during automatic biometric syncs
        if (!($record['is_manual'] ?? false)) {
            $existing = BiometricAttendance::where('employee_id', $emp->id)
                ->whereDate('attendance_date', $dateStr)
                ->first();
            if ($existing && $existing->is_manual) {
                return $existing;
            }
        }

        // Ensure we are working with Carbon instances
        $inTime = $record['in_time'] ? Carbon::parse($record['in_time']) : null;
        $outTime = $record['out_time'] ? Carbon::parse($record['out_time']) : null;

        // Remove seconds for cleaner calculations
        if ($inTime)
            $inTime->seconds(0);
        if ($outTime)
            $outTime->seconds(0);

        // Night shift: OUT next morning is stored with earlier clock time than IN
        if ($inTime && $outTime && $outTime->lte($inTime)) {
            $outTime->addDay();
        }

        // Keep log_details exactly as synced — all punches visible, no collapsing
        $logDetails = $record['log_details'] ?? null;
        if ($inTime && $outTime && empty($logDetails)) {
            $logDetails = buildLogDetailsFromInOutTimes($inTime, $outTime);
        }

        $isMisPunch = (bool) ($record['is_mis_punch'] ?? false);
        $deferOpenIn = function_exists('shouldDeferOpenInMispunch')
            && shouldDeferOpenInMispunch($emp, $dateStr)
            && function_exists('logDetailsHasOpenIn')
            && logDetailsHasOpenIn($logDetails);

        if ($deferOpenIn) {
            $isMisPunch = false;
        } elseif ($logDetails) {
            $events = parseLogDetailsToEvents($logDetails);
            $inCount = count(array_filter($events, fn($e) => $e['type'] === 'IN'));
            $outCount = count(array_filter($events, fn($e) => $e['type'] === 'OUT'));
            if ($inCount !== $outCount) {
                $isMisPunch = true;
            }
        }

        $shiftCode = $shift?->short_code ?? '---';

        // Safety Fallbacks
        $sInStr = $shift?->start_time ?? '08:00';
        $sOutStr = $shift?->end_time ?? '18:00';
        $breakDur = $shift?->break_duration ?? 0;

        $matchedSlot = null;
        $minDiff = 999999;

        // SHIFT DETECTION
        if ($record['shift_slot_id'] ?? null) {
            // Explicit slot already resolved (e.g. from bio-sync)
            $matchedSlot = ShiftSlot::with('dutyRules')->find($record['shift_slot_id']);
            if ($matchedSlot) {
                $sInStr = $matchedSlot->start_time;
                $sOutStr = $matchedSlot->end_time;
                if ($shift && $shift->is_multi) {
                    $branchPrefix = $emp->branch ? strtoupper(substr($emp->branch->name, 0, 1)) : '';
                    $shiftCode = trim($branchPrefix . ' ' . $matchedSlot->slot_name);
                }
            }
        } elseif ($shift && $shift->is_multi && $inTime) {
            // Multi-shift: detect slot from punch-in time
            $slots = $shift->slots()->with('dutyRules')->get();
            foreach ($slots as $slot) {
                $start = Carbon::parse($dateStr . ' ' . $slot->start_time);
                $end = Carbon::parse($dateStr . ' ' . $slot->end_time);

                if ($end <= $start)
                    $end->addDay();

                $allowedStart = $start->copy()->subMinutes((int) $slot->grace_before_in);
                $allowedEnd = $end->copy()->addMinutes((int) $slot->grace_after_out);

                $diff = abs($inTime->diffInMinutes($start));
                if ($inTime->between($allowedStart, $allowedEnd)) {
                    if ($matchedSlot === null || $diff < $minDiff) {
                        $minDiff = $diff;
                        $matchedSlot = $slot;
                    }
                }
            }

            if ($matchedSlot) {
                $sInStr = $matchedSlot->start_time;
                $sOutStr = $matchedSlot->end_time;
                $branchPrefix = $emp->branch ? strtoupper(substr($emp->branch->name, 0, 1)) : '';
                $shiftCode = trim($branchPrefix . ' ' . $matchedSlot->slot_name);
            }
        } elseif ($shift && !$shift->is_multi) {
            // Fixed shift: load first slot to get its duty rules
            $firstSlot = $shift->slots()->with('dutyRules')->orderBy('priority')->first();
            if ($firstSlot) {
                $matchedSlot = $firstSlot;
                $sInStr = $firstSlot->start_time;
                $sOutStr = $firstSlot->end_time;
            }
        }

        $shiftIn = Carbon::parse($dateStr . ' ' . $sInStr)->seconds(0);
        $shiftOut = Carbon::parse($dateStr . ' ' . $sOutStr)->seconds(0);
        if ($shiftOut->lte($shiftIn))
            $shiftOut->addDay();

        $spanMinutes = ($inTime && $outTime && !$isMisPunch)
            ? max(0, $inTime->diffInMinutes($outTime) - $breakDur)
            : 0;
        $minutesFromLog = sumWorkMinutesFromLogDetails($record['log_details'] ?? null);

        // Multiple IN/OUT pairs: sum each session only (gaps between pairs excluded)
        if (array_key_exists('actual_work_minutes', $record) && $record['actual_work_minutes'] !== null) {
            $totalMinutes = max(0, (int) $record['actual_work_minutes']);
        } elseif ($minutesFromLog !== null) {
            $totalMinutes = $minutesFromLog;
        } else {
            $totalMinutes = $spanMinutes;
        }

        $shiftDuration = max(1, $shiftIn->diffInMinutes($shiftOut));
        $isDoubleShift = ($totalMinutes >= ($shiftDuration * 1.6));

        // Format Combined Shift Code for Multi-Shift Double Shifts
        if ($isDoubleShift && $shift && $shift->is_multi) {
            $slots = $shift->slots;
            $slotNames = [];
            $branchPrefix = $emp->branch ? strtoupper(substr($emp->branch->name, 0, 1)) : '';
            foreach ($slots as $slot) {
                $slotNames[] = trim($branchPrefix . ' ' . $slot->slot_name);
            }
            if (!empty($slotNames)) {
                $shiftCode = implode(', ', $slotNames); // e.g. "KD, KN"
            }
        }

        $lateMin = 0;
        if ($inTime) {
            $diff = $shiftIn->diffInMinutes($inTime, false);
            $lateMin = $diff > 0 ? $diff : 0;
        }

        $earlyMin = 0;
        if ($outTime && !$isDoubleShift) {
            $diff = $outTime->diffInMinutes($shiftOut, false);
            $earlyMin = $diff > 0 ? $diff : 0;
        }

        $otMin = 0;
        if ($outTime) {
            $otBaseline = $isDoubleShift ? $shiftOut->copy()->addMinutes($shiftDuration) : $shiftOut;
            $diff = $otBaseline->diffInMinutes($outTime, false);
            $grossOt = $diff > 0 ? $diff : 0;
            
            // Rule: Subtract Late In duration from Overtime
            $otMin = max(0, $grossOt - $lateMin);
            // Employee minimum OT threshold (e.g. 2 hr) — below minimum, no OT counted
            $otMin = applyEmployeeOtMinimum($emp, $otMin);
        }

        $duty = 0.0;
        $status = $isMisPunch ? 'MIS' : ($record['status'] ?? 'P');
        
        if (!$isMisPunch && $inTime && $outTime) {
            $ruleMatched = false;

            // Double shift always takes priority over per-slot duty rules
            if ($isDoubleShift) {
                $duty = 2.0;
                if (!($record['is_manual'] ?? false)) $status = 'P';
                $ruleMatched = true;
            }

            if (!$ruleMatched && $matchedSlot) {
                // Rule: Use Dynamic Slot Duty Rules (only for single-slot shifts)
                $matchedSlot->loadMissing('dutyRules');
                if ($matchedSlot->dutyRules->isNotEmpty()) {
                    foreach ($matchedSlot->dutyRules as $rule) {
                        if ($totalMinutes >= $rule->min_minutes && $totalMinutes <= $rule->max_minutes) {
                            $duty = (float) $rule->duty_value;
                            if (!($record['is_manual'] ?? false)) {
                                $status = $rule->status;
                            }
                            $ruleMatched = true;
                            break;
                        }
                    }
                }
            }

            if (!$ruleMatched) {
                // Fallback to legacy percentage/hardcoded logic if no dynamic rules are defined
                if ($totalMinutes >= ($shiftDuration * 0.75)) {
                    $duty = 1.0;
                } elseif ($totalMinutes >= ($shiftDuration * 0.50)) {
                    $duty = 0.5;
                    if (!($record['is_manual'] ?? false)) $status = 'HD';
                } else {
                    if (!($record['is_manual'] ?? false)) $status = 'A';
                }
            }
        }

        if (($record['is_manual'] ?? false) && ($record['status'] ?? '') === 'HD') {
            $duty = 0.5;
            $status = 'HD';
        }

        if ($record['is_manual'] ?? false) {
            if (empty($logDetails)) {
                if ($isDoubleShift && $inTime && $outTime) {
                    $midTime = $inTime->copy()->addMinutes($shiftDuration);
                    $logDetails = $inTime->format('H:i') . ' IN, ' . $midTime->format('H:i') . ' OUT, ' . $midTime->format('H:i') . ' IN, ' . $outTime->format('H:i') . ' OUT';
                } elseif ($inTime && $outTime) {
                    $logDetails = buildLogDetailsFromInOutTimes($inTime, $outTime);
                }
            }
        }

        return BiometricAttendance::updateOrCreate(
            ['employee_id' => $emp->id, 'attendance_date' => $dateStr],
            [
                'employee_code' => $emp->emy_code,
                'department_id' => $emp->department_id,
                'branch_id' => $emp->branch_id,
                'category_id' => $emp->category_id,
                'section_id' => $emp->section_id,
                'base_shift' => $emp->shift?->short_code ?? '---',
                'shift_code' => $shiftCode,
                'shift_slot_id' => $matchedSlot?->id,
                'in_time' => $inTime,
                'out_time' => $outTime,
                'in_count' => $record['in_count'] ?? ($inTime ? 1 : 0),
                'out_count' => $record['out_count'] ?? ($outTime ? 1 : 0),
                'punch_count' => ($record['in_count'] ?? ($inTime ? 1 : 0)) + ($record['out_count'] ?? ($outTime ? 1 : 0)),
                'total_minutes' => $totalMinutes,
                'late_in' => $this->formatMinutes($lateMin),
                'early_out' => $this->formatMinutes($earlyMin),
                'ot_minutes' => $otMin,
                'duty_value' => $duty,
                'status' => $status,
                'is_manual' => $record['is_manual'] ?? false,
                'manual_by' => $record['manual_by'] ?? null,
                'manual_remarks' => $record['manual_remarks'] ?? null,
                'log_details' => $logDetails,
            ]
        );
    }

    protected function formatMinutes($minutes)
    {
        if (!$minutes || $minutes <= 0)
            return '0m';
        $h = floor($minutes / 60);
        $m = $minutes % 60;
        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }
}
