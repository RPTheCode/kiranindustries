<?php

use App\Models\Setting;
use App\Models\User;
use App\Models\Coupon;
use Carbon\Carbon;
use App\Models\Plan;
use App\Models\PlanOrder;
use App\Models\Role;
use App\Models\PaymentSetting;
use Illuminate\Support\Facades\Auth;
use App\Models\Permission;

if (!function_exists('getCacheSize')) {
    /**
     * Get the total cache size in MB
     *
     * @return string
     */
    function getCacheSize()
    {
        $file_size = 0;
        $framework_path = storage_path('framework');

        if (is_dir($framework_path)) {
            foreach (\File::allFiles($framework_path) as $file) {
                $file_size += $file->getSize();
            }
        }

        return number_format($file_size / 1000000, 2);
    }
}

if (!function_exists('settings')) {
    function settings($user_id = null)
    {
        // Skip database queries during installation
        if (request()->is('install/*') || request()->is('update/*') || !file_exists(storage_path('installed'))) {
            return [];
        }

        if (is_null($user_id)) {
            if (auth()->user()) {
                if (isSaas()) {
                    if (!in_array(auth()->user()->type, ['superadmin', 'company'])) {
                        $user_id = auth()->user()->created_by;
                    } else {
                        $user_id = auth()->id();
                    }
                } else {
                    // Non-SaaS: Company is top level
                    if (auth()->user()->type === 'company') {
                        $user_id = auth()->id();
                    } else {
                        $user_id = auth()->user()->created_by;
                    }
                }
            } else {
                if (isSaas()) {
                    $user = User::where('type', 'superadmin')->first();
                } else {
                    $user = User::where('type', 'company')->first();
                }
                $user_id = $user ? $user->id : null;
            }
        }

        if (!$user_id) {
            return collect();
        }

        $userSettings = Setting::where('user_id', $user_id)->pluck('value', 'key')->toArray();

        // If user is not superadmin in SaaS mode, merge with superadmin settings for specific keys
        if (isSaas() && auth()->check() && auth()->user()->type !== 'superadmin') {
            $superAdmin = User::where('type', 'superadmin')->first();
            if ($superAdmin) {
                $superAdminKeys = ['decimalFormat', 'defaultCurrency', 'thousandsSeparator', 'floatNumber', 'currencySymbolSpace', 'currencySymbolPosition', 'dateFormat', 'timeFormat', 'calendarStartDay', 'defaultTimezone', 'trusted_domains'];
                $superAdminSettings = Setting::where('user_id', $superAdmin->id)
                    ->whereIn('key', $superAdminKeys)
                    ->pluck('value', 'key')
                    ->toArray();
                $userSettings = array_merge($superAdminSettings, $userSettings);
            }
        }

        return $userSettings;
    }
}

if (!function_exists('formatDateTime')) {
    function formatDateTime($date, $includeTime = true)
    {
        if (!$date) {
            return null;
        }

        $settings = settings();

        $dateFormat = $settings['dateFormat'] ?? 'Y-m-d';
        $timeFormat = $settings['timeFormat'] ?? 'H:i';
        $timezone = $settings['defaultTimezone'] ?? config('app.timezone', 'UTC');

        $format = $includeTime ? "$dateFormat $timeFormat" : $dateFormat;

        return Carbon::parse($date)->timezone($timezone)->format($format);
    }
}

if (!function_exists('brandThemeColor')) {
    /**
     * Brand theme hex color for PDFs/emails (matches UI --theme-color).
     */
    function brandThemeColor($user_id = null): string
    {
        $settings = settings($user_id);
        if (is_array($settings)) {
            $settings = $settings;
        } else {
            $settings = $settings instanceof \Illuminate\Support\Collection ? $settings->toArray() : (array) $settings;
        }

        $map = [
            'blue' => '#3b82f6',
            'brand' => '#1e2978',
            'green' => '#10b981',
            'purple' => '#8b5cf6',
            'orange' => '#f97316',
            'red' => '#ef4444',
        ];

        $theme = $settings['themeColor'] ?? 'brand';
        if ($theme === 'custom' && !empty($settings['customColor'])) {
            return $settings['customColor'];
        }

        return $map[$theme] ?? '#1e2978';
    }
}

if (!function_exists('employeeMinimumOtMinutes')) {
    /**
     * Minimum OT minutes from employees.ot_hours dropdown (per employee: 1, 1.5, 2, 2.5, 3 …).
     * Not a fixed 2-hour rule — whatever value is saved for that employee is used.
     * Returns null when OT is off or no value is selected in the dropdown.
     */
    function employeeMinimumOtMinutes($emp): ?int
    {
        if (!$emp || !($emp->ot_flag ?? false)) {
            return null;
        }

        $raw = trim((string) ($emp->ot_hours ?? ''));
        if ($raw === '') {
            return null;
        }

        if (preg_match('/([\d.]+)/', $raw, $matches)) {
            $hours = (float) $matches[1];
            if ($hours > 0) {
                return (int) round($hours * 60);
            }
        }

        if (is_numeric($raw) && (float) $raw > 0) {
            return (int) round((float) $raw * 60);
        }

        return null;
    }
}

if (!function_exists('employeeHourlyOtEnabled')) {
    /**
     * Hourly OT applies only when OT flag is ON and minimum hours are selected in dropdown.
     */
    function employeeHourlyOtEnabled($emp): bool
    {
        return employeeMinimumOtMinutes($emp) !== null;
    }
}

if (!function_exists('applyEmployeeOtMinimum')) {
    /**
     * Count day's OT only when OT is enabled with a selected minimum and raw minutes meet it.
     */
    function applyEmployeeOtMinimum($emp, int $rawOtMinutes): int
    {
        if ($rawOtMinutes <= 0) {
            return 0;
        }

        $minimum = employeeMinimumOtMinutes($emp);
        if ($minimum === null) {
            return 0;
        }

        return $rawOtMinutes >= $minimum ? $rawOtMinutes : 0;
    }
}

if (!function_exists('employeeShiftHoursForOtRate')) {
    /**
     * Shift duration in hours for deriving hourly OT rate (not employees.ot_hours minimum).
     */
    function employeeShiftHoursForOtRate($emp): float
    {
        $shiftHours = 8.0;

        if (!$emp) {
            return $shiftHours;
        }

        if (!$emp->relationLoaded('shift')) {
            $emp->load('shift.slots');
        }

        if ($emp->shift) {
            if ($emp->shift->working_hours > 0) {
                return max(1, (float) $emp->shift->working_hours);
            }
            if ($emp->shift->slots && $emp->shift->slots->isNotEmpty()) {
                $slot = $emp->shift->slots->first();
                $start = \Carbon\Carbon::parse('2000-01-01 ' . $slot->start_time);
                $end = \Carbon\Carbon::parse('2000-01-01 ' . $slot->end_time);
                if ($end->lte($start)) {
                    $end->addDay();
                }

                return max(1, $start->diffInMinutes($end) / 60);
            }
        }

        return $shiftHours;
    }
}

if (!function_exists('sumWorkMinutesFromLogDetails')) {
    /**
     * Sum worked minutes per IN/OUT pair from log_details.
     * Gaps between pairs (e.g. 10:00–11:00) are NOT included.
     * Example: 08:00-10:00 + 11:00-14:00 + 15:00-20:00 = 600 min (10h), not 08:00-20:00 span.
     */
    function sumWorkMinutesFromLogDetails(?string $logDetails): ?int
    {
        if (!$logDetails || trim($logDetails) === '') {
            return null;
        }

        $pairs = parseLogDetailsToPairs($logDetails);
        $total = 0;
        $completePairs = 0;

        foreach ($pairs as $pair) {
            if (empty($pair['in']) || empty($pair['out'])) {
                continue;
            }
            [$ih, $im] = array_map('intval', explode(':', $pair['in']));
            [$oh, $om] = array_map('intval', explode(':', $pair['out']));
            $diff = ($oh * 60 + $om) - ($ih * 60 + $im);
            if ($diff <= 0) {
                $diff += 24 * 60;
            }
            $total += $diff;
            $completePairs++;
        }

        if ($completePairs > 0) {
            return $total;
        }

        $punches = array_filter(array_map('trim', explode(',', $logDetails)));
        $parsed = [];
        foreach ($punches as $p) {
            $parts = preg_split('/\s+/', trim($p));
            if (count($parts) < 2) {
                continue;
            }
            $time = substr($parts[0], 0, 5);
            $type = strtoupper($parts[1]) === 'OUT' ? 'OUT' : 'IN';
            $parsed[] = ['time' => $time, 'type' => $type];
        }

        $currentIn = null;
        $total = 0;
        $completePairs = 0;

        foreach ($parsed as $p) {
            if ($p['type'] === 'IN') {
                if ($currentIn !== null) {
                    // Previous IN without OUT — skip incomplete
                }
                $currentIn = $p['time'];
            } elseif ($currentIn !== null) {
                [$ih, $im] = array_map('intval', explode(':', $currentIn));
                [$oh, $om] = array_map('intval', explode(':', $p['time']));
                $diff = ($oh * 60 + $om) - ($ih * 60 + $im);
                if ($diff <= 0) {
                    $diff += 24 * 60;
                }
                $total += $diff;
                $completePairs++;
                $currentIn = null;
            }
        }

        return $completePairs > 0 ? $total : null;
    }
}

if (!function_exists('normalizePunchDirection')) {
    function normalizePunchDirection(?string $direction): string
    {
        $dir = strtolower(trim((string) $direction));
        if ($dir === '0' || $dir === 'in') {
            return 'in';
        }
        if ($dir === '1' || $dir === 'out') {
            return 'out';
        }

        return $dir === 'out' ? 'out' : 'in';
    }
}

if (!function_exists('getEmployeeShiftSlots')) {
    function getEmployeeShiftSlots($employee): \Illuminate\Support\Collection
    {
        $shift = $employee->shift ?? null;
        if (!$shift) {
            return collect();
        }

        if ($shift->relationLoaded('slots')) {
            return $shift->slots->sortBy('priority')->values();
        }

        return $shift->slots()->orderBy('priority')->get();
    }
}

if (!function_exists('getEmployeeDayAndNightSlots')) {
    /**
     * @return array{0: ?object, 1: ?object} day slot, night slot
     */
    function getEmployeeDayAndNightSlots($employee): array
    {
        $daySlot = null;
        $nightSlot = null;

        foreach (getEmployeeShiftSlots($employee) as $slot) {
            if (isShiftTimeCrossesMidnight($slot->start_time, $slot->end_time)) {
                $nightSlot = $slot;
            } else {
                $daySlot = $slot;
            }
        }

        return [$daySlot, $nightSlot];
    }
}

