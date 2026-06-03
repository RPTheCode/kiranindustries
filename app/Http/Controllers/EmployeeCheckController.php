<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Employee;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class EmployeeCheckController extends Controller
{
    public function index()
    {
        return Inertia::render('hr/employee-check/index');
    }

    public function process(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        $file = $request->file('file');
        
        // Read the excel file
        $data = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\ToArray {
            public function array(array $array) {}
        }, $file);

        if (empty($data) || empty($data[0])) {
            return response()->json(['error' => 'No data found in the file'], 400);
        }

        $sheet = $data[0];
        $codes = [];
        
        // Find the column index for employee code
        $codeColumnIndex = 0; // default to first column
        $headerRowIndex = 0;

        // Scan first 10 rows to find a header row
        for ($i = 0; $i < min(10, count($sheet)); $i++) {
            foreach ($sheet[$i] as $colIndex => $cellValue) {
                $val = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$cellValue));
                if (in_array($val, ['empcode', 'employeeid', 'employee', 'code', 'emp'])) {
                    $codeColumnIndex = $colIndex;
                    $headerRowIndex = $i;
                    break 2;
                }
            }
        }

                // Extract codes from the found column
        $rowMapping = [];
        $headers = [];
        if (isset($sheet[$headerRowIndex])) {
            $headers = $sheet[$headerRowIndex];
        }
        $headers[] = 'VERIFICATION_STATUS'; // Append a new column for status

        foreach ($sheet as $index => $row) {
            if ($index <= $headerRowIndex) continue; // skip headers
            
            if (isset($row[$codeColumnIndex]) && $row[$codeColumnIndex] !== '') {
                $code = (string) $row[$codeColumnIndex];
                $code = trim($code);
                $codes[] = $code;
                if (!isset($rowMapping[$code])) {
                    $rowMapping[$code] = $row;
                }
            }
        }

        $codes = array_unique($codes);

        $employees = Employee::withoutGlobalScopes()
            ->with('branch')
            ->whereIn('employee_id', $codes)
            ->orWhereIn('emy_code', $codes)
            ->get();

        $found = [];
        $notFound = [];

        // Map found employees by employee_id and emy_code
        $foundMap = [];
        foreach ($employees as $emp) {
            $foundMap[(string)$emp->employee_id] = $emp;
            $foundMap[(string)$emp->emy_code] = $emp;
        }

        $notFoundCodes = [];
        foreach ($codes as $code) {
            if (isset($foundMap[$code])) {
                $emp = $foundMap[$code];
                
                $originalRow = $rowMapping[$code] ?? [];
                $originalRow[] = 'Found in DB';

                $found[] = [
                    'code' => $code,
                    'name' => $emp->user ? $emp->user->name : ($emp->name ?? '-'),
                    'branch' => $emp->branch ? $emp->branch->name : 'No Branch',
                    'status' => 'Found in DB',
                    'original_row' => $originalRow
                ];
            } else {
                $notFoundCodes[] = $code;
            }
        }

        // Check ESSL for not found codes
        $esslFound = [];
        $finalNotFound = [];

        if (!empty($notFoundCodes)) {
            // Increase time limit for remote DB queries just in case
            set_time_limit(300);

            try {
                $esslService = new \App\Services\EsslService();
                
                // Pre-check which tables exist
                $tablesToCheck = [];
                for ($i = 0; $i < 3; $i++) {
                    $date = now()->subMonths($i);
                    $tableName = 'DeviceLogs_' . $date->format('n_Y');
                    if ($esslService->tableExists($tableName)) {
                        $tablesToCheck[] = $tableName;
                    }
                }
                if ($esslService->tableExists('AttLog')) {
                    $tablesToCheck[] = 'AttLog';
                }

                $hasEmployeesTable = $esslService->tableExists('Employees');

                // 1. Fetch DISTINCT active users from the logs in bulk
                $activeEsslUsers = [];
                foreach ($tablesToCheck as $table) {
                    try {
                        $userIds = $esslService->query("SELECT DISTINCT UserId FROM {$table}");
                        foreach ($userIds as $row) {
                            $activeEsslUsers[trim((string)$row['UserId'])] = true;
                        }
                    } catch (\Exception $e) {}
                }

                // 2. Fetch Employee Names in bulk (chunks of 1000)
                $esslNames = [];
                if ($hasEmployeesTable) {
                    $chunks = array_chunk($notFoundCodes, 1000);
                    foreach ($chunks as $chunk) {
                        $cleanChunk = array_map(function($c) { return str_replace("'", "''", $c); }, $chunk);
                        $inList = "'" . implode("','", $cleanChunk) . "'";
                        try {
                            $rows = $esslService->query("SELECT EmployeeCode, EmployeeName FROM Employees WHERE EmployeeCode IN ($inList)");
                            foreach ($rows as $row) {
                                $esslNames[trim((string)$row['EmployeeCode'])] = trim((string)$row['EmployeeName']);
                            }
                        } catch (\Exception $e) {}
                    }
                }

                // 3. Process the codes locally in memory
                foreach ($notFoundCodes as $code) {
                    $codeStr = trim((string)$code);
                    $foundInEssl = isset($activeEsslUsers[$codeStr]);
                    $empName = $esslNames[$codeStr] ?? '-';
                    $branch = 'ESSL Database';

                    $originalRow = $rowMapping[$code] ?? [];

                    if ($foundInEssl) {
                        $originalRow[] = 'Needs Import (Active in ESSL)';
                        $esslFound[] = [
                            'code' => $code,
                            'name' => $empName,
                            'branch' => $branch,
                            'status' => 'Found in ESSL (Active last 3 months)',
                            'original_row' => $originalRow
                        ];
                    } else {
                        $originalRow[] = 'Missing Completely';
                        $finalNotFound[] = [
                            'code' => $code,
                            'name' => $empName !== '-' ? $empName : '-',
                            'branch' => '-',
                            'status' => 'Missing',
                            'original_row' => $originalRow
                        ];
                    }
                }
            } catch (\Exception $e) {
                foreach ($notFoundCodes as $code) {
                    $originalRow = $rowMapping[$code] ?? [];
                    $originalRow[] = 'Error Checking ESSL';
                    $finalNotFound[] = [
                        'code' => $code,
                        'name' => '-',
                        'branch' => '-',
                        'status' => 'Not Found (ESSL Error: ' . $e->getMessage() . ')',
                        'original_row' => $originalRow
                    ];
                }
            }
        }

        $responseData = [
            'headers' => $headers,
            'found' => $found,
            'essl_found' => $esslFound,
            'not_found' => $finalNotFound
        ];

        // Cache the data for download to avoid max_input_vars limits and browser freezing
        $downloadToken = \Illuminate\Support\Str::random(40);
        \Illuminate\Support\Facades\Cache::put('employee_check_download_' . $downloadToken, $responseData, now()->addMinutes(30));
        
        $responseData['download_token'] = $downloadToken;

        return response()->json($responseData);
    }

    public function download(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            abort(400, 'Missing download token');
        }

        $data = \Illuminate\Support\Facades\Cache::get('employee_check_download_' . $token);
        if (!$data) {
            abort(404, 'Download link expired or invalid');
        }

        $headers = $data['headers'] ?? [];
        $found = $data['found'] ?? [];
        $esslFound = $data['essl_found'] ?? [];
        $notFound = $data['not_found'] ?? [];

        $export = new class($headers, $found, $esslFound, $notFound) implements WithMultipleSheets {
            private $headers;
            private $found;
            private $esslFound;
            private $notFound;

            public function __construct($headers, $found, $esslFound, $notFound)
            {
                $this->headers = $headers;
                $this->found = $found;
                $this->esslFound = $esslFound;
                $this->notFound = $notFound;
            }

            public function sheets(): array
            {
                $sheets = [];
                
                $sheets[] = new class($this->found, $this->headers, 'Found In DB') implements FromArray, WithHeadings, WithTitle {
                    private $data, $headers, $title;
                    public function __construct($data, $headers, $title) { $this->data = $data; $this->headers = $headers; $this->title = $title; }
                    public function array(): array { return array_map(function($row) { return $row['original_row'] ?? []; }, $this->data); }
                    public function headings(): array { return $this->headers; }
                    public function title(): string { return $this->title; }
                };

                $sheets[] = new class($this->esslFound, $this->headers, 'Found In ESSL (To Import)') implements FromArray, WithHeadings, WithTitle {
                    private $data, $headers, $title;
                    public function __construct($data, $headers, $title) { $this->data = $data; $this->headers = $headers; $this->title = $title; }
                    public function array(): array { return array_map(function($row) { return $row['original_row'] ?? []; }, $this->data); }
                    public function headings(): array { return $this->headers; }
                    public function title(): string { return $this->title; }
                };

                $sheets[] = new class($this->notFound, $this->headers, 'Missing') implements FromArray, WithHeadings, WithTitle {
                    private $data, $headers, $title;
                    public function __construct($data, $headers, $title) { $this->data = $data; $this->headers = $headers; $this->title = $title; }
                    public function array(): array { return array_map(function($row) { return $row['original_row'] ?? []; }, $this->data); }
                    public function headings(): array { return $this->headers; }
                    public function title(): string { return $this->title; }
                };

                return $sheets;
            }
        };

        return Excel::download($export, 'Employee_Verification_Result.xlsx');
    }
}
