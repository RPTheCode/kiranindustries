<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\User;
use App\Models\WeekOff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now()->addMonths(2)->endOfMonth()->toDateString();
        $companyUserIds = $this->companyUserIds($request->user());
        $branchId = $request->user()->employee?->branch_id;

        $holidays = Holiday::query()
            ->whereIn('created_by', $companyUserIds)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('start_date', [$from, $to])
                    ->orWhereBetween('end_date', [$from, $to]);
            })
            ->orderBy('start_date')
            ->get()
            ->map(fn (Holiday $holiday) => [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'start_date' => $holiday->start_date?->format('Y-m-d'),
                'end_date' => $holiday->end_date?->format('Y-m-d'),
                'is_paid' => (bool) $holiday->is_paid,
                'type' => 'holiday',
            ]);

        $weekOffs = WeekOff::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('created_by', $companyUserIds)
            ->get()
            ->map(fn (WeekOff $weekOff) => [
                'id' => $weekOff->id,
                'name' => $weekOff->name ?? __('Week Off'),
                'employment_type' => $weekOff->employment_type,
                'week_off_type' => $weekOff->week_off_type,
                'days' => $weekOff->days,
                'type' => 'week_off',
            ]);

        return response()->json([
            'from' => $from,
            'to' => $to,
            'holidays' => $holidays->values(),
            'week_offs' => $weekOffs->values(),
        ]);
    }

    /**
     * @return list<int>
     */
    private function companyUserIds(User $user): array
    {
        if ($user->type === 'company') {
            $ids = User::where('created_by', $user->id)->pluck('id')->toArray();
            $ids[] = $user->id;

            return $ids;
        }

        $ownerId = User::where('id', $user->created_by)->value('id');
        $ids = User::where('created_by', $ownerId)->pluck('id')->toArray();
        $ids[] = $ownerId;

        return $ids;
    }
}