if (!function_exists('resolveShiftAttendanceDate')) {
    /**
     * Shift-day key for biometric attendance (session start calendar date).
     * Night / multi: one shift day can span evening → next evening (e.g. 20:00–20:00).
     */
    function resolveShiftAttendanceDate($employee, \Carbon\Carbon $punchTime, string $direction): string
    {
        $direction = normalizePunchDirection($direction);
        $calendarDate = $punchTime->format('Y-m-d');

        if (!$employee || (!employeeShiftSpansMidnight($employee) && !employeeIsMultiShift($employee))) {
            return $calendarDate;
        }

        $timeMins = shiftTimeToMinutes($punchTime->format('H:i'));
        [$daySlot, $nightSlot] = getEmployeeDayAndNightSlots($employee);

        if ($daySlot && $nightSlot && employeeIsMultiShift($employee)) {
            $nightStart = shiftTimeToMinutes($nightSlot->start_time);
            $nightEnd = shiftTimeToMinutes($nightSlot->end_time);
            $dayStart = shiftTimeToMinutes($daySlot->start_time);
            $dayEnd = shiftTimeToMinutes($daySlot->end_time);
            $graceBefore = 120;

            if ($timeMins < $nightEnd) {
                // Morning OUT → previous shift day (night logout). Morning IN → calendar day (day login, e.g. 07:13 on 4 Jun).
                if ($direction === 'out') {
                    $shiftDay = $punchTime->copy()->subDay()->format('Y-m-d');
                } elseif ($timeMins >= ($dayStart - $graceBefore) && $timeMins < $nightEnd) {
                    $shiftDay = $calendarDate;
                } elseif ($direction === 'in') {
                    $shiftDay = $calendarDate;
                } else {
                    $shiftDay = $punchTime->copy()->subDay()->format('Y-m-d');
                }
            } elseif ($timeMins >= $nightStart) {
                $shiftDay = $calendarDate;
            } elseif ($direction === 'out') {
                // Same-day gap OUT (e.g. 13:44 on Jun 2) — stay on calendar date; night close uses IN→OUT pairing
                $shiftDay = $calendarDate;
            } elseif ($timeMins >= ($dayStart - $graceBefore) && $timeMins <= $dayEnd) {
                $shiftDay = $calendarDate;
            } else {
                $shiftDay = $calendarDate;
            }

            return $shiftDay;
        }

        $anchorSlot = $nightSlot;
        if (!$anchorSlot) {
            foreach (getEmployeeShiftSlots($employee) as $slot) {
                if (isShiftTimeCrossesMidnight($slot->start_time, $slot->end_time)) {
                    $anchorSlot = $slot;
                    break;
                }
            }
        }

        $nightStart = $anchorSlot ? shiftTimeToMinutes($anchorSlot->start_time) : 1200;
        $nightEnd = $anchorSlot ? shiftTimeToMinutes($anchorSlot->end_time) : 480;
        $graceBefore = 120;

        if ($timeMins >= $nightStart) {
            $shiftDay = $calendarDate;
        } elseif ($timeMins < $nightEnd) {
            if ($direction === 'out') {
                $shiftDay = $punchTime->copy()->subDay()->format('Y-m-d');
            } elseif ($timeMins >= ($nightEnd - $graceBefore)) {
                $shiftDay = $calendarDate;
            } elseif ($direction === 'in') {
                $shiftDay = $calendarDate;
            } else {
                $shiftDay = $punchTime->copy()->subDay()->format('Y-m-d');
            }
        } else {
            $shiftDay = $calendarDate;
        }

        return $shiftDay;
    }
}

if (!function_exists('getShiftSessionStart')) {
    /** Start of the shift session containing $at. */
    function getShiftSessionStart($employee, \Carbon\Carbon $at): \Carbon\Carbon
    {
        $shiftDay = resolveShiftAttendanceDate($employee, $at, 'in');
        [$daySlot, $nightSlot] = getEmployeeDayAndNightSlots($employee);

        if ($daySlot && $nightSlot && employeeIsMultiShift($employee)) {
            $dayStart = shiftTimeToMinutes($daySlot->start_time);
            $graceBefore = 120;
            $startMins = max(0, $dayStart - $graceBefore);

            return \Carbon\Carbon::parse($shiftDay)->startOfDay()->addMinutes($startMins)->seconds(0);
        }

        if ($nightSlot || employeeShiftSpansMidnight($employee)) {
            $slot = $nightSlot;
            if (!$slot) {
                foreach (getEmployeeShiftSlots($employee) as $s) {
                    if (isShiftTimeCrossesMidnight($s->start_time, $s->end_time)) {
                        $slot = $s;
                        break;
                    }
                }
            }
            $nightStart = $slot ? shiftTimeToMinutes($slot->start_time) : 1200;

            return \Carbon\Carbon::parse($shiftDay)->startOfDay()->addMinutes($nightStart)->seconds(0);
        }

        return \Carbon\Carbon::parse($shiftDay)->startOfDay();
    }
}

if (!function_exists('getShiftSessionEnd')) {
    /**
     * Exclusive end of the shift session containing $at.
     * Open IN is not marked MIS before this time (e.g. next day 20:00 for night shift).
     */
    function getShiftSessionEnd($employee, \Carbon\Carbon $at): \Carbon\Carbon
    {
        $shiftDay = resolveShiftAttendanceDate($employee, $at, 'in');
        [$daySlot, $nightSlot] = getEmployeeDayAndNightSlots($employee);

        if (($daySlot && $nightSlot && employeeIsMultiShift($employee)) || $nightSlot || employeeShiftSpansMidnight($employee)) {
            $slot = $nightSlot;
            if (!$slot) {
                foreach (getEmployeeShiftSlots($employee) as $s) {
                    if (isShiftTimeCrossesMidnight($s->start_time, $s->end_time)) {
                        $slot = $s;
                        break;
                    }
                }
            }
            $nightStart = $slot ? shiftTimeToMinutes($slot->start_time) : 1200;

            return \Carbon\Carbon::parse($shiftDay)->addDay()->startOfDay()->addMinutes($nightStart)->seconds(0);
        }

        return \Carbon\Carbon::parse($shiftDay)->addDay()->startOfDay();
    }
}

if (!function_exists('getShiftAttendanceDateForPunch')) {
    function getShiftAttendanceDateForPunch($employee, \Carbon\Carbon $punchTime, string $direction): string
    {
        return resolveShiftAttendanceDate($employee, $punchTime, $direction);
    }
}

if (!function_exists('assignPunchToAttendanceDate')) {
    /** Assign punch to attendance_date using shift session rules. */
    function assignPunchToAttendanceDate($employee, \Carbon\Carbon $punchTime, string $direction): string
    {
        return getShiftAttendanceDateForPunch($employee, $punchTime, $direction);
    }
}

if (!function_exists('groupEmployeePunchesByAttendanceDate')) {
    /**
     * Pair punches chronologically (IN→OUT), bucket by shift session day.
     *
     * - Rule 1: Open IN closed by next chronological OUT (may be next calendar morning).
     * - Rule 2: IN → next IN without OUT → first IN orphan → mispunch on that shift day.
     * - Night / multi: mid-session pairs (21 IN, 01 OUT, 03 IN, 06 OUT) stay on one shift day.
     *
     * @param  iterable<int, object{log_date: string, direction: string}>  $logs
     * @return array<string, array<int, array{time: \Carbon\Carbon, type: string}>>
     */
    function groupEmployeePunchesByAttendanceDate($employee, iterable $logs): array
    {
        $punches = [];
        foreach ($logs as $log) {
            $punches[] = [
                'time' => \Carbon\Carbon::parse($log->log_date),
                'type' => strtoupper(normalizePunchDirection($log->direction)) === 'OUT' ? 'OUT' : 'IN',
            ];
        }

        usort($punches, fn($a, $b) => $a['time']->timestamp <=> $b['time']->timestamp);

        $byDate = [];
        $append = static function (string $date, \Carbon\Carbon $time, string $type) use (&$byDate): void {
            $byDate[$date][] = ['time' => $time->copy(), 'type' => $type];
        };

        $currentIn = null;
        $currentInDate = null;

        foreach ($punches as $p) {
            if ($p['type'] === 'IN') {
                if ($currentIn !== null) {
                    $append($currentInDate, $currentIn, 'IN');
                }
                $currentIn = $p['time']->copy();
                $currentInDate = getShiftAttendanceDateForPunch($employee, $currentIn, 'in');
                continue;
            }

            if ($currentIn !== null) {
                $append($currentInDate, $currentIn, 'IN');
                $append($currentInDate, $p['time'], 'OUT');
                $currentIn = null;
                $currentInDate = null;
                continue;
            }

            $orphanDate = getShiftAttendanceDateForPunch($employee, $p['time'], 'out');
            $append($orphanDate, $p['time'], 'OUT');
        }

        if ($currentIn !== null) {
            $append($currentInDate, $currentIn, 'IN');
        }

        foreach ($byDate as &$dayPunches) {
            usort($dayPunches, fn($a, $b) => $a['time']->timestamp <=> $b['time']->timestamp);
        }
        unset($dayPunches);

        return $byDate;
    }
}

if (!function_exists('analyzePunchSequence')) {
    /**
     * Evaluate chronological punches — all appear in log_details; mispunch if any pair incomplete.
     *
     * @param  array<int, array{time: \Carbon\Carbon, type: string}>  $punches
     */
    function analyzePunchSequence(array $punches): array
    {
        usort($punches, fn($a, $b) => $a['time']->timestamp <=> $b['time']->timestamp);

        $events = [];
        foreach ($punches as $p) {
            $events[] = [
                'time' => $p['time']->format('H:i'),
                'type' => strtoupper($p['type']) === 'OUT' ? 'OUT' : 'IN',
            ];
        }
        $logDetails = buildLogDetailsFromEvents($events);

        $inCount = count(array_filter($punches, fn($p) => strtoupper($p['type']) === 'IN'));
        $outCount = count(array_filter($punches, fn($p) => strtoupper($p['type']) === 'OUT'));

        $currentIn = null;
        $workMinutes = 0;
        $hasIncomplete = false;

        foreach ($punches as $p) {
            $isIn = strtoupper($p['type']) === 'IN';
            if ($isIn) {
                if ($currentIn !== null) {
                    $hasIncomplete = true;
                }
                $currentIn = $p['time'];
            } else {
                if ($currentIn === null) {
                    $hasIncomplete = true;
                } else {
                    $outCopy = $p['time']->copy();
                    if ($outCopy->lte($currentIn)) {
                        $outCopy->addDay();
                    }
                    $workMinutes += $currentIn->diffInMinutes($outCopy);
                    $currentIn = null;
                }
            }
        }

        if ($currentIn !== null) {
            $hasIncomplete = true;
        }

        $firstIn = null;
        $lastOut = null;
        foreach ($punches as $p) {
            if (strtoupper($p['type']) === 'IN') {
                if ($firstIn === null || $p['time']->lt($firstIn)) {
                    $firstIn = $p['time']->copy();
                }
            } else {
                if ($lastOut === null || $p['time']->gt($lastOut)) {
                    $lastOut = $p['time']->copy();
                }
            }
        }

        $isMisPunch = $hasIncomplete || $inCount !== $outCount;

        return [
            'log_details' => $logDetails,
            'in_count' => $inCount,
            'out_count' => $outCount,
            'work_minutes' => $workMinutes,
            'is_mis_punch' => $isMisPunch,
            'first_in' => $firstIn,
            'last_out' => $lastOut,
        ];
    }
}

