<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\HolidaysImport;
use App\Exports\HolidaysTemplateExport;

class HolidayController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Holiday::withPermissionCheck()->with(['branches']);

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Handle category filter
        if ($request->has('category') && !empty($request->category)) {
            $query->where('category', $request->category);
        }

        // Handle branch scope
        $activeBranchId = session('active_branch_id');
        if ($activeBranchId) {
            $query->whereHas('branches', function ($q) use ($activeBranchId) {
                $q->where('branches.id', $activeBranchId);
            });
        }

        // Determine target year (default to current if not provided)
        $targetYear = (int) ($request->year ?? date('Y'));

        // Fetch holidays:
        // 1. In the target year
        // 2. OR Recurring (any year)
        $query->where(function ($q) use ($targetYear) {
            $q->whereYear('start_date', $targetYear)
                ->orWhereYear('end_date', $targetYear)
                ->orWhere('is_recurring', 1);
        });

        // Get all matching records
        $holidaysCollection = $query->get();

        // Transform recurring holidays to the target year
        $holidaysCollection = $holidaysCollection->map(function ($holiday) use ($targetYear) {
            if ($holiday->is_recurring && $holiday->start_date && $holiday->start_date->year != $targetYear) {
                $newHoliday = clone $holiday;

                // Use copies to avoid mutating original or affecting calculation
                $originalStart = $holiday->start_date->copy();
                $originalEnd = $holiday->end_date ? $holiday->end_date->copy() : $originalStart->copy();

                // Calculate original duration purely from original dates
                // abs() is default, so order doesn't matter for magnitude
                $duration = $originalStart->diffInDays($originalEnd);

                // Create new start date safely
                try {
                    $newStart = $originalStart->copy()->setYear($targetYear);
                } catch (\Exception $e) {
                    // Handle leap year edge cases by fallback creation
                    $newStart = \Carbon\Carbon::create($targetYear, $originalStart->month, $originalStart->day);
                }

                // Assign new dates
                $newHoliday->start_date = $newStart;
                $newHoliday->end_date = $newStart->copy()->addDays($duration);

                return $newHoliday;
            }
            return $holiday;
        });

        // Apply Date Range Filter (in PHP, after transformation)
        if ($request->has('date_from') && !empty($request->date_from)) {
            $holidaysCollection = $holidaysCollection->filter(function ($h) use ($request) {
                return $h->start_date->format('Y-m-d') >= $request->date_from ||
                    $h->end_date->format('Y-m-d') >= $request->date_from;
            });
        }

        if ($request->has('date_to') && !empty($request->date_to)) {
            $holidaysCollection = $holidaysCollection->filter(function ($h) use ($request) {
                return $h->start_date->format('Y-m-d') <= $request->date_to ||
                    $h->end_date->format('Y-m-d') <= $request->date_to;
            });
        }

        // Sort
        $sortField = $request->sort_field ?? 'start_date';
        $sortDirection = $request->sort_direction ?? 'asc';

        if ($sortDirection === 'asc') {
            $holidaysCollection = $holidaysCollection->sortBy($sortField);
        } else {
            $holidaysCollection = $holidaysCollection->sortByDesc($sortField);
        }

        // Manual Pagination
        $page = $request->page ?? 1;
        $perPage = $request->per_page ?? 10;
        $total = $holidaysCollection->count();

        $paginatedItems = $holidaysCollection->forPage($page, $perPage)->values();

        $holidays = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedItems,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Get branches for filter dropdown
        $branches = \Illuminate\Support\Facades\Auth::user()->branches()
            ->select('branches.id', 'branches.name')
            ->get();

        // Get categories for filter dropdown
        $categories = ['national', 'religious', 'company-specific', 'regional'];

        // Get available years for filter dropdown
        $years = Holiday::whereIn('created_by', getCompanyAndUsersId())
            ->selectRaw('YEAR(start_date) as year')
            ->distinct()
            ->pluck('year')
            ->toArray();

        // Add current year if not in the list
        $currentYear = (int) date('Y');
        if (!in_array($currentYear, $years)) {
            $years[] = $currentYear;
        }
        sort($years);

        return Inertia::render('hr/holidays/index', [
            'holidays' => $holidays,
            'branches' => $branches,
            'categories' => $categories,
            'years' => $years,
            'filters' => $request->all(['search', 'category', 'date_from', 'date_to', 'year', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    /**
     * Display the calendar view.
     */
    public function calendar(Request $request)
    {
        $year = $request->year ?? date('Y');

        $holidays = Holiday::with(['branches'])
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where(function ($q) use ($year) {
                $q->whereYear('start_date', $year)
                    ->orWhereYear('end_date', $year);
            })
            ->get();

        // Apply global branch scope
        $activeBranchId = session('active_branch_id');
        if ($activeBranchId) {
            $holidays = $holidays->filter(function ($holiday) use ($activeBranchId) {
                return $holiday->branches->contains('id', $activeBranchId);
            })->values();
        }

        // Get branches for filter dropdown
        $branches = \Illuminate\Support\Facades\Auth::user()->branches()
            ->select('branches.id', 'branches.name')
            ->get();

        // Get categories for filter dropdown
        $categories = ['national', 'religious', 'company-specific', 'regional'];

        // Get available years for filter dropdown
        $years = Holiday::whereIn('created_by', getCompanyAndUsersId())
            ->selectRaw('YEAR(start_date) as year')
            ->distinct()
            ->pluck('year')
            ->toArray();

        // Add current year if not in the list
        $currentYear = (int) date('Y');
        if (!in_array($currentYear, $years)) {
            $years[] = $currentYear;
        }
        sort($years);

        // Format holidays for FullCalendar
        $calendarEvents = $holidays->map(function ($holiday) {
            return [
                'id' => $holiday->id,
                'title' => $holiday->name,
                'start' => $holiday->start_date,
                'end' => $holiday->end_date ? \Carbon\Carbon::parse($holiday->end_date)->addDay()->format('Y-m-d') : null,
                'allDay' => true,
                'backgroundColor' => $this->getCategoryColor($holiday->category),
                'borderColor' => $this->getCategoryColor($holiday->category),
                'extendedProps' => [
                    'category' => $holiday->category,
                    'description' => $holiday->description,
                    'is_paid' => $holiday->is_paid,
                    'is_half_day' => $holiday->is_half_day,
                    'is_recurring' => $holiday->is_recurring,
                    'branches' => $holiday->branches->pluck('name')->toArray()
                ]
            ];
        });

        return Inertia::render('hr/holidays/index', [
            'holidays' => $holidays,
            'calendarEvents' => $calendarEvents,
            'branches' => $branches, // Pass branches to frontend
            'categories' => $categories,
            'years' => $years,
            'currentYear' => (int) $year,
            'filters' => $request->all(['category']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'category' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_recurring' => 'nullable|boolean',
            'is_paid' => 'nullable|boolean',
            'is_half_day' => 'nullable|boolean',
            // 'branch_ids' removed as we use active branch
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }        $startDate = $request->start_date;
        $endDate = $request->end_date ?? $startDate;
        
        $scope = $request->input('branch_scope', 'current');
        $selectedBranches = $request->input('selected_branches', []);
        $targetBranchIds = [];

        if ($scope === 'all') {
            $targetBranchIds = \Illuminate\Support\Facades\Auth::user()->branches()->pluck('branches.id')->toArray();
        } elseif ($scope === 'selected' && !empty($selectedBranches)) {
            $targetBranchIds = \Illuminate\Support\Facades\Auth::user()->branches()
                ->whereIn('branches.id', $selectedBranches)
                ->pluck('branches.id')
                ->toArray();
        } else {
            $activeBranchId = session('active_branch_id');
            if ($activeBranchId) {
                $targetBranchIds = [$activeBranchId];
            }
        }

        if (!empty($targetBranchIds)) {
            $overlapping = Holiday::whereIn('created_by', getCompanyAndUsersId())
                ->whereHas('branches', function ($q) use ($targetBranchIds) {
                    $q->whereIn('branches.id', $targetBranchIds);
                })
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->where('start_date', '<=', $endDate)
                      ->where(function ($sub) use ($startDate) {
                          $sub->where('end_date', '>=', $startDate)
                              ->orWhere(function ($nullEnd) use ($startDate) {
                                  $nullEnd->whereNull('end_date')
                                          ->where('start_date', '>=', $startDate);
                              });
                      });
                })
                ->exists();

            if ($overlapping) {
                return redirect()->back()->with('error', __('A holiday already exists in this date range for the selected branch(es).'))->withInput();
            }
        }

        $holiday = Holiday::create([
            'name' => $request->name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'category' => $request->category,
            'description' => $request->description,
            'is_recurring' => $request->is_recurring ?? false,
            'is_paid' => $request->is_paid ?? true,
            'is_half_day' => $request->is_half_day ?? false,
            'created_by' => creatorId(),
        ]);

        if (!empty($targetBranchIds)) {
            $holiday->branches()->sync($targetBranchIds);
        }
        $scope = $request->input('branch_scope', 'current'); // default to current
        $selectedBranches = $request->input('selected_branches', []);

        if ($scope === 'all') {
            // Attach all branches belonging to this user
            $allBranchIds = \Illuminate\Support\Facades\Auth::user()->branches()->pluck('branches.id')->toArray();
            $holiday->branches()->sync($allBranchIds);
        } elseif ($scope === 'selected' && !empty($selectedBranches)) {
            // Attach selected branches, ensuring they belong to the user
            $validBranchIds = \Illuminate\Support\Facades\Auth::user()->branches()
                ->whereIn('branches.id', $selectedBranches)
                ->pluck('branches.id')
                ->toArray();
            $holiday->branches()->sync($validBranchIds);
        } else {
            // Default: Attach active branch
            $activeBranchId = session('active_branch_id');
            if ($activeBranchId) {
                $holiday->branches()->attach([$activeBranchId]);
            }
        }

        return redirect()->back()->with('success', __('Holiday created successfully'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Holiday $holiday)
    {
        // Check if holiday belongs to current company
        if (!in_array($holiday->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to update this holiday'));
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'category' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_recurring' => 'nullable|boolean',
            'is_paid' => 'nullable|boolean',
            'is_half_day' => 'nullable|boolean',
            // 'branch_ids' removed
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $startDate = $request->start_date;
        $endDate = $request->end_date ?? $startDate;
        
        $scope = $request->input('branch_scope', 'current');
        $selectedBranches = $request->input('selected_branches', []);
        $targetBranchIds = [];

        if ($scope === 'all') {
            $targetBranchIds = \Illuminate\Support\Facades\Auth::user()->branches()->pluck('branches.id')->toArray();
        } elseif ($scope === 'selected' && !empty($selectedBranches)) {
            $targetBranchIds = \Illuminate\Support\Facades\Auth::user()->branches()
                ->whereIn('branches.id', $selectedBranches)
                ->pluck('branches.id')
                ->toArray();
        } else {
            $activeBranchId = session('active_branch_id');
            if ($activeBranchId) {
                $targetBranchIds = [$activeBranchId];
            }
        }

        if (!empty($targetBranchIds)) {
            $overlapping = Holiday::where('id', '!=', $holiday->id)
                ->whereIn('created_by', getCompanyAndUsersId())
                ->whereHas('branches', function ($q) use ($targetBranchIds) {
                    $q->whereIn('branches.id', $targetBranchIds);
                })
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->where('start_date', '<=', $endDate)
                      ->where(function ($sub) use ($startDate) {
                          $sub->where('end_date', '>=', $startDate)
                              ->orWhere(function ($nullEnd) use ($startDate) {
                                  $nullEnd->whereNull('end_date')
                                          ->where('start_date', '>=', $startDate);
                              });
                      });
                })
                ->exists();

            if ($overlapping) {
                return redirect()->back()->with('error', __('A holiday already exists in this date range for the selected branch(es).'))->withInput();
            }
        }

        $holiday->update([
            'name' => $request->name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'category' => $request->category,
            'description' => $request->description,
            'is_recurring' => $request->is_recurring ?? false,
            'is_paid' => $request->is_paid ?? true,
            'is_half_day' => $request->is_half_day ?? false,
        ]);

        if (!empty($targetBranchIds)) {
            $holiday->branches()->sync($targetBranchIds);
        }

        return redirect()->back()->with('success', __('Holiday updated successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Holiday $holiday)
    {
        // Check if holiday belongs to current company
        if (!in_array($holiday->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('You do not have permission to delete this holiday'));
        }

        // Detach all branches
        $holiday->branches()->detach();

        // Delete the holiday
        $holiday->delete();

        return redirect()->back()->with('success', __('Holiday deleted successfully'));
    }

    /**
     * Export holidays to PDF.
     */
    public function exportPdf(Request $request)
    {
        $year = $request->year ?? date('Y');

        $query = Holiday::with(['branches'])
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where(function ($q) use ($year) {
                $q->whereYear('start_date', $year)
                    ->orWhereYear('end_date', $year);
            });

        if ($request->category) {
            $query->where('category', $request->category);
        }

        $activeBranchId = session('active_branch_id');
        if ($activeBranchId) {
            $query->whereHas('branches', function ($q) use ($activeBranchId) {
                $q->where('branches.id', $activeBranchId);
            });
        }

        $holidays = $query->orderBy('start_date', 'asc')->get();

        $html = view('exports.holidays-pdf', compact('holidays', 'year'))->render();

        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', "attachment; filename=holidays-{$year}.html");
    }

    /**
     * Export holidays to iCal format.
     */
    public function exportIcal(Request $request)
    {
        $year = $request->year ?? date('Y');

        $query = Holiday::with(['branches'])
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where(function ($q) use ($year) {
                $q->whereYear('start_date', $year)
                    ->orWhereYear('end_date', $year);
            });

        if ($request->category) {
            $query->where('category', $request->category);
        }

        $activeBranchId = session('active_branch_id');
        if ($activeBranchId) {
            $query->whereHas('branches', function ($q) use ($activeBranchId) {
                $q->where('branches.id', $activeBranchId);
            });
        }

        $holidays = $query->orderBy('start_date', 'asc')->get();

        $icalContent = "BEGIN:VCALENDAR\r\n";
        $icalContent .= "VERSION:2.0\r\n";
        $icalContent .= "PRODID:-//Company//Holidays//EN\r\n";
        $icalContent .= "CALSCALE:GREGORIAN\r\n";

        foreach ($holidays as $holiday) {
            $startDate = \Carbon\Carbon::parse($holiday->start_date)->format('Ymd');
            $endDate = $holiday->end_date ? \Carbon\Carbon::parse($holiday->end_date)->addDay()->format('Ymd') : \Carbon\Carbon::parse($holiday->start_date)->addDay()->format('Ymd');

            $icalContent .= "BEGIN:VEVENT\r\n";
            $icalContent .= "UID:" . md5($holiday->id . $holiday->name) . "@company.com\r\n";
            $icalContent .= "DTSTART;VALUE=DATE:{$startDate}\r\n";
            $icalContent .= "DTEND;VALUE=DATE:{$endDate}\r\n";
            $icalContent .= "SUMMARY:" . str_replace(',', '\,', $holiday->name) . "\r\n";
            if ($holiday->description) {
                $icalContent .= "DESCRIPTION:" . str_replace(',', '\,', $holiday->description) . "\r\n";
            }
            $icalContent .= "END:VEVENT\r\n";
        }

        $icalContent .= "END:VCALENDAR\r\n";

        return response($icalContent)
            ->header('Content-Type', 'text/calendar')
            ->header('Content-Disposition', "attachment; filename=holidays-{$year}.ics");
    }

    /**
     * Get color for holiday category
     */
    private function getCategoryColor($category)
    {
        $colors = [
            'national' => '#3b82f6',
            'religious' => '#8b5cf6',
            'company-specific' => '#10b981',
            'regional' => '#f59e0b'
        ];

        return $colors[$category] ?? '#6b7280';
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
            'branch_scope' => 'nullable|string|in:current,all,selected',
            'selected_branches' => 'nullable|array',
        ]);

        try {
            $branchScope = $request->input('branch_scope', 'current');
            $selectedBranches = $request->input('selected_branches', []);

            $import = new HolidaysImport($branchScope, $selectedBranches);
            Excel::import($import, $request->file('file'));

            $failures = $import->failures();
            $customFailures = $import->customFailures ?? [];
            $savedCount = $import->rowsSaved;
            $failedCount = $failures->count() + count($customFailures);

            if ($failedCount > 0) {
                $msg = '<div class="space-y-1 text-sm">';
                $msg .= '<div class="font-bold text-gray-800 border-b pb-1 mb-2">Import Summary: ' . $savedCount . ' saved, ' . $failedCount . ' failed</div>';
                
                $msg .= '<div class="text-red-500 mt-2 font-semibold">✘ Failures:</div>';
                $msg .= '<ul class="list-disc pl-5 text-red-500 text-xs space-y-0.5">';
                foreach ($failures as $failure) {
                    $msg .= '<li>Row ' . $failure->row() . ': ' . implode(', ', $failure->errors()) . '</li>';
                }
                foreach ($customFailures as $customFailure) {
                    $msg .= '<li>' . $customFailure . '</li>';
                }
                $msg .= '</ul>';
                $msg .= '</div>';

                return redirect()->back()->with('error', $msg);
            }

            return redirect()->back()->with('success', __('Holidays imported successfully'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function exportTemplate()
    {
        return Excel::download(new HolidaysTemplateExport, 'holidays_import_template.xlsx');
    }
}