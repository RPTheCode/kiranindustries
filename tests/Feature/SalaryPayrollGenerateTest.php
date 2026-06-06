<?php

namespace Tests\Feature;

use App\Models\SalaryPayroll\SalaryPayrollEntry;
use App\Models\SalaryPayroll\SalaryPayrollRun;
use App\Services\SalaryPayroll\SalaryPayrollPayslipService;
use App\Models\User;
use App\Services\SalaryPayroll\SalaryPayrollBatchProcessor;
use App\Services\SalaryPayroll\SalaryPayrollRunService;
use App\Services\SalaryPayroll\SalaryPayrollScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryPayrollGenerateTest extends TestCase
{
    public function test_scope_service_normalizes_filters(): void
    {
        $service = app(SalaryPayrollScopeService::class);
        $normalized = $service->normalizeFilters([
            'category_ids' => ['1', 2],
            'shift_ids' => [],
            'employee_ids' => [5],
        ]);

        $this->assertSame([1, 2], $normalized['category_ids']);
        $this->assertSame([5], $normalized['employee_ids']);
    }

    public function test_run_service_builds_months_for_financial_year(): void
    {
        $service = app(SalaryPayrollRunService::class);
        $months = $service->monthsForFinancialYear('2025-2026');

        $this->assertCount(12, $months);
        $this->assertSame('2025-04', $months[0]['value']);
        $this->assertSame('2026-03', $months[11]['value']);
    }

    public function test_period_from_month_year(): void
    {
        $service = app(SalaryPayrollRunService::class);
        $period = $service->periodFromMonthYear('2026-05');

        $this->assertSame('2026-05-01', $period['pay_period_start']);
        $this->assertSame('2026-05-31', $period['pay_period_end']);
        $this->assertSame('month', $period['period_mode']);
    }

    public function test_resolve_period_custom_range(): void
    {
        $service = app(SalaryPayrollRunService::class);
        $period = $service->resolvePeriod([
            'period_mode' => 'custom',
            'financial_year' => '2026-2027',
            'pay_period_start' => '2026-06-01',
            'pay_period_end' => '2026-06-15',
        ]);

        $this->assertSame('2026-06-01', $period['pay_period_start']);
        $this->assertSame('2026-06-15', $period['pay_period_end']);
        $this->assertSame('2026-06', $period['month_year']);
        $this->assertSame('custom', $period['period_mode']);
    }

    public function test_is_full_month_period(): void
    {
        $service = app(SalaryPayrollRunService::class);

        $this->assertTrue($service->isFullMonthPeriod('2026-06-01', '2026-06-30'));
        $this->assertFalse($service->isFullMonthPeriod('2026-06-01', '2026-06-15'));
    }

    public function test_build_title_for_custom_period(): void
    {
        $service = app(SalaryPayrollRunService::class);
        $title = $service->buildTitle([
            'pay_period_start' => '2026-06-01',
            'pay_period_end' => '2026-06-15',
            'period_mode' => 'custom',
        ], null);

        $this->assertStringContainsString('01 Jun', $title);
        $this->assertStringContainsString('15 Jun 2026', $title);
    }

    public function test_salary_payroll_run_model_scope_label(): void
    {
        $run = new SalaryPayrollRun(['scope_mode' => 'category']);
        $this->assertSame('By Category', $run->scopeLabel());
    }

    public function test_scope_signature_matches_same_filters(): void
    {
        $service = app(SalaryPayrollRunService::class);

        $sig1 = $service->scopeSignature('category', ['category_ids' => [2, 1]]);
        $sig2 = $service->scopeSignature('category', ['category_ids' => [1, 2]]);
        $this->assertSame($sig1, $sig2);

        $sig3 = $service->scopeSignature('shift', ['shift_ids' => [1]]);
        $this->assertNotSame($sig1, $sig3);
    }

    public function test_preview_summary_supports_filters_and_pagination(): void
    {
        $service = app(SalaryPayrollScopeService::class);

        $rows = [
            ['id' => 1, 'name' => 'Alice', 'employee_code' => 'E001', 'category_id' => 1, 'shift_id' => 1, 'department_id' => 1, 'monthly_gross' => 10000, 'ready' => true, 'status' => 'ready'],
            ['id' => 2, 'name' => 'Bob', 'employee_code' => 'E002', 'category_id' => 2, 'shift_id' => 1, 'department_id' => 1, 'monthly_gross' => 0, 'ready' => false, 'status' => 'missing'],
            ['id' => 3, 'name' => 'Charlie', 'employee_code' => 'E003', 'category_id' => 1, 'shift_id' => 2, 'department_id' => 2, 'monthly_gross' => 8000, 'ready' => true, 'status' => 'ready'],
        ];

        $method = new \ReflectionMethod($service, 'filterPreviewRows');
        $method->setAccessible(true);
        $filtered = $method->invoke($service, $rows, ['status' => 'missing']);
        $this->assertCount(1, $filtered);
        $this->assertSame('Bob', $filtered[0]['name']);

        $paginate = new \ReflectionMethod($service, 'paginatePreviewRows');
        $paginate->setAccessible(true);
        $page = $paginate->invoke($service, $rows, ['page' => 1, 'per_page' => 2]);
        $this->assertCount(2, $page['data']);
        $this->assertSame(2, $page['meta']['last_page']);
        $this->assertSame(3, $page['meta']['total']);
    }

    public function test_payslip_service_allows_locked_or_finalized_entries_only(): void
    {
        $service = app(SalaryPayrollPayslipService::class);

        $openEntry = new SalaryPayrollEntry(['is_locked' => false]);
        $openEntry->setRelation('run', new SalaryPayrollRun(['status' => 'calculated']));

        $lockedEntry = new SalaryPayrollEntry(['is_locked' => true, 'employee_id' => 99]);
        $lockedEntry->setRelation('run', new SalaryPayrollRun(['status' => 'calculated']));

        $finalizedEntry = new SalaryPayrollEntry(['is_locked' => false]);
        $finalizedEntry->setRelation('run', new SalaryPayrollRun(['status' => 'finalized']));

        $this->assertFalse($service->canGeneratePayslip($openEntry));
        $this->assertTrue($service->canGeneratePayslip($lockedEntry));
        $this->assertTrue($service->canGeneratePayslip($finalizedEntry));
    }
}