if (!function_exists('shiftTimeToMinutes')) {
    function shiftTimeToMinutes(?string $time): int
    {
        if (!$time) {
            return 0;
        }
        $parts = explode(':', substr($time, 0, 5));

        return ((int) ($parts[0] ?? 0)) * 60 + (int) ($parts[1] ?? 0);
    }
}

if (!function_exists('isShiftTimeCrossesMidnight')) {
    function isShiftTimeCrossesMidnight(?string $start, ?string $end): bool
    {
        if (!$start || !$end) {
            return false;
        }

        return shiftTimeToMinutes($start) >= shiftTimeToMinutes($end);
    }
}

if (!function_exists('employeeIsMultiShift')) {
    /** Employee has multiple shift slots (e.g. day + night). */
    function employeeIsMultiShift($employee): bool
    {
        $shift = $employee->shift ?? null;
        if (!$shift) {
            return false;
        }

        return (bool) ($shift->is_multi ?? false);
    }
}

if (!function_exists('employeeShiftSpansMidnight')) {
    /**
     * True when employee shift (or any slot) crosses midnight — night / multi night slot.
     */
    function employeeShiftSpansMidnight($employee): bool
    {
        $shift = $employee->shift ?? null;
        if (!$shift) {
            return false;
        }

        if ($shift->is_night_shift ?? false) {
            return true;
        }

        $slots = $shift->relationLoaded('slots') ? $shift->slots : $shift->slots()->get();
        foreach ($slots as $slot) {
            if (isShiftTimeCrossesMidnight($slot->start_time, $slot->end_time)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('isValidOutForShiftIn')) {
    /**
     * Check OUT belongs to IN using full datetime (handles night shift next-morning OUT).
     */
    function isValidOutForShiftIn($employee, \Carbon\Carbon $in, \Carbon\Carbon $out): bool
    {
        if ($out->gt($in)) {
            return true;
        }

        if ($out->format('Y-m-d') > $in->format('Y-m-d')) {
            return true;
        }

        // Same calendar day: morning OUT before evening IN = previous shift leftover
        if ($out->format('Y-m-d') === $in->format('Y-m-d') && $out->lt($in)) {
            return false;
        }

        return false;
    }
}

if (!function_exists('filterShiftAwareDateRecords')) {
    /**
     * Remove orphan morning OUT rows that belong to the previous shift day.
     */
    function filterShiftAwareDateRecords(iterable $dateRecords, string $attendanceDate, $employee = null): \Illuminate\Support\Collection
    {
        $records = collect($dateRecords);
        $firstIn = $records->filter(fn($dr) => !empty($dr['in_time']))->min('in_time');
        if (!$firstIn) {
            return $records->values();
        }

        $inCarbon = \Carbon\Carbon::parse($firstIn);

        return $records->filter(function ($dr) use ($inCarbon, $attendanceDate, $employee) {
            if (empty($dr['in_time']) && !empty($dr['out_time'])) {
                $out = \Carbon\Carbon::parse($dr['out_time']);
                if ($out->format('Y-m-d') === $attendanceDate && $out->lt($inCarbon)) {
                    return false;
                }
            }

            if (!empty($dr['in_time']) && !empty($dr['out_time'])) {
                return isValidOutForShiftIn(
                    $employee,
                    \Carbon\Carbon::parse($dr['in_time']),
                    \Carbon\Carbon::parse($dr['out_time'])
                );
            }

            return true;
        })->values();
    }
}

if (!function_exists('buildLogDetailsFromInOutTimes')) {
    function buildLogDetailsFromInOutTimes(?\Carbon\Carbon $in, ?\Carbon\Carbon $out): string
    {
        if (!$in || !$out) {
            return '';
        }

        $outDisplay = $out->copy();
        if ($outDisplay->lte($in)) {
            $outDisplay->addDay();
        }

        return $in->format('H:i') . ' IN, ' . $outDisplay->format('H:i') . ' OUT';
    }
}

if (!function_exists('resolveNightShiftAttendanceDate')) {
    /**
     * Night / cross-midnight shift: morning OUT belongs to previous calendar day (IN was previous evening).
     */
    function resolveNightShiftAttendanceDate(\Carbon\Carbon $punchTime, string $direction = 'in', $employee = null): string
    {
        if ($direction === 'out') {
            $hour = (int) $punchTime->format('H');
            $spansMidnight = employeeShiftSpansMidnight($employee);

            if ($spansMidnight && $hour < 14) {
                return $punchTime->copy()->subDay()->format('Y-m-d');
            }

            if (!$spansMidnight && $hour < 12) {
                return $punchTime->copy()->subDay()->format('Y-m-d');
            }
        }

        return $punchTime->format('Y-m-d');
    }
}

if (!function_exists('buildLogDetailsFromPairedRecords')) {
    /**
     * Build log_details from paired IN/OUT records (shift order: IN then OUT per pair).
     */
    function buildLogDetailsFromPairedRecords(iterable $dateRecords): string
    {
        $events = [];
        $sorted = collect($dateRecords)->sortBy(function ($dr) {
            $times = [];
            if (!empty($dr['in_time'])) {
                $times[] = \Carbon\Carbon::parse($dr['in_time'])->timestamp;
            }
            if (!empty($dr['out_time'])) {
                $times[] = \Carbon\Carbon::parse($dr['out_time'])->timestamp;
            }

            return min($times ?: [PHP_INT_MAX]);
        });

        foreach ($sorted as $dr) {
            if (!empty($dr['in_time'])) {
                $events[] = [
                    'time' => \Carbon\Carbon::parse($dr['in_time'])->format('H:i'),
                    'type' => 'IN',
                ];
            }
            if (!empty($dr['out_time'])) {
                $events[] = [
                    'time' => \Carbon\Carbon::parse($dr['out_time'])->format('H:i'),
                    'type' => 'OUT',
                ];
            }
        }

        return buildLogDetailsFromEvents($events);
    }
}

if (!function_exists('parseLogDetailsToEvents')) {
    /**
     * Parse log_details into ordered IN/OUT events.
     */
    function parseLogDetailsToEvents(?string $logDetails): array
    {
        if (!$logDetails || trim($logDetails) === '') {
            return [];
        }

        $events = [];
        foreach (array_filter(array_map('trim', explode(',', $logDetails))) as $p) {
            $parts = preg_split('/\s+/', trim($p));
            if (count($parts) < 1) {
                continue;
            }
            $time = substr($parts[0], 0, 5);
            $type = (isset($parts[1]) && strtoupper($parts[1]) === 'OUT') ? 'OUT' : 'IN';
            if ($time) {
                $events[] = ['time' => $time, 'type' => $type];
            }
        }

        return $events;
    }
}

if (!function_exists('dedupeExactPunchEvents')) {
    /** Only exact duplicate (same time + type) — keep consecutive OUT/IN e.g. two OUTs */
    function dedupeExactPunchEvents(array $events): array
    {
        $result = [];
        foreach ($events as $ev) {
            $last = !empty($result) ? $result[count($result) - 1] : null;
            if ($last && $last['time'] === $ev['time'] && $last['type'] === $ev['type']) {
                continue;
            }
            $result[] = $ev;
        }

        return $result;
    }
}

if (!function_exists('normalizePunchEvents')) {
    /**
     * Remove duplicate punches (same time + type) and collapse consecutive same-direction punches.
     */
    function normalizePunchEvents(array $events): array
    {
        $result = [];
        foreach ($events as $ev) {
            $last = !empty($result) ? $result[count($result) - 1] : null;

            if ($last && $last['time'] === $ev['time'] && $last['type'] === $ev['type']) {
                continue;
            }

            if ($last && $last['type'] === $ev['type']) {
                continue;
            }

            $result[] = $ev;
        }

        return $result;
    }
}

if (!function_exists('buildLogDetailsFromEvents')) {
    function buildLogDetailsFromEvents(array $events): string
    {
        $parts = [];
        foreach ($events as $ev) {
            $parts[] = $ev['time'] . ' ' . $ev['type'];
        }

        return implode(', ', $parts);
    }
}

if (!function_exists('normalizeLogDetails')) {
    function normalizeLogDetails(?string $logDetails): string
    {
        return buildLogDetailsFromEvents(
            normalizePunchEvents(parseLogDetailsToEvents($logDetails))
        );
    }
}

if (!function_exists('parseLogDetailsToPairs')) {
    /**
     * Parse normalized log_details into IN/OUT pairs for forms and reports.
     */
    function parseLogDetailsToPairs(?string $logDetails): array
    {
        $events = dedupeExactPunchEvents(parseLogDetailsToEvents($logDetails));
        $pairs = [];
        $currentIn = '';

        foreach ($events as $ev) {
            if ($ev['type'] === 'IN') {
                if ($currentIn !== '') {
                    $pairs[] = ['in' => $currentIn, 'out' => ''];
                }
                $currentIn = $ev['time'];
            } elseif ($currentIn !== '') {
                $pairs[] = ['in' => $currentIn, 'out' => $ev['time']];
                $currentIn = '';
            } else {
                $pairs[] = ['in' => '', 'out' => $ev['time']];
            }
        }

        if ($currentIn !== '') {
            $pairs[] = ['in' => $currentIn, 'out' => ''];
        }

        return $pairs;
    }
}

if (!function_exists('attendanceLogDetailsMatchEsslOnDate')) {
    /**
     * True when every punch in log_details exists on ESSL for this calendar date (±0 min).
     */
    function attendanceLogDetailsMatchEsslOnDate(iterable $esslLogs, string $calendarDate, string $logDetails): bool
    {
        $events = parseLogDetailsToEvents($logDetails);
        if (empty($events)) {
            return true;
        }

        foreach ($events as $event) {
            $time = substr($event['time'], 0, 5);
            $type = strtoupper($event['type']) === 'OUT' ? 'OUT' : 'IN';
            $matched = false;

            foreach ($esslLogs as $log) {
                $dt = \Carbon\Carbon::parse($log->log_date);
                $logType = strtoupper(normalizePunchDirection($log->direction)) === 'OUT' ? 'OUT' : 'IN';

                if ($dt->format('Y-m-d') === $calendarDate && $dt->format('H:i') === $time && $logType === $type) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('getStoredLogDetailsFromRecord')) {
    /** Raw log_details column — avoid accessor that rebuilds from logs table. */
    function getStoredLogDetailsFromRecord($record): string
    {
        $raw = $record->getAttributes()['log_details'] ?? '';
        if (is_string($raw) && trim($raw) !== '') {
            return trim($raw);
        }

        if ($record->in_time || $record->out_time) {
            $parts = [];
            if ($record->in_time) {
                $parts[] = \Carbon\Carbon::parse($record->in_time)->format('H:i') . ' IN';
            }
            if ($record->out_time) {
                $parts[] = \Carbon\Carbon::parse($record->out_time)->format('H:i') . ' OUT';
            }

            return implode(', ', $parts);
        }

        return '';
    }
}

if (!function_exists('logDetailsHasOpenIn')) {
    function logDetailsHasOpenIn(?string $logDetails): bool
    {
        if (!$logDetails || trim($logDetails) === '') {
            return false;
        }

        $pairs = parseLogDetailsToPairs($logDetails);
        if (empty($pairs)) {
            return false;
        }

        $last = $pairs[array_key_last($pairs)];

        return !empty($last['in']) && empty($last['out']);
    }
}

if (!function_exists('getOpenInCarbonFromLogDetails')) {
    /** Last open IN as Carbon on attendance_date (for session defer). */
    function getOpenInCarbonFromLogDetails(string $attendanceDate, ?string $logDetails): ?\Carbon\Carbon
    {
        if (!$logDetails || !logDetailsHasOpenIn($logDetails)) {
            return null;
        }

        $pairs = parseLogDetailsToPairs($logDetails);
        $last = $pairs[array_key_last($pairs)] ?? null;
        if (empty($last['in'])) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($attendanceDate . ' ' . substr($last['in'], 0, 5));
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('shouldDeferOpenInMispunch')) {
    /**
     * Open IN: defer only while session is open AND no later punch has decided the pair.
     * Rule 2: first punch after open IN is IN (e.g. 3 Jun IN → 4 Jun IN) → not defer → MIS.
     * Rule 1: first punch after open IN is OUT → not defer (sync pairs or report closes).
     */
    function shouldDeferOpenInMispunch($employee, string $attendanceDate, ?array $punchedUserIdsByDate = null, ?\Carbon\Carbon $openInTime = null): bool
    {
        if (!$employee) {
            return false;
        }

        if ($openInTime) {
            $firstAfter = fetchFirstPunchAfterOpenIn(
                $employee,
                $attendanceDate,
                $openInTime->format('H:i'),
                null,
                $openInTime
            );
            if ($firstAfter !== null) {
                return false;
            }
        }

        try {
            $ref = $openInTime
                ? $openInTime->copy()
                : \Carbon\Carbon::parse($attendanceDate)->setTime(12, 0, 0);
        } catch (\Throwable $e) {
            return false;
        }

        if (!employeeShiftSpansMidnight($employee) && !employeeIsMultiShift($employee)) {
            return $attendanceDate === now()->format('Y-m-d');
        }

        return now()->lt(getShiftSessionEnd($employee, $ref));
    }
}

if (!function_exists('esslPunchedUserIdLookupForDate')) {
    /** @return array<string, true> user_id => true for O(1) defer checks */
    function esslPunchedUserIdLookupForDate(string $date): array
    {
        $ids = \App\Models\EsslLog::query()
            ->whereDate('log_date', $date)
            ->distinct()
            ->pluck('user_id');

        return array_fill_keys($ids->all(), true);
    }
}

if (!function_exists('recordIsDeferredOpenInMispunch')) {
    function recordIsDeferredOpenInMispunch($record, ?array $punchedUserIdsByDate = null): bool
    {
        $logDetails = getStoredLogDetailsFromRecord($record);
        if (!logDetailsHasOpenIn($logDetails)) {
            return false;
        }

        $employee = resolveEmployeeForBiometricRecord($record);
        if ($employee && !$employee->relationLoaded('shift')) {
            $employee->load('shift.slots');
        }

        $attDate = \Carbon\Carbon::parse($record->attendance_date)->format('Y-m-d');
        $openIn = getOpenInCarbonFromLogDetails($attDate, $logDetails);

        return shouldDeferOpenInMispunch($employee, $attDate, $punchedUserIdsByDate, $openIn);
    }
}

if (!function_exists('fetchFirstPunchAfterOpenIn')) {
    /**
     * First device punch strictly after open IN, within shift session when $employee is set.
     *
     * @return array{direction: string, time: string, at: \Carbon\Carbon}|null
     */
    function fetchFirstPunchAfterOpenIn(
        $employee,
        string $attendanceDate,
        string $openInTime,
        ?\Carbon\Carbon $untilExclusive = null,
        ?\Carbon\Carbon $openInAt = null
    ): ?array {
        if (!$employee || empty($employee->user_id)) {
            return null;
        }

        try {
            $openIn = $openInAt
                ? $openInAt->copy()
                : \Carbon\Carbon::parse($attendanceDate . ' ' . substr($openInTime, 0, 5));
        } catch (\Throwable $e) {
            return null;
        }

        if ($untilExclusive === null) {
            if (employeeShiftSpansMidnight($employee) || employeeIsMultiShift($employee)) {
                $untilExclusive = getShiftSessionEnd($employee, $openIn);
            } else {
                $untilExclusive = \Carbon\Carbon::parse($attendanceDate)->endOfDay();
            }
        }

        $afterOpenIn = $openIn->copy()->addSecond();

        $query = \App\Models\EsslLog::query()
            ->where('user_id', $employee->user_id)
            ->where('log_date', '>=', $afterOpenIn->format('Y-m-d H:i:s'))
            ->where('log_date', '<', $untilExclusive->format('Y-m-d H:i:s'));

        $firstLog = $query->orderBy('log_date')->first();

        if (!$firstLog) {
            return null;
        }

        $punchDt = \Carbon\Carbon::parse($firstLog->log_date);

        return [
            'direction' => normalizePunchDirection($firstLog->direction),
            'time' => $punchDt->format('H:i'),
            'at' => $punchDt,
        ];
    }
}

if (!function_exists('fetchNextCalendarDayFirstPunch')) {
    /**
     * First device punch on the calendar day after attendance_date (for open IN pairing).
     *
     * @return array{direction: string, time: string}|null
     */
    function fetchNextCalendarDayFirstPunch($employee, string $attendanceDate): ?array
    {
        if (!$employee || empty($employee->user_id)) {
            return null;
        }

        try {
            $nextDayStart = \Carbon\Carbon::parse($attendanceDate)->addDay()->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        $nextDayEnd = $nextDayStart->copy()->endOfDay();

        $firstLog = \App\Models\EsslLog::query()
            ->where('user_id', $employee->user_id)
            ->where('log_date', '>=', $nextDayStart->format('Y-m-d H:i:s'))
            ->where('log_date', '<=', $nextDayEnd->format('Y-m-d H:i:s'))
            ->orderBy('log_date')
            ->first();

        if (!$firstLog) {
            return null;
        }

        $punchDt = \Carbon\Carbon::parse($firstLog->log_date);

        return [
            'direction' => normalizePunchDirection($firstLog->direction),
            'time' => $punchDt->format('H:i'),
        ];
    }
}

if (!function_exists('enrichMispunchPairsForRecord')) {
    /**
     * Before session end: defer open IN. After session end: if first punch after open IN is OUT, close pair.
     * If that punch is IN, leave as mispunch.
     *
     * @param  array<int, array{in: string, out: string}>  $pairs
     * @return array<int, array{in: string, out: string, pending_next_day?: bool}>
     */
    function enrichMispunchPairsForRecord($record, array $pairs): array
    {
        $employee = resolveEmployeeForBiometricRecord($record);
        if ($employee && !$employee->relationLoaded('shift')) {
            $employee->load('shift.slots');
        }

        $attDate = \Carbon\Carbon::parse($record->attendance_date)->format('Y-m-d');

        foreach ($pairs as &$pair) {
            unset($pair['pending_next_day'], $pair['suggested_out']);

            if (empty($pair['in']) || !empty($pair['out'])) {
                continue;
            }

            $openInCarbon = null;
            try {
                $openInCarbon = \Carbon\Carbon::parse($attDate . ' ' . substr($pair['in'], 0, 5));
            } catch (\Throwable $e) {
                // ignore
            }

            if (shouldDeferOpenInMispunch($employee, $attDate, null, $openInCarbon)) {
                $pair['pending_next_day'] = true;
                continue;
            }

            $sessionEnd = $employee && $openInCarbon
                ? getShiftSessionEnd($employee, $openInCarbon)
                : null;
            $firstAfterIn = fetchFirstPunchAfterOpenIn(
                $employee,
                $attDate,
                $pair['in'],
                $sessionEnd,
                $openInCarbon
            );

            if (
                $firstAfterIn
                && $firstAfterIn['direction'] === 'out'
                && !empty($firstAfterIn['at'])
                && (!$employee || resolveShiftAttendanceDate($employee, $firstAfterIn['at'], 'out') === $attDate)
            ) {
                $pair['out'] = $firstAfterIn['time'];
                $pair['suggested_out'] = true;
            }
        }
        unset($pair);

        return $pairs;
    }
}

if (!function_exists('buildLogDetailsFromPairs')) {
    /** @param array<int, array{in?: string, out?: string}> $pairs */
    function buildLogDetailsFromPairs(array $pairs): string
    {
        $events = [];
        foreach ($pairs as $pair) {
            if (!empty($pair['in'])) {
                $events[] = ['time' => $pair['in'], 'type' => 'IN'];
            }
            if (!empty($pair['out'])) {
                $events[] = ['time' => $pair['out'], 'type' => 'OUT'];
            }
        }

        return buildLogDetailsFromEvents($events);
    }
}

if (!function_exists('getMispunchIssuesFromPairs')) {
    /** @param  array<int, array{in: string, out: string}>  $pairs */
    function getMispunchIssuesFromPairs(array $pairs): array
    {
        $issues = [];
        foreach ($pairs as $i => $pair) {
            $n = $i + 1;
            if (empty($pair['in'])) {
                $issues[] = "Pair {$n}: missing IN";
            }
            if (empty($pair['out'])) {
                if (!empty($pair['pending_next_day'])) {
                    continue;
                }
                $issues[] = "Pair {$n}: missing OUT";
            }
            if (
                !empty($pair['in'])
                && !empty($pair['out'])
                && $pair['in'] === $pair['out']
                && empty($pair['suggested_out'])
            ) {
                $issues[] = "Pair {$n}: IN and OUT same time ({$pair['in']})";
            }
        }

        return $issues;
    }
}

if (!function_exists('resolveManualMispunchStatus')) {
    /**
     * Final MIS/P status after HR manual correction — respects incomplete IN/OUT pairs.
     */
    function resolveManualMispunchStatus(?string $logDetails, string $requestedStatus, bool $hasIn, bool $hasOut): string
    {
        if ($logDetails && trim($logDetails) !== '') {
            $normalized = function_exists('normalizeLogDetails') ? normalizeLogDetails($logDetails) : $logDetails;
            $issues = getMispunchIssuesFromPairs(parseLogDetailsToPairs($normalized));
            if (!empty($issues)) {
                return 'MIS';
            }
            if ($hasIn && $hasOut) {
                return 'P';
            }
        }

        if (!$hasIn || !$hasOut) {
            return 'MIS';
        }

        return $requestedStatus === 'MIS' ? 'MIS' : 'P';
    }
}

if (!function_exists('applyMispunchReportDateScope')) {
    /**
     * Exclude today's attendance from mispunch reports — duty may still be in progress.
     */
    function applyMispunchReportDateScope($query)
    {
        return $query->where('attendance_date', '<', now()->format('Y-m-d'));
    }
}

if (!function_exists('buildMispunchPairsForReport')) {
    /**
     * Build structured IN/OUT pairs for mispunch PDF and preview.
     *
     * @return array<int, array{num: int, in: ?string, out: ?string, complete: bool, issue: ?string}>
     */
    function buildMispunchPairsForReport($recordOrLogDetails): array
    {
        if (is_object($recordOrLogDetails)) {
            $record = $recordOrLogDetails;
            $rawLog = getStoredLogDetailsFromRecord($record);
            $events = dedupeExactPunchEvents(parseLogDetailsToEvents($rawLog));
            $logDetails = buildLogDetailsFromEvents($events);
            $pairs = parseLogDetailsToPairs($logDetails);

            if (empty($pairs) && ($record->in_time || $record->out_time)) {
                $pairs[] = [
                    'in' => $record->in_time ? \Carbon\Carbon::parse($record->in_time)->format('H:i') : '',
                    'out' => $record->out_time ? \Carbon\Carbon::parse($record->out_time)->format('H:i') : '',
                ];
            }

            $pairs = enrichMispunchPairsForRecord($record, $pairs);
        } else {
            $pairs = parseLogDetailsToPairs((string) $recordOrLogDetails);
        }

        $result = [];
        foreach ($pairs as $i => $pair) {
            $num = $i + 1;
            $missingIn = empty($pair['in']);
            $missingOut = empty($pair['out']);
            $issue = null;

            if ($missingIn) {
                $issue = 'Missing IN';
            } elseif ($missingOut) {
                $issue = 'Missing OUT';
            } elseif (!empty($pair['in']) && !empty($pair['out']) && $pair['in'] === $pair['out']) {
                $issue = 'Same time';
            }

            $result[] = [
                'num' => $num,
                'in' => $pair['in'] ?: null,
                'out' => $pair['out'] ?: null,
                'complete' => $issue === null,
                'issue' => $issue,
            ];
        }

        return $result;
    }
}

if (!function_exists('resolveEmployeeForBiometricRecord')) {
    /**
     * Load employee for attendance (bypass branch scope; fallback by employee_code).
     */
    function resolveEmployeeForBiometricRecord($record): ?\App\Models\Employee
    {
        if ($record->relationLoaded('employee') && $record->employee) {
            $employee = $record->employee;
            if (!$employee->relationLoaded('shift')) {
                $employee->load('shift.slots');
            }

            return $employee;
        }

        $employee = $record->employee()
            ->with(['user', 'department', 'designation', 'shift.slots'])
            ->first();

        if ($employee) {
            return $employee;
        }

        $code = $record->employee_code ?? null;
        if (!$code) {
            return null;
        }

        return \App\Models\Employee::withoutGlobalScopes()
            ->withTrashed()
            ->with(['user', 'department', 'designation', 'shift.slots'])
            ->where('emy_code', $code)
            ->first();
    }
}

if (!function_exists('getEmployeeDisplayNameForRecord')) {
    function getEmployeeDisplayNameForRecord($record): string
    {
        $employee = resolveEmployeeForBiometricRecord($record);

        return $employee?->user?->name
            ?? $record->employee_code
            ?? '—';
    }
}

if (!function_exists('buildMispunchReportRowFromRecord')) {
    /**
     * Build mispunch PDF row from a biometric attendance record.
     */
    function buildMispunchReportRowFromRecord($record, $employee): array
    {
        $rawLog = getStoredLogDetailsFromRecord($record);
        $events = dedupeExactPunchEvents(parseLogDetailsToEvents($rawLog));
        $logDetails = buildLogDetailsFromEvents($events);
        $pairs = parseLogDetailsToPairs($logDetails);

        if (empty($pairs) && ($record->in_time || $record->out_time)) {
            $pairs[] = [
                'in' => $record->in_time ? \Carbon\Carbon::parse($record->in_time)->format('H:i') : '',
                'out' => $record->out_time ? \Carbon\Carbon::parse($record->out_time)->format('H:i') : '',
            ];
        }

        $pairs = enrichMispunchPairsForRecord($record, $pairs);
        $punchesText = buildLogDetailsFromPairs($pairs) ?: $logDetails ?: 'MISSING';

        $issues = getMispunchIssuesFromPairs($pairs);
        $deferred = recordIsDeferredOpenInMispunch($record);
        $hasIncomplete = !$deferred && !empty($issues);
        $isMultiple = count($pairs) > 1 || $hasIncomplete;

        $firstIn = $record->in_time
            ? \Carbon\Carbon::parse($record->in_time)->format('H:i')
            : ($pairs[0]['in'] ?? '---');
        $lastOut = $record->out_time
            ? \Carbon\Carbon::parse($record->out_time)->format('H:i')
            : ($pairs[count($pairs) - 1]['out'] ?? '---');

        $displayName = $employee->user?->name ?? $record->employee_code ?? 'N/A';

        return [
            'date' => \Carbon\Carbon::parse($record->attendance_date)->format('d/m/Y'),
            'name' => strtoupper($displayName),
            'code' => $employee->emy_code ?? $employee->employee_id ?? $record->employee_code,
            'dept' => strtoupper(optional($employee->department)->name ?? '---'),
            'designation' => strtoupper(optional($employee->designation)->name ?? '---'),
            'is_multiple' => $isMultiple,
            'has_incomplete' => $hasIncomplete,
            'issues' => $issues,
            'issues_text' => !empty($issues) ? implode('; ', $issues) : '',
            'punches_text' => $punchesText,
            'punch_pairs' => $pairs,
            'in_time' => $firstIn ?: '---',
            'out_time' => $lastOut ?: '---',
        ];
    }
}

if (!function_exists('parseAttendanceDurationMinutes')) {
    /**
     * Parse biometric late_in / early_out values (e.g. "15m", "1h 15m", "0m") to total minutes.
     */
    function parseAttendanceDurationMinutes(?string $value): int
    {
        if (!$value || in_array($value, ['0m', '-', 'ON TIME'], true)) {
            return 0;
        }

        $value = trim($value);
        $total = 0;

        if (preg_match('/(\d+)\s*h/i', $value, $hours)) {
            $total += (int) $hours[1] * 60;
        }
        if (preg_match('/(\d+)\s*m/i', $value, $minutes)) {
            $total += (int) $minutes[1];
        }

        return $total;
    }
}

if (!function_exists('passesStatusMinutesThreshold')) {
    /**
     * True when late/early duration is strictly greater than the threshold (e.g. 15 → show only >15 min late).
     */
    function passesStatusMinutesThreshold(string $status, $record, int $thresholdMinutes): bool
    {
        if ($thresholdMinutes <= 0 || !in_array($status, ['latein', 'earlyout'], true)) {
            return true;
        }

        $field = $status === 'latein' ? ($record->late_in ?? null) : ($record->early_out ?? null);
        $mins = parseAttendanceDurationMinutes($field);

        return $mins > $thresholdMinutes;
    }
}

if (!function_exists('pdfCurrencySymbol')) {
    /**
     * Currency symbol safe for DomPDF (built-in fonts lack ₹ and many Unicode glyphs).
     */
    function pdfCurrencySymbol($user_id = null): string
    {
        $settings = settings($user_id);
        $settings = is_array($settings)
            ? $settings
            : ($settings instanceof \Illuminate\Support\Collection ? $settings->toArray() : (array) $settings);

        $code = $settings['defaultCurrency'] ?? 'INR';
        $currency = \App\Models\Currency::where('code', $code)->first();
        $symbol = $currency?->symbol ?? 'Rs.';

        $pdfSafe = [
            '₹' => 'Rs.',
            '₨' => 'Rs.',
            '€' => 'EUR',
            '£' => 'GBP',
            '¥' => 'JPY',
            '₩' => 'KRW',
            '₺' => 'TRY',
            '₽' => 'RUB',
            '₪' => 'ILS',
            '₦' => 'NGN',
            '₱' => 'PHP',
            '₫' => 'VND',
            '₴' => 'UAH',
            '₸' => 'KZT',
            '₼' => 'AZN',
            '₾' => 'GEL',
            '₵' => 'GHS',
            '฿' => 'THB',
            '৳' => 'BDT',
            '₡' => 'CRC',
        ];

        if (isset($pdfSafe[$symbol])) {
            return $pdfSafe[$symbol];
        }

        if (preg_match('/^[\x20-\x7E]+$/', $symbol)) {
            return $symbol;
        }

        return $code === 'INR' ? 'Rs.' : $code . ' ';
    }
}

if (!function_exists('getSetting')) {
    function getSetting($key, $default = null, $user_id = null)
    {
        $settings = settings($user_id);

        // If no value found and no default provided, try to get from defaultSettings
        if (!isset($settings[$key]) && $default === null) {
            $defaultSettings = defaultSettings();
            $default = $defaultSettings[$key] ?? null;
        }

        return $settings[$key] ?? $default;
    }
}

if (!function_exists('updateSetting')) {
    function updateSetting($key, $value, $user_id = null)
    {
        if (is_null($user_id)) {
            if (auth()->user()) {
                if (isSaas()) {
                    if (!in_array(auth()->user()->type, ['superadmin', 'company'])) {
                        $user_id = auth()->user()->created_by;
                    } else {
                        $user_id = auth()->id();
                    }
                } else {
                    // Non-SaaS: Company is top level
                    if (auth()->user()->hasRole('company')) {
                        $user_id = auth()->id();
                    } else {
                        $user_id = auth()->user()->created_by;
                    }
                }
            } else {
                if (isSaas()) {
                    $user = User::where('type', 'superadmin')->first();
                } else {
                    $user = User::where('type', 'company')->first();
                }
                $user_id = $user ? $user->id : null;
            }
        }

        if (!$user_id) {
            return false;
        }

        return Setting::updateOrCreate(
            ['user_id' => $user_id, 'key' => $key],
            ['value' => $value]
        );
    }
}

if (!function_exists('isLandingPageEnabled')) {
    function isLandingPageEnabled()
    {
        return getSetting('landingPageEnabled', true) === true || getSetting('landingPageEnabled', true) === '1';
    }
}

if (!function_exists('defaultRoleAndSetting')) {
    function defaultRoleAndSetting($user)
    {
        $companyRole = Role::where('name', 'company')->first();

        if ($companyRole) {
            $user->assignRole($companyRole);
        }

        // Create default settings for the user
        if ($user->type === 'superadmin') {
            createDefaultSettings($user->id);
        } elseif ($user->type === 'company') {
            copySettingsFromSuperAdmin($user->id);
            $user->companyDefaultData($user);
        }
        return true;
    }
}

if (!function_exists('getPaymentSettings')) {
    /**
     * Get payment settings for a user
     *
     * @param int|null $userId
     * @return array
     */
    function getPaymentSettings($userId = null)
    {
        if (is_null($userId)) {
            if (auth()->check() && auth()->user()->type == 'superadmin') {
                $userId = auth()->id();
            } else {
                $user = User::where('type', 'superadmin')->first();
                $userId = $user ? $user->id : null;
            }
        }

        return PaymentSetting::getUserSettings($userId);
    }
}

if (!function_exists('updatePaymentSetting')) {
    /**
     * Update or create a payment setting
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $userId
     * @return \App\Models\PaymentSetting
     */
    function updatePaymentSetting($key, $value, $userId = null)
    {
        if (is_null($userId)) {
            $userId = auth()->id();
        }

        return PaymentSetting::updateOrCreateSetting($userId, $key, $value);
    }
}

if (!function_exists('isPaymentMethodEnabled')) {
    /**
     * Check if a payment method is enabled
     *
     * @param string $method (stripe, paypal, razorpay, mercadopago, bank)
     * @param int|null $userId
     * @return bool
     */
    function isPaymentMethodEnabled($method, $userId = null)
    {
        $settings = getPaymentSettings($userId);
        $key = "is_{$method}_enabled";

        return isset($settings[$key]) && ($settings[$key] === true || $settings[$key] === '1');
    }
}

if (!function_exists('getPaymentMethodConfig')) {
    /**
     * Get configuration for a specific payment method
     *
     * @param string $method (stripe, paypal, razorpay, mercadopago)
     * @param int|null $userId
     * @return array
     */
    function getPaymentMethodConfig($method, $userId = null)
    {
        $settings = getPaymentSettings($userId);

        switch ($method) {
            case 'stripe':
                return [
                    'enabled' => isPaymentMethodEnabled('stripe', $userId),
                    'key' => $settings['stripe_key'] ?? null,
                    'secret' => $settings['stripe_secret'] ?? null,
                ];

            case 'paypal':
                return [
                    'enabled' => isPaymentMethodEnabled('paypal', $userId),
                    'mode' => $settings['paypal_mode'] ?? 'sandbox',
                    'client_id' => $settings['paypal_client_id'] ?? null,
                    'secret' => $settings['paypal_secret_key'] ?? null,
                ];

            case 'razorpay':
                return [
                    'enabled' => isPaymentMethodEnabled('razorpay', $userId),
                    'key' => $settings['razorpay_key'] ?? null,
                    'secret' => $settings['razorpay_secret'] ?? null,
                ];

            case 'mercadopago':
                return [
                    'enabled' => isPaymentMethodEnabled('mercadopago', $userId),
                    'mode' => $settings['mercadopago_mode'] ?? 'sandbox',
                    'access_token' => $settings['mercadopago_access_token'] ?? null,
                ];

            case 'paystack':
                return [
                    'enabled' => isPaymentMethodEnabled('paystack', $userId),
                    'public_key' => $settings['paystack_public_key'] ?? null,
                    'secret_key' => $settings['paystack_secret_key'] ?? null,
                ];

            case 'flutterwave':
                return [
                    'enabled' => isPaymentMethodEnabled('flutterwave', $userId),
                    'public_key' => $settings['flutterwave_public_key'] ?? null,
                    'secret_key' => $settings['flutterwave_secret_key'] ?? null,
                ];

            case 'bank':
                return [
                    'enabled' => isPaymentMethodEnabled('bank', $userId),
                    'details' => $settings['bank_detail'] ?? null,
                ];

            case 'paytabs':
                return [
                    'enabled' => isPaymentMethodEnabled('paytabs', $userId),
                    'mode' => $settings['paytabs_mode'] ?? 'sandbox',
                    'profile_id' => $settings['paytabs_profile_id'] ?? null,
                    'server_key' => $settings['paytabs_server_key'] ?? null,
                    'region' => $settings['paytabs_region'] ?? 'ARE',
                ];

            case 'skrill':
                return [
                    'enabled' => isPaymentMethodEnabled('skrill', $userId),
                    'merchant_id' => $settings['skrill_merchant_id'] ?? null,
                    'secret_word' => $settings['skrill_secret_word'] ?? null,
                ];

            case 'coingate':
                return [
                    'enabled' => isPaymentMethodEnabled('coingate', $userId),
                    'mode' => $settings['coingate_mode'] ?? 'sandbox',
                    'api_token' => $settings['coingate_api_token'] ?? null,
                ];

            case 'payfast':
                return [
                    'enabled' => isPaymentMethodEnabled('payfast', $userId),
                    'mode' => $settings['payfast_mode'] ?? 'sandbox',
                    'merchant_id' => $settings['payfast_merchant_id'] ?? null,
                    'merchant_key' => $settings['payfast_merchant_key'] ?? null,
                    'passphrase' => $settings['payfast_passphrase'] ?? null,
                ];

            case 'tap':
                return [
                    'enabled' => isPaymentMethodEnabled('tap', $userId),
                    'secret_key' => $settings['tap_secret_key'] ?? null,
                ];

            case 'xendit':
                return [
                    'enabled' => isPaymentMethodEnabled('xendit', $userId),
                    'api_key' => $settings['xendit_api_key'] ?? null,
                ];

            case 'paytr':
                return [
                    'enabled' => isPaymentMethodEnabled('paytr', $userId),
                    'merchant_id' => $settings['paytr_merchant_id'] ?? null,
                    'merchant_key' => $settings['paytr_merchant_key'] ?? null,
                    'merchant_salt' => $settings['paytr_merchant_salt'] ?? null,
                ];

            case 'mollie':
                return [
                    'enabled' => isPaymentMethodEnabled('mollie', $userId),
                    'api_key' => $settings['mollie_api_key'] ?? null,
                ];

            case 'toyyibpay':
                return [
                    'enabled' => isPaymentMethodEnabled('toyyibpay', $userId),
                    'category_code' => $settings['toyyibpay_category_code'] ?? null,
                    'secret_key' => $settings['toyyibpay_secret_key'] ?? null,
                    'mode' => $settings['toyyibpay_mode'] ?? 'sandbox',
                ];

            case 'cashfree':
                return [
                    'enabled' => isPaymentMethodEnabled('cashfree', $userId),
                    'mode' => $settings['cashfree_mode'] ?? 'sandbox',
                    'public_key' => $settings['cashfree_public_key'] ?? null,
                    'secret_key' => $settings['cashfree_secret_key'] ?? null,
                ];

            case 'iyzipay':
                return [
                    'enabled' => isPaymentMethodEnabled('iyzipay', $userId),
                    'mode' => $settings['iyzipay_mode'] ?? 'sandbox',
                    'public_key' => $settings['iyzipay_public_key'] ?? null,
                    'secret_key' => $settings['iyzipay_secret_key'] ?? null,
                ];

            case 'benefit':
                return [
                    'enabled' => isPaymentMethodEnabled('benefit', $userId),
                    'mode' => $settings['benefit_mode'] ?? 'sandbox',
                    'public_key' => $settings['benefit_public_key'] ?? null,
                    'secret_key' => $settings['benefit_secret_key'] ?? null,
                ];

            case 'ozow':
                return [
                    'enabled' => isPaymentMethodEnabled('ozow', $userId),
                    'mode' => $settings['ozow_mode'] ?? 'sandbox',
                    'site_key' => $settings['ozow_site_key'] ?? null,
                    'private_key' => $settings['ozow_private_key'] ?? null,
                    'api_key' => $settings['ozow_api_key'] ?? null,
                ];

            case 'easebuzz':
                return [
                    'enabled' => isPaymentMethodEnabled('easebuzz', $userId),
                    'merchant_key' => $settings['easebuzz_merchant_key'] ?? null,
                    'salt_key' => $settings['easebuzz_salt_key'] ?? null,
                    'environment' => $settings['easebuzz_environment'] ?? 'test',
                ];

            case 'khalti':
                return [
                    'enabled' => isPaymentMethodEnabled('khalti', $userId),
                    'public_key' => $settings['khalti_public_key'] ?? null,
                    'secret_key' => $settings['khalti_secret_key'] ?? null,
                ];

            case 'authorizenet':
                return [
                    'enabled' => isPaymentMethodEnabled('authorizenet', $userId),
                    'mode' => $settings['authorizenet_mode'] ?? 'sandbox',
                    'merchant_id' => $settings['authorizenet_merchant_id'] ?? null,
                    'transaction_key' => $settings['authorizenet_transaction_key'] ?? null,
                    'supported_countries' => ['US', 'CA', 'GB', 'AU'],
                    'supported_currencies' => ['USD', 'CAD', 'CHF', 'DKK', 'EUR', 'GBP', 'NOK', 'PLN', 'SEK', 'AUD', 'NZD'],
                ];

            case 'fedapay':
                return [
                    'enabled' => isPaymentMethodEnabled('fedapay', $userId),
                    'mode' => $settings['fedapay_mode'] ?? 'sandbox',
                    'public_key' => $settings['fedapay_public_key'] ?? null,
                    'secret_key' => $settings['fedapay_secret_key'] ?? null,
                ];

            case 'payhere':
                return [
                    'enabled' => isPaymentMethodEnabled('payhere', $userId),
                    'mode' => $settings['payhere_mode'] ?? 'sandbox',
                    'merchant_id' => $settings['payhere_merchant_id'] ?? null,
                    'merchant_secret' => $settings['payhere_merchant_secret'] ?? null,
                    'app_id' => $settings['payhere_app_id'] ?? null,
                    'app_secret' => $settings['payhere_app_secret'] ?? null,
                ];

            case 'cinetpay':
                return [
                    'enabled' => isPaymentMethodEnabled('cinetpay', $userId),
                    'site_id' => $settings['cinetpay_site_id'] ?? null,
                    'api_key' => $settings['cinetpay_api_key'] ?? null,
                    'secret_key' => $settings['cinetpay_secret_key'] ?? null,
                ];

            case 'paymentwall':
                return [
                    'enabled' => isPaymentMethodEnabled('paymentwall', $userId),
                    'mode' => $settings['paymentwall_mode'] ?? 'sandbox',
                    'public_key' => $settings['paymentwall_public_key'] ?? null,
                    'private_key' => $settings['paymentwall_private_key'] ?? null,
                ];

            default:
                return [];
        }
    }
}

if (!function_exists('getEnabledPaymentMethods')) {
    /**
     * Get all enabled payment methods
     *
     * @param int|null $userId
     * @return array
     */
    function getEnabledPaymentMethods($userId = null)
    {
        $methods = ['stripe', 'paypal', 'razorpay', 'mercadopago', 'paystack', 'flutterwave', 'bank', 'paytabs', 'skrill', 'coingate', 'payfast', 'tap', 'xendit', 'paytr', 'mollie', 'toyyibpay', 'cashfree', 'iyzipay', 'benefit', 'ozow', 'easebuzz', 'khalti', 'authorizenet', 'fedapay', 'payhere', 'cinetpay', 'paymentwall'];
        $enabled = [];

        foreach ($methods as $method) {
            if (isPaymentMethodEnabled($method, $userId)) {
                $enabled[$method] = getPaymentMethodConfig($method, $userId);
            }
        }

        return $enabled;
    }
}

if (!function_exists('validatePaymentMethodConfig')) {
    /**
     * Validate payment method configuration
     *
     * @param string $method
     * @param array $config
     * @return array [valid => bool, errors => array]
     */
    function validatePaymentMethodConfig($method, $config)
    {
        $errors = [];

        switch ($method) {
            case 'stripe':
                if (empty($config['key'])) {
                    $errors[] = 'Stripe publishable key is required';
                }
                if (empty($config['secret'])) {
                    $errors[] = 'Stripe secret key is required';
                }
                break;

            case 'paypal':
                if (empty($config['client_id'])) {
                    $errors[] = 'PayPal client ID is required';
                }
                if (empty($config['secret'])) {
                    $errors[] = 'PayPal secret key is required';
                }
                break;

            case 'razorpay':
                if (empty($config['key'])) {
                    $errors[] = 'Razorpay key ID is required';
                }
                if (empty($config['secret'])) {
                    $errors[] = 'Razorpay secret key is required';
                }
                break;

            case 'mercadopago':
                if (empty($config['access_token'])) {
                    $errors[] = 'MercadoPago access token is required';
                }
                break;

            case 'bank':
                if (empty($config['details'])) {
                    $errors[] = 'Bank details are required';
                }
                break;

            case 'paytabs':
                if (empty($config['server_key'])) {
                    $errors[] = 'PayTabs server key is required';
                }
                if (empty($config['profile_id'])) {
                    $errors[] = 'PayTabs profile id is required';
                }
                if (empty($config['region'])) {
                    $errors[] = 'PayTabs region is required';
                }
                break;

            case 'skrill':
                if (empty($config['merchant_id'])) {
                    $errors[] = 'Skrill merchant ID is required';
                }
                if (empty($config['secret_word'])) {
                    $errors[] = 'Skrill secret word is required';
                }
                break;

            case 'coingate':
                if (empty($config['api_token'])) {
                    $errors[] = 'CoinGate API token is required';
                }
                break;

            case 'payfast':
                if (empty($config['merchant_id'])) {
                    $errors[] = 'Payfast merchant ID is required';
                }
                if (empty($config['merchant_key'])) {
                    $errors[] = 'Payfast merchant key is required';
                }
                break;

            case 'tap':
                if (empty($config['secret_key'])) {
                    $errors[] = 'Tap secret key is required';
                }
                break;

            case 'xendit':
                if (empty($config['api_key'])) {
                    $errors[] = 'Xendit api key is required';
                }
                break;

            case 'paytr':
                if (empty($config['merchant_id'])) {
                    $errors[] = 'PayTR merchant ID is required';
                }
                if (empty($config['merchant_key'])) {
                    $errors[] = 'PayTR merchant key is required';
                }
                if (empty($config['merchant_salt'])) {
                    $errors[] = 'PayTR merchant salt is required';
                }
                break;

            case 'mollie':
                if (empty($config['api_key'])) {
                    $errors[] = 'Mollie API key is required';
                }
                break;

            case 'toyyibpay':
                if (empty($config['category_code'])) {
                    $errors[] = 'toyyibPay category code is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'toyyibPay secret key is required';
                }
                break;

            case 'cashfree':
                if (empty($config['public_key'])) {
                    $errors[] = 'Cashfree App ID is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'Cashfree Secret Key is required';
                }
                break;

            case 'iyzipay':
                if (empty($config['public_key'])) {
                    $errors[] = 'Iyzipay API key is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'Iyzipay secret key is required';
                }
                break;

            case 'benefit':
                if (empty($config['public_key'])) {
                    $errors[] = 'Benefit API key is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'Benefit secret key is required';
                }
                break;

            case 'ozow':
                if (empty($config['site_key'])) {
                    $errors[] = 'Ozow site key is required';
                }
                if (empty($config['private_key'])) {
                    $errors[] = 'Ozow private key is required';
                }
                break;

            case 'easebuzz':
                if (empty($config['merchant_key'])) {
                    $errors[] = 'Easebuzz merchant key is required';
                }
                if (empty($config['salt_key'])) {
                    $errors[] = 'Easebuzz salt key is required';
                }
                break;

            case 'khalti':
                if (empty($config['public_key'])) {
                    $errors[] = 'Khalti public key is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'Khalti secret key is required';
                }
                break;

            case 'authorizenet':
                if (empty($config['merchant_id'])) {
                    $errors[] = 'AuthorizeNet merchant ID is required';
                }
                if (empty($config['transaction_key'])) {
                    $errors[] = 'AuthorizeNet transaction key is required';
                }
                break;

            case 'fedapay':
                if (empty($config['public_key'])) {
                    $errors[] = 'FedaPay public key is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'FedaPay secret key is required';
                }
                break;

            case 'payhere':
                if (empty($config['merchant_id'])) {
                    $errors[] = 'PayHere merchant ID is required';
                }
                if (empty($config['merchant_secret'])) {
                    $errors[] = 'PayHere merchant secret is required';
                }
                break;

            case 'cinetpay':
                if (empty($config['site_id'])) {
                    $errors[] = 'CinetPay site ID is required';
                }
                if (empty($config['api_key'])) {
                    $errors[] = 'CinetPay API key is required';
                }
                break;

            case 'paiement':
                if (empty($config['merchant_id'])) {
                    $errors[] = 'Paiement Pro merchant ID is required';
                }
                break;

            case 'nepalste':
                if (empty($config['public_key'])) {
                    $errors[] = 'Nepalste public key is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'Nepalste secret key is required';
                }
                break;

            case 'yookassa':
                if (empty($config['shop_id'])) {
                    $errors[] = 'YooKassa shop ID is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'YooKassa secret key is required';
                }
                break;

            case 'midtrans':
                if (empty($config['secret_key'])) {
                    $errors[] = 'Midtrans secret key is required';
                }
                break;

            case 'aamarpay':
                if (empty($config['store_id'])) {
                    $errors[] = 'Aamarpay store ID is required';
                }
                if (empty($config['signature'])) {
                    $errors[] = 'Aamarpay signature is required';
                }
                break;

            case 'paymentwall':
                if (empty($config['public_key'])) {
                    $errors[] = 'PaymentWall public key is required';
                }
                if (empty($config['private_key'])) {
                    $errors[] = 'PaymentWall private key is required';
                }
                break;

            case 'sspay':
                if (empty($config['secret_key'])) {
                    $errors[] = 'SSPay secret key is required';
                }
                break;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

if (!function_exists('calculatePlanPricing')) {
    function calculatePlanPricing($plan, $couponCode = null, $billingCycle = 'monthly')
    {
        // $originalPrice = $plan->price;
        $originalPrice = $plan->getPriceForCycle($billingCycle);
        $discountAmount = 0;
        $finalPrice = $originalPrice;
        $couponId = null;

        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)
                ->where('status', 1)
                ->first();

            if ($coupon) {
                if ($coupon->type === 'percentage') {
                    $discountAmount = ($originalPrice * $coupon->discount_amount) / 100;
                } else {
                    $discountAmount = min($coupon->discount_amount, $originalPrice);
                }
                $finalPrice = max(0, $originalPrice - $discountAmount);
                $couponId = $coupon->id;
            }
        }

        return [
            'original_price' => $originalPrice,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'coupon_id' => $couponId
        ];
    }
}

if (!function_exists('createPlanOrder')) {
    function createPlanOrder($data)
    {
        $plan = Plan::findOrFail($data['plan_id']);
        $pricing = calculatePlanPricing($plan, $data['coupon_code'] ?? null, $data['billing_cycle'] ?? 'monthly');


        return PlanOrder::create([
            'user_id' => $data['user_id'],
            'plan_id' => $plan->id,
            'coupon_id' => $pricing['coupon_id'],
            'billing_cycle' => $data['billing_cycle'],
            'payment_method' => $data['payment_method'],
            'coupon_code' => $data['coupon_code'] ?? null,
            'original_price' => $pricing['original_price'],
            'discount_amount' => $pricing['discount_amount'],
            'final_price' => $pricing['final_price'],
            'payment_id' => $data['payment_id'],
            'status' => $data['status'] ?? 'pending',
            'ordered_at' => now(),
        ]);
    }
}

if (!function_exists('assignPlanToUser')) {
    function assignPlanToUser($user, $plan, $billingCycle)
    {
        $expiresAt = $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth();

        \Log::info('Assigning plan ' . $plan->id . ' to user ' . $user->id . ' with billing cycle ' . $billingCycle);

        $updated = $user->update([
            'plan_id' => $plan->id,
            'plan_expire_date' => $expiresAt,
            'plan_is_active' => 1,
        ]);

        \Log::info('Plan assignment result: ' . ($updated ? 'success' : 'failed'));
    }
}

if (!function_exists('processPaymentSuccess')) {
    function processPaymentSuccess($data)
    {
        $plan = Plan::findOrFail($data['plan_id']);
        $user = User::findOrFail($data['user_id']);

        $planOrder = createPlanOrder(array_merge($data, ['status' => 'approved']));
        assignPlanToUser($user, $plan, $data['billing_cycle']);

        // Verify the plan was assigned
        $user->refresh();

        // Create referral record if user was referred
        \App\Http\Controllers\ReferralController::createReferralRecord($user);

        return $planOrder;
    }
}

if (!function_exists('getPaymentGatewaySettings')) {
    function getPaymentGatewaySettings()
    {
        $superAdminId = User::where('type', 'superadmin')->first()?->id;

        return [
            'payment_settings' => PaymentSetting::getUserSettings($superAdminId),
            'general_settings' => Setting::getUserSettings($superAdminId),
            'super_admin_id' => $superAdminId
        ];
    }
}

if (!function_exists('validatePaymentRequest')) {
    function validatePaymentRequest($request, $additionalRules = [])
    {
        $baseRules = [
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'coupon_code' => 'nullable|string',
        ];

        return $request->validate(array_merge($baseRules, $additionalRules));
    }
}

if (!function_exists('handlePaymentError')) {
    function handlePaymentError($e, $method = 'payment')
    {
        return back()->withErrors(['error' => __('Payment processing failed: :message', ['message' => $e->getMessage()])]);
    }
}

if (!function_exists('defaultSettings')) {
    /**
     * Get default settings for System, Brand, Storage, and Currency configurations
     *
     * @return array
     */
    function defaultSettings()
    {
        $settings = [
            // System Settings
            'defaultLanguage' => 'en',
            'dateFormat' => 'Y-m-d',
            'timeFormat' => 'H:i',
            'calendarStartDay' => 'sunday',
            'defaultTimezone' => 'UTC',
            'emailVerification' => false,
            'landingPageEnabled' => true,
            'trusted_domains' => '',

            // Brand Settings
            'logoDark' => 'logo/logo-dark.png',
            'logoLight' => 'logo/logo-light.png',
            'favicon' => 'logo/favicon.ico',
            'titleText' => isSaas() ? 'HRM SaaS' : 'HRM',
            'footerText' => isSaas() ? '© 2024 HRM SaaS. All rights reserved.' : '© 2024 HRM. All rights reserved.',
            'themeColor' => 'green',
            'customColor' => '#10b981',
            'sidebarVariant' => 'inset',
            'sidebarStyle' => 'plain',
            'layoutDirection' => 'left',
            'themeMode' => 'light',

            // Storage Settings
            'storage_type' => 'local',
            'storage_file_types' => 'jpg,png,webp,gif,pdf,doc,docx,txt,csv',
            'storage_max_upload_size' => '2048',
            'aws_access_key_id' => '',
            'aws_secret_access_key' => '',
            'aws_default_region' => 'us-east-1',
            'aws_bucket' => '',
            'aws_url' => '',
            'aws_endpoint' => '',
            'wasabi_access_key' => '',
            'wasabi_secret_key' => '',
            'wasabi_region' => 'us-east-1',
            'wasabi_bucket' => '',
            'wasabi_url' => '',
            'wasabi_root' => '',

            // Currency Settings
            'decimalFormat' => '2',
            'defaultCurrency' => 'USD',
            'decimalSeparator' => '.',
            'thousandsSeparator' => ',',
            'floatNumber' => true,
            'currencySymbolSpace' => false,
            'currencySymbolPosition' => 'before',
        ];

        if (isDemo()) {
            $cookieSettingArray = [
                'enableLogging' => true,
                'strictlyNecessaryCookies' => true,
                'cookieTitle' => 'Cookie Consent',
                'strictlyCookieTitle' => 'Strictly Necessary Cookies',
                'cookieDescription' => 'We use cookies to enhance your browsing experience and provide personalized content.',
                'strictlyCookieDescription' => 'These cookies are essential for the website to function properly.',
                'contactUsDescription' => 'If you have any questions about our cookie policy, please contact us.',
                'contactUsUrl' => 'https://example.com/contact',
            ];
            $settings = array_merge($settings, $cookieSettingArray);
        }
        return $settings;
    }
}

if (!function_exists('createDefaultSettings')) {
    /**
     * Create default settings for a user
     *
     * @param int $userId
     * @return void
     */
    function createDefaultSettings($userId)
    {
        $defaults = defaultSettings();
        $settingsData = [];

        foreach ($defaults as $key => $value) {
            $settingsData[] = [
                'user_id' => $userId,
                'key' => $key,
                'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Setting::insert($settingsData);
    }
}

if (!function_exists('copySettingsFromSuperAdmin')) {
    /**
     * Copy system and brand settings from superadmin to company user
     *
     * @param int $companyUserId
     * @return void
     */
    function copySettingsFromSuperAdmin($companyUserId)
    {
        // $superAdmin = User::where('type', 'superadmin')->first();
        // if (!$superAdmin) {
        //     createDefaultSettings($companyUserId);
        //     return;
        // }

        if (isSaas()) {
            $superAdmin = User::where('type', 'superadmin')->first();
            if (!$superAdmin) {
                createDefaultSettings($companyUserId);
                return;
            }
        } else {
            // Non-SaaS: Create default settings directly
            createDefaultSettings($companyUserId);
            return;
        }

        // Settings to copy from superadmin (system and brand settings only)
        $settingsToCopy = [
            'defaultLanguage',
            'dateFormat',
            'timeFormat',
            'calendarStartDay',
            'defaultTimezone',
            'emailVerification',
            'landingPageEnabled',
            'logoDark',
            'logoLight',
            'favicon',
            'titleText',
            'footerText',
            'themeColor',
            'customColor',
            'sidebarVariant',
            'sidebarStyle',
            'layoutDirection',
            'themeMode'
        ];

        $superAdminSettings = Setting::where('user_id', $superAdmin->id)
            ->whereIn('key', $settingsToCopy)
            ->get();

        $settingsData = [];

        // Only copy existing superadmin settings
        foreach ($superAdminSettings as $setting) {
            $settingsData[] = [
                'user_id' => $companyUserId,
                'key' => $setting->key,
                'value' => $setting->value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Setting::insertOrIgnore($settingsData);
    }
}

if (!function_exists('createdBy')) {
    function createdBy()
    {
        if (Auth::user()->type == 'superadmin') {
            return Auth::user()->id;
        } else if (Auth::user()->type == 'company') {
            return Auth::user()->id;
        } else {
            return Auth::user()->created_by;
        }
    }
}


if (!function_exists('creatorId')) {
    function creatorId()
    {
        return Auth::user()->id;
    }
}


if (!function_exists('getCompanyAndUsersId')) {
    function getCompanyAndUsersId()
    {
        $user = Auth::user();
        if ($user->type === 'company' || $user->hasRole(['company'])) {
            $companyUserIds = User::where('created_by', $user->id)->pluck('id')->toArray();
            $companyUserIds[] = $user->id;

            return $companyUserIds;
        }

        $userCreatedBy = User::where('id', Auth::user()->created_by)->value('id');
        $companyUserIds = User::where('created_by', $userCreatedBy)->pluck('id')->toArray();
        $companyUserIds[] = $userCreatedBy;

        return $companyUserIds;
    }
}

// Get Image URL Path
if (!function_exists('getImageUrlPrefix')) {
    function getImageUrlPrefix(): string
    {
        $settings = settings();
        $storageType = $settings['storage_type'] ?? 'local';
        switch ($storageType) {
            case 's3':
            case 'aws_s3':
                $endpoint = $settings['aws_endpoint'] ?? null;
                if ($endpoint) {
                    return rtrim($endpoint, '/') . '/media/';
                }
                $bucket = $settings['aws_bucket'] ?? '';
                $region = $settings['aws_default_region'] ?? 'us-east-1';
                return "https://{$bucket}.s3.{$region}.amazonaws.com/media/";

            case 'wasabi':
                $url = $settings['wasabi_url'] ?? null;
                return $url ? rtrim($url, '/') . '/media/' : url('/storage/media/');

            case 'local':
            default:
                return url('/storage/media/');
        }
    }
}

// Get Company and User
if (!function_exists('getUser')) {
    function getUser()
    {
        $autheUser = Auth::user();
        if ($autheUser->hasRole('superadmin')) {
            return $autheUser;
        } else if ($autheUser->hasRole('company')) {
            return $autheUser;
        } else {
            $company = User::where('id', $autheUser->created_by)->first();
            return $company;
        }
    }
}

if (!function_exists('getStorageFilePath')) {
    /**
     * Get storage file path for downloads
     */
    function getStorageFilePath($filename)
    {
        if (empty($filename)) {
            return null;
        }

        // Remove any path separators to ensure only filename
        $filename = basename($filename);

        return storage_path('app/public/media/' . $filename);
    }
}

if (!function_exists('randomImage')) {
    function randomImage()
    {
        if (isSaas() && isDemo()) {
            $images = [
                'apex-industries-building-exterior.png',
                'apex-industries-business-card.png',
                'default-avatar.png',
                'global-systems-inc-social-banner.png',
                'apex-industries-logo.png',
                'phoenix-corporation-team-photo.png',
                'stellar-enterprises-social-banner.png',
                'techcorp-solutions-office-photo.png',
                'vortex-systems-building-exterior.png',
                'vortex-systems-business-card.png',
                'techcorp-solutions-business-card.png',
                'quantum-dynamics-office-photo.png',
                'phoenix-corporation-building-exterior.png',
                'infinity-solutions-office-photo.png',
                'nexus-technologies-business-card.png',
                'loading-animation.png',
                'global-systems-inc-office-photo.png',
                'digital-innovations-ltd-team-photo.png',
                'certificate-template.png',
                'apex-industries-team-photo.png'
            ];
        } else {
            $images = [
                'company-logo.png',
                'company-office-photo.png',
                'company-business-card.png',
                'company-letterhead.png',
                'company-team-photo.png',
                'company-building-exterior.png',
                'company-social-banner.png',
            ];
        }


        $randomImage = collect($images)->random();

        return $randomImage;
    }
}

if (!function_exists('isSaas')) {
    function isSaas()
    {
        $isSaas = config('app.is_saas');
        return $isSaas;
    }
}
if (!function_exists('isDemo')) {
    function isDemo()
    {
        $isDemo = config('app.is_demo');
        return $isDemo;
    }
}



if (!function_exists('isNotEditableRoles')) {
    function isNotEditableRoles()
    {
        // Roles that cannot be edited
        $notEditableRoles = [
            'employee',
            'hr',

        ];

        return $notEditableRoles;
    }
}

if (!function_exists('isNotDeletableRoles')) {
    function isNotDeletableRoles()
    {
        $notDeletableRoles = [
            'employee',
            'hr',
        ];

        return $notDeletableRoles;
    }
}

if (!function_exists('financialYearLabelForDate')) {
    /**
     * Indian financial year label (Apr–Mar), e.g. "2026-2027".
     */
    function financialYearLabelForDate($date = null): string
    {
        $date = $date ? \Carbon\Carbon::parse($date) : now();
        $month = (int) $date->format('n');
        $year = (int) $date->format('Y');

        if ($month >= 4) {
            $startYear = $year;
            $endYear = $year + 1;
        } else {
            $startYear = $year - 1;
            $endYear = $year;
        }

        return "{$startYear}-{$endYear}";
    }
}

if (!function_exists('currentFinancialYearLabel')) {
    function currentFinancialYearLabel(): string
    {
        return financialYearLabelForDate(now());
    }
}

if (!function_exists('nextFinancialYearLabel')) {
    function nextFinancialYearLabel(?string $fromYear = null): string
    {
        $base = $fromYear ?? currentFinancialYearLabel();
        $startYear = (int) explode('-', $base)[0];

        return ($startYear + 1) . '-' . ($startYear + 2);
    }
}

if (!function_exists('financialYearSelectOptions')) {
    /**
     * Payroll settings dropdown: current + next financial year only.
     *
     * @return array<int, string>
     */
    function financialYearSelectOptions(): array
    {
        return [
            currentFinancialYearLabel(),
            nextFinancialYearLabel(),
        ];
    }
}

if (!function_exists('buildFinancialYearSelectOptions')) {
    /**
     * @return array<int, string>
     */
    function buildFinancialYearSelectOptions(): array
    {
        return financialYearSelectOptions();
    }
}

if (!function_exists('isAllowedFinancialYearOption')) {
    function isAllowedFinancialYearOption(?string $year): bool
    {
        return $year !== null && $year !== '' && in_array($year, financialYearSelectOptions(), true);
    }
}

if (!function_exists('countEmployeesInBranchForMaster')) {
    /**
     * Count employees in the employees table for a branch-wise master FK (department, shift, etc.).
     */
    function countEmployeesInBranchForMaster(string $column, int $masterId, int $branchId): int
    {
        return (int) \App\Models\Employee::query()
            ->withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->where('branch_id', $branchId)
            ->where($column, $masterId)
            ->count();
    }
}

if (!function_exists('countEmployeesInBranchForSkill')) {
    /**
     * skill_id is JSON (often string ids like ["1"]); match int and string for branch-wise delete checks.
     */
    function countEmployeesInBranchForSkill(int $skillId, int $branchId): int
    {
        return (int) \App\Models\Employee::query()
            ->withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
            ->where('branch_id', $branchId)
            ->where(function ($q) use ($skillId) {
                $q->whereJsonContains('skill_id', $skillId)
                    ->orWhereJsonContains('skill_id', (string) $skillId);
            })
            ->count();
    }
}

if (!function_exists('applyMasterDeleteAttributes')) {
    function applyMasterDeleteAttributes(\Illuminate\Database\Eloquent\Model $model, bool $canDelete, ?string $blockReason, int $employeesCount = 0): void
    {
        $model->setAttribute('can_delete', $canDelete);
        $model->setAttribute('delete_block_reason', $blockReason);
        $model->setAttribute('employees_count', $employeesCount);
    }
}
