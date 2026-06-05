<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PlanOrderController;
use App\Http\Controllers\PlanRequestController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\BiometricReportController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\ImpersonateController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\LandingPage\CustomPageController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\RazorpayController;
use App\Http\Controllers\MercadoPagoController;
use App\Http\Controllers\StripePaymentController;
use App\Http\Controllers\PayPalPaymentController;
use App\Http\Controllers\BankPaymentController;
use App\Http\Controllers\PaystackPaymentController;
use App\Http\Controllers\FlutterwavePaymentController;
use App\Http\Controllers\PayTabsPaymentController;
use App\Http\Controllers\SkrillPaymentController;
use App\Http\Controllers\CoinGatePaymentController;
use App\Http\Controllers\PayfastPaymentController;
use App\Http\Controllers\TapPaymentController;
use App\Http\Controllers\XenditPaymentController;
use App\Http\Controllers\PayTRPaymentController;
use App\Http\Controllers\MolliePaymentController;
use App\Http\Controllers\ToyyibPayPaymentController;
use App\Http\Controllers\CashfreeController;
use App\Http\Controllers\IyzipayPaymentController;
use App\Http\Controllers\BenefitPaymentController;
use App\Http\Controllers\OzowPaymentController;
use App\Http\Controllers\EasebuzzPaymentController;
use App\Http\Controllers\KhaltiPaymentController;
use App\Http\Controllers\AuthorizeNetPaymentController;
use App\Http\Controllers\FedaPayPaymentController;
use App\Http\Controllers\AwardTypeController;
use App\Http\Controllers\AwardController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ResignationController;
use App\Http\Controllers\TerminationController;
use App\Http\Controllers\WarningController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\EmployeeTransferController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AssetTypeController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\TrainingTypeController;
use App\Http\Controllers\TrainingProgramController;
use App\Http\Controllers\TrainingSessionController;
use App\Http\Controllers\TrainingAssessmentController;
use App\Http\Controllers\EmployeeTrainingController;
use App\Http\Controllers\PayHerePaymentController;
use App\Http\Controllers\CinetPayPaymentController;
use App\Http\Controllers\PaiementPaymentController;
use App\Http\Controllers\NepalstePaymentController;
use App\Http\Controllers\YooKassaPaymentController;
use App\Http\Controllers\AamarpayPaymentController;
use App\Http\Controllers\MidtransPaymentController;
use App\Http\Controllers\PaymentWallPaymentController;
use App\Http\Controllers\SSPayPaymentController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\MonthlyIncentiveController;
use App\Http\Controllers\DailyProductionAttendanceEntryController;
use App\Http\Controllers\PayrollSettingController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\BiometricAttendanceSyncController;

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HrDocumentController;
use App\Http\Controllers\PerformanceIndicatorCategoryController;
use App\Http\Controllers\PerformanceIndicatorController;
use App\Http\Controllers\GoalTypeController;
use App\Http\Controllers\EmployeeGoalController;
use App\Http\Controllers\ReviewCycleController;
use App\Http\Controllers\EmployeeReviewController;
use App\Http\Controllers\ResignReasonController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\MaterialItemController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Route::get('/', [LandingPageController::class, 'show'])->name('home');
use Illuminate\Support\Facades\DB;

Route::get('essl-test', function () {
    try {
        $esslService = app(\App\Services\EsslService::class);
        
        // Try to find a table we can read from
        $date = \Carbon\Carbon::now();
        $table = $esslService->resolveDeviceLogsTable($date);
        
        if (!$table) {
            // Fallback: check if we can query Employees table
            try {
                $employees = $esslService->getEmployees();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Connected successfully! Employees table accessed.',
                    'employee_count' => count($employees),
                    'sample_employees' => array_slice($employees, 0, 5)
                ]);
            } catch (\Exception $employeeEx) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Connected to database, but both DeviceLogs and Employees tables failed: ' . $employeeEx->getMessage(),
                    'connection_details' => config('database.connections.essl')
                ], 500);
            }
        }

        $logs = $esslService->getLogsFromTable($table, $date->copy()->subDays(30)->format('Y-m-d H:i:s'), $date->format('Y-m-d H:i:s'));
        return response()->json([
            'status' => 'success',
            'message' => 'Connected successfully!',
            'table_queried' => $table,
            'log_count' => count($logs),
            'sample_logs' => array_slice($logs, 0, 5)
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'connection_details' => config('database.connections.essl')
        ], 500);
    }
});

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// Public form submission routes

// Cashfree webhook (public route)
Route::post('cashfree/webhook', [CashfreeController::class, 'webhook'])->name('cashfree.webhook');

// Benefit webhook (public route)
Route::post('benefit/webhook', [BenefitPaymentController::class, 'webhook'])->name('benefit.webhook');
Route::get('payments/benefit/success', [BenefitPaymentController::class, 'success'])->name('benefit.success');
Route::post('payments/benefit/callback', [BenefitPaymentController::class, 'callback'])->name('benefit.callback');

// FedaPay callback (public route)
Route::match(['GET', 'POST'], 'payments/fedapay/callback', [FedaPayPaymentController::class, 'callback'])->name('fedapay.callback');

// YooKassa success/callback (public routes)
Route::get('payments/yookassa/success', [YooKassaPaymentController::class, 'success'])->name('yookassa.success');
Route::post('payments/yookassa/callback', [YooKassaPaymentController::class, 'callback'])->name('yookassa.callback');

// Nepalste success/callback (public routes)
Route::get('payments/nepalste/success', [NepalstePaymentController::class, 'success'])->name('nepalste.success');
Route::post('payments/nepalste/callback', [NepalstePaymentController::class, 'callback'])->name('nepalste.callback');

// PayTR callback (public route)
Route::post('payments/paytr/callback', [PayTRPaymentController::class, 'callback'])->name('paytr.callback');

// PayTabs callback (public route)
Route::match(['GET', 'POST'], 'payments/paytabs/callback', [PayTabsPaymentController::class, 'callback'])->name('paytabs.callback');
Route::get('payments/paytabs/success', [PayTabsPaymentController::class, 'success'])->name('paytabs.success');

// Tap payment routes (public routes)
Route::get('payments/tap/success', [TapPaymentController::class, 'success'])->name('tap.success');
Route::post('payments/tap/callback', [TapPaymentController::class, 'callback'])->name('tap.callback');

// Aamarpay payment routes (public routes)
Route::match(['GET', 'POST'], 'payments/aamarpay/success', [AamarpayPaymentController::class, 'success'])->name('aamarpay.success');
Route::post('payments/aamarpay/callback', [AamarpayPaymentController::class, 'callback'])->name('aamarpay.callback');

// PaymentWall callback (public route)
Route::match(['GET', 'POST'], 'payments/paymentwall/callback', [PaymentWallPaymentController::class, 'callback'])->name('paymentwall.callback');
Route::get('payments/paymentwall/success', [PaymentWallPaymentController::class, 'success'])->name('paymentwall.success');

// PayFast payment routes (public routes)
Route::get('payments/payfast/success', [PayfastPaymentController::class, 'success'])->name('payfast.success');
Route::post('payments/payfast/callback', [PayfastPaymentController::class, 'callback'])->name('payfast.callback');

// CoinGate callback (public route)
Route::match(['GET', 'POST'], 'payments/coingate/callback', [CoinGatePaymentController::class, 'callback'])->name('coingate.callback');

// Xendit payment routes (public routes)
Route::get('payments/xendit/success', [XenditPaymentController::class, 'success'])->name('xendit.success');
Route::post('payments/xendit/callback', [XenditPaymentController::class, 'callback'])->name('xendit.callback');

// PWA Manifest routes removed

Route::get('/landing-page', [LandingPageController::class, 'settings'])->name('landing-page');
Route::post('/landing-page/contact', [LandingPageController::class, 'submitContact'])->name('landing-page.contact');
Route::post('/landing-page/subscribe', [LandingPageController::class, 'subscribe'])->name('landing-page.subscribe');
Route::get('/page/{slug}', [CustomPageController::class, 'show'])->name('custom-page.show');

Route::get('/translations/{locale}', [TranslationController::class, 'getTranslations'])->name('translations');
Route::get('/refresh-language/{locale}', [TranslationController::class, 'refreshLanguage'])->name('refresh-language');
Route::get('/initial-locale', [TranslationController::class, 'getInitialLocale'])->name('initial-locale');


// Email Templates routes (no middleware for testing)
Route::get('email-templates', [\App\Http\Controllers\EmailTemplateController::class, 'index'])->name('email-templates.index');
Route::get('email-templates/{emailTemplate}', [\App\Http\Controllers\EmailTemplateController::class, 'show'])->name('email-templates.show');
Route::put('email-templates/{emailTemplate}/settings', [\App\Http\Controllers\EmailTemplateController::class, 'updateSettings'])->name('email-templates.update-settings');
Route::put('email-templates/{emailTemplate}/content', [\App\Http\Controllers\EmailTemplateController::class, 'updateContent'])->name('email-templates.update-content');

Route::middleware(['auth', 'verified', 'setting'])->group(function () {

    Route::middleware('checksaas')->group(function () {
        // Plans routes - accessible without plan check
        Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
        Route::post('plans/request', [PlanController::class, 'requestPlan'])->name('plans.request');
        Route::post('plans/trial', [PlanController::class, 'startTrial'])->name('plans.trial');
        Route::post('plans/subscribe', [PlanController::class, 'subscribe'])->name('plans.subscribe');
        Route::post('plans/coupons/validate', [CouponController::class, 'validate'])->name('coupons.validate');




        // Payment routes - accessible without plan check
        Route::post('payments/stripe', [StripePaymentController::class, 'processPayment'])->name('stripe.payment');
        Route::post('payments/paypal', [PayPalPaymentController::class, 'processPayment'])->name('paypal.payment');
        Route::post('payments/bank', [BankPaymentController::class, 'processPayment'])->name('bank.payment');
        Route::post('payments/paystack', [PaystackPaymentController::class, 'processPayment'])->name('paystack.payment');
        Route::post('payments/flutterwave', [FlutterwavePaymentController::class, 'processPayment'])->name('flutterwave.payment');
        Route::post('payments/paytabs', [PayTabsPaymentController::class, 'processPayment'])->name('paytabs.payment');
        Route::post('payments/skrill', [SkrillPaymentController::class, 'processPayment'])->name('skrill.payment');
        Route::post('payments/coingate', [CoinGatePaymentController::class, 'processPayment'])->name('coingate.payment');
        Route::post('payments/payfast', [PayfastPaymentController::class, 'processPayment'])->name('payfast.payment');
        Route::post('payments/mollie', [MolliePaymentController::class, 'processPayment'])->name('mollie.payment');
        Route::post('payments/toyyibpay', [ToyyibPayPaymentController::class, 'processPayment'])->name('toyyibpay.payment');
        Route::post('payments/iyzipay', [IyzipayPaymentController::class, 'processPayment'])->name('iyzipay.payment');
        Route::post('payments/benefit', [BenefitPaymentController::class, 'processPayment'])->name('benefit.payment');
        Route::post('payments/ozow', [OzowPaymentController::class, 'processPayment'])->name('ozow.payment');
        Route::post('payments/easebuzz', [EasebuzzPaymentController::class, 'processPayment'])->name('easebuzz.payment');
        Route::post('payments/khalti', [KhaltiPaymentController::class, 'processPayment'])->name('khalti.payment');
        Route::post('payments/authorizenet', [AuthorizeNetPaymentController::class, 'processPayment'])->name('authorizenet.payment');
        Route::post('payments/fedapay', [FedaPayPaymentController::class, 'processPayment'])->name('fedapay.payment');
        Route::post('payments/payhere', [PayHerePaymentController::class, 'processPayment'])->name('payhere.payment');
        Route::post('payments/cinetpay', [CinetPayPaymentController::class, 'processPayment'])->name('cinetpay.payment');
        Route::post('payments/paiement', [PaiementPaymentController::class, 'processPayment'])->name('paiement.payment');
        Route::post('payments/nepalste', [NepalstePaymentController::class, 'processPayment'])->name('nepalste.payment');
        Route::post('payments/yookassa', [YooKassaPaymentController::class, 'processPayment'])->name('yookassa.payment');
        Route::post('payments/aamarpay', [AamarpayPaymentController::class, 'processPayment'])->name('aamarpay.payment');
        Route::post('payments/midtrans', [MidtransPaymentController::class, 'processPayment'])->name('midtrans.payment');
        Route::post('payments/paymentwall', [PaymentWallPaymentController::class, 'processPayment'])->name('paymentwall.payment');
        Route::post('payments/sspay', [SSPayPaymentController::class, 'processPayment'])->name('sspay.payment');

        // Payment gateway specific routes
        Route::post('razorpay/create-order', [RazorpayController::class, 'createOrder'])->name('razorpay.create-order');
        Route::post('razorpay/verify-payment', [RazorpayController::class, 'verifyPayment'])->name('razorpay.verify-payment');
        Route::post('cashfree/create-session', [CashfreeController::class, 'createPaymentSession'])->name('cashfree.create-session');
        Route::post('cashfree/verify-payment', [CashfreeController::class, 'verifyPayment'])->name('cashfree.verify-payment');
        Route::post('mercadopago/create-preference', [MercadoPagoController::class, 'createPreference'])->name('mercadopago.create-preference');
        Route::post('mercadopago/process-payment', [MercadoPagoController::class, 'processPayment'])->name('mercadopago.process-payment');

        // Other payment creation routes
        Route::post('tap/create-payment', [TapPaymentController::class, 'createPayment'])->name('tap.create-payment');
        Route::post('xendit/create-payment', [XenditPaymentController::class, 'createPayment'])->name('xendit.create-payment');
        Route::post('payments/paytr/create-token', [PayTRPaymentController::class, 'createPaymentToken'])->name('paytr.create-token');
        Route::post('iyzipay/create-form', [IyzipayPaymentController::class, 'createPaymentForm'])->name('iyzipay.create-form');
        Route::post('benefit/create-session', [BenefitPaymentController::class, 'createPaymentSession'])->name('benefit.create-session');
        Route::post('ozow/create-payment', [OzowPaymentController::class, 'createPayment'])->name('ozow.create-payment');
        Route::post('easebuzz/create-payment', [EasebuzzPaymentController::class, 'createPayment'])->name('easebuzz.create-payment');
        Route::post('khalti/create-payment', [KhaltiPaymentController::class, 'createPayment'])->name('khalti.create-payment');
        Route::post('authorizenet/create-form', [AuthorizeNetPaymentController::class, 'createPaymentForm'])->name('authorizenet.create-form');
        Route::post('fedapay/create-payment', [FedaPayPaymentController::class, 'createPayment'])->name('fedapay.create-payment');
        Route::post('payhere/create-payment', [PayHerePaymentController::class, 'createPayment'])->name('payhere.create-payment');
        Route::post('cinetpay/create-payment', [CinetPayPaymentController::class, 'createPayment'])->name('cinetpay.create-payment');
        Route::post('paiement/create-payment', [PaiementPaymentController::class, 'createPayment'])->name('paiement.create-payment');
        Route::post('nepalste/create-payment', [NepalstePaymentController::class, 'createPayment'])->name('nepalste.create-payment');
        Route::post('yookassa/create-payment', [YooKassaPaymentController::class, 'createPayment'])->name('yookassa.create-payment');
        Route::post('aamarpay/create-payment', [AamarpayPaymentController::class, 'createPayment'])->name('aamarpay.create-payment');
        Route::post('midtrans/create-payment', [MidtransPaymentController::class, 'createPayment'])->name('midtrans.create-payment');
        Route::post('paymentwall/create-payment', [PaymentWallPaymentController::class, 'createPayment'])->name('paymentwall.create-payment');
        Route::post('sspay/create-payment', [SSPayPaymentController::class, 'createPayment'])->name('sspay.create-payment');

        // Payment success/callback routes
        Route::post('payments/skrill/callback', [SkrillPaymentController::class, 'callback'])->name('skrill.callback');
        Route::get('payments/paytr/success', [PayTRPaymentController::class, 'success'])->name('paytr.success');
        Route::get('payments/paytr/failure', [PayTRPaymentController::class, 'failure'])->name('paytr.failure');
        Route::get('payments/mollie/success', [MolliePaymentController::class, 'success'])->name('mollie.success');
        Route::post('payments/mollie/callback', [MolliePaymentController::class, 'callback'])->name('mollie.callback');
        Route::match(['GET', 'POST'], 'payments/toyyibpay/success', [ToyyibPayPaymentController::class, 'success'])->name('toyyibpay.success');
        Route::post('payments/toyyibpay/callback', [ToyyibPayPaymentController::class, 'callback'])->name('toyyibpay.callback');
        Route::post('payments/iyzipay/callback', [IyzipayPaymentController::class, 'callback'])->name('iyzipay.callback');
        Route::get('payments/ozow/success', [OzowPaymentController::class, 'success'])->name('ozow.success');
        Route::post('payments/ozow/callback', [OzowPaymentController::class, 'callback'])->name('ozow.callback');
        Route::get('payments/payhere/success', [PayHerePaymentController::class, 'success'])->name('payhere.success');
        Route::post('payments/payhere/callback', [PayHerePaymentController::class, 'callback'])->name('payhere.callback');
        Route::get('payments/cinetpay/success', [CinetPayPaymentController::class, 'success'])->name('cinetpay.success');
        Route::post('payments/cinetpay/callback', [CinetPayPaymentController::class, 'callback'])->name('cinetpay.callback');
        Route::get('payments/paiement/success', [PaiementPaymentController::class, 'success'])->name('paiement.success');
        Route::post('payments/paiement/callback', [PaiementPaymentController::class, 'callback'])->name('paiement.callback');
        Route::post('payments/midtrans/callback', [MidtransPaymentController::class, 'callback'])->name('midtrans.callback');
        Route::post('paymentwall/process', [PaymentWallPaymentController::class, 'processPayment'])->name('paymentwall.process');
        Route::get('payments/sspay/success', [SSPayPaymentController::class, 'success'])->name('sspay.success');
        Route::post('payments/sspay/callback', [SSPayPaymentController::class, 'callback'])->name('sspay.callback');
        Route::get('mercadopago/success', [MercadoPagoController::class, 'success'])->name('mercadopago.success');
        Route::get('mercadopago/failure', [MercadoPagoController::class, 'failure'])->name('mercadopago.failure');
        Route::get('mercadopago/pending', [MercadoPagoController::class, 'pending'])->name('mercadopago.pending');
        Route::post('mercadopago/webhook', [MercadoPagoController::class, 'webhook'])->name('mercadopago.webhook');
        Route::post('authorizenet/test-connection', [AuthorizeNetPaymentController::class, 'testConnection'])->name('authorizenet.test-connection');
    });

    // NEW UNIQUE ROUTES (FIXING 404)
    Route::get('employee-check', [\App\Http\Controllers\EmployeeCheckController::class, 'index'])->name('hr.employee-check.index');
    Route::post('employee-check/process', [\App\Http\Controllers\EmployeeCheckController::class, 'process'])->name('hr.employee-check.process');
    Route::get('employee-check/download', [\App\Http\Controllers\EmployeeCheckController::class, 'download'])->name('hr.employee-check.download');

    Route::get('attendance-module', [\App\Http\Controllers\AttendanceManagementController::class, 'index'])->name('hr.attendance.module');
    Route::get('attendance-module/grid-data', [\App\Http\Controllers\AttendanceManagementController::class, 'getGridData'])->name('hr.attendance.grid-data');
    Route::post('attendance-module/update-record', [\App\Http\Controllers\AttendanceManagementController::class, 'updateRecord'])->name('hr.attendance.update-record');
    Route::post('attendance-module/bulk-present', [\App\Http\Controllers\AttendanceManagementController::class, 'bulkPresent'])->name('hr.attendance.bulk-present');

    Route::get('mispunch', [\App\Http\Controllers\BiometricAttendanceSyncController::class, 'index'])->name('hr.attendance.sync');
    Route::post('mispunch', [\App\Http\Controllers\BiometricAttendanceSyncController::class, 'sync'])->name('hr.attendance.sync.process');
    Route::put('mispunch/{record}', [\App\Http\Controllers\BiometricAttendanceSyncController::class, 'updateRecord'])->name('hr.attendance.sync.update');
    Route::post('mispunch/bulk-update', [\App\Http\Controllers\BiometricAttendanceSyncController::class, 'bulkUpdate'])->name('hr.attendance.sync.bulk-update');

    // Legacy URL redirects (old bookmarks / links)
    Route::get('attendance-sync', fn () => redirect()->route('hr.attendance.sync', request()->query()));
    Route::get('attendance-sync-engine', fn () => redirect()->route('hr.attendance.sync', request()->query()));
    Route::post('attendance-sync', [\App\Http\Controllers\BiometricAttendanceSyncController::class, 'sync']);
    Route::post('attendance-sync-engine', [\App\Http\Controllers\BiometricAttendanceSyncController::class, 'sync']);
    Route::put('attendance-sync/{record}', [\App\Http\Controllers\BiometricAttendanceSyncController::class, 'updateRecord']);
    Route::put('attendance-sync-engine/{record}', [\App\Http\Controllers\BiometricAttendanceSyncController::class, 'updateRecord']);
    Route::post('attendance-sync/bulk-update', [\App\Http\Controllers\BiometricAttendanceSyncController::class, 'bulkUpdate']);
    Route::post('attendance-sync-engine/bulk-update', [\App\Http\Controllers\BiometricAttendanceSyncController::class, 'bulkUpdate']);
    Route::post('payslips/{payslip}/regenerate', [\App\Http\Controllers\PayslipController::class, 'regenerate'])->name('hr.payslips.regenerate');
    Route::get('payroll/earning-deduction-entry', [\App\Http\Controllers\MonthlyIncentiveController::class, 'index'])->name('hr.earning-deduction.index');
    Route::get('payroll/earning-deduction-entry/employee-details/{id}', [\App\Http\Controllers\MonthlyIncentiveController::class, 'getEmployeeDetails'])->name('hr.earning-deduction.employee-details');
    Route::get('payroll/earning-deduction-entry/employee-history/{id}', [\App\Http\Controllers\MonthlyIncentiveController::class, 'getEmployeeHistory'])->name('hr.earning-deduction.employee-history');
    Route::post('payroll/earning-deduction-entry/store', [\App\Http\Controllers\MonthlyIncentiveController::class, 'store'])->name('hr.earning-deduction.store');
    Route::get('payroll/employee-salary', [\App\Http\Controllers\SalaryPayrollEmployeeSalaryController::class, 'index'])->middleware('permission:view-employee-salaries|manage-employee-salaries|manage-any-employee-salaries|manage-own-employee-salaries')->name('hr.salary-payroll.employee-salary.index');
    Route::post('payroll/employee-salary/calculate', [\App\Http\Controllers\SalaryPayrollEmployeeSalaryController::class, 'calculate'])->middleware('permission:view-employee-salaries|manage-employee-salaries|create-employee-salaries|edit-employee-salaries')->name('hr.salary-payroll.employee-salary.calculate');
    Route::post('payroll/employee-salary', [\App\Http\Controllers\SalaryPayrollEmployeeSalaryController::class, 'store'])->middleware('permission:create-employee-salaries|edit-employee-salaries|manage-employee-salaries')->name('hr.salary-payroll.employee-salary.store');
    Route::put('payroll/employee-salary/{employeeSalary}', [\App\Http\Controllers\SalaryPayrollEmployeeSalaryController::class, 'update'])->middleware('permission:edit-employee-salaries|manage-employee-salaries')->name('hr.salary-payroll.employee-salary.update');
    Route::post('payroll/employee-salary/bulk', [\App\Http\Controllers\SalaryPayrollEmployeeSalaryController::class, 'bulkStore'])->middleware('permission:create-employee-salaries|edit-employee-salaries|manage-employee-salaries')->name('hr.salary-payroll.employee-salary.bulk');
    Route::get('payroll/employee-salary/{employee}/history', [\App\Http\Controllers\SalaryPayrollEmployeeSalaryController::class, 'history'])->middleware('permission:view-employee-salaries|manage-employee-salaries|manage-any-employee-salaries|manage-own-employee-salaries')->name('hr.salary-payroll.employee-salary.history');
    Route::post('payroll/employee-salary/{employee}/increment', [\App\Http\Controllers\SalaryPayrollEmployeeSalaryController::class, 'increment'])->middleware('permission:create-employee-salaries|edit-employee-salaries|manage-employee-salaries')->name('hr.salary-payroll.employee-salary.increment');
    Route::get('payroll/salary-increment', [\App\Http\Controllers\SalaryPayrollIncrementController::class, 'index'])->middleware('permission:view-employee-salaries|manage-employee-salaries|manage-any-employee-salaries|manage-own-employee-salaries')->name('hr.salary-payroll.salary-increment.index');
    Route::post('payroll/salary-increment/preview', [\App\Http\Controllers\SalaryPayrollIncrementController::class, 'preview'])->middleware('permission:view-employee-salaries|create-employee-salaries|edit-employee-salaries|manage-employee-salaries')->name('hr.salary-payroll.salary-increment.preview');
    Route::post('payroll/salary-increment/apply', [\App\Http\Controllers\SalaryPayrollIncrementController::class, 'apply'])->middleware('permission:create-employee-salaries|edit-employee-salaries|manage-employee-salaries')->name('hr.salary-payroll.salary-increment.apply');
    Route::redirect('monthly-incentives-entry', '/payroll/earning-deduction-entry');
    Route::get('monthly-incentives-entry/employee-details/{id}', fn ($id) => redirect()->route('hr.earning-deduction.employee-details', $id));
    Route::get('monthly-incentives-entry/employee-history/{id}', fn ($id) => redirect()->route('hr.earning-deduction.employee-history', $id));
    Route::get('daily-production-attendance-entry', [\App\Http\Controllers\DailyProductionAttendanceEntryController::class, 'index'])->name('hr.daily-production-attendance-entry.index');
    Route::get('daily-production-attendance-entry/employee-details/{id}', [\App\Http\Controllers\DailyProductionAttendanceEntryController::class, 'getEmployeeDetails'])->name('hr.daily-production-attendance-entry.employee-details');
    Route::post('daily-production-attendance-entry/store', [\App\Http\Controllers\DailyProductionAttendanceEntryController::class, 'store'])->name('hr.daily-production-attendance-entry.store');
    Route::delete('daily-production-attendance-entry/{id}', [\App\Http\Controllers\DailyProductionAttendanceEntryController::class, 'destroy'])->name('hr.daily-production-attendance-entry.destroy');

    // All other routes require plan access check
    Route::middleware('plan.access')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/redirect', [DashboardController::class, 'redirectToFirstAvailablePage'])->name('dashboard.redirect');

        Route::get('media-library', function () {
            return Inertia::render('media-library');
        })->name('media-library');



        // Media Library API routes
        Route::get('api/media', [MediaController::class, 'index'])->middleware('permission:manage-media')->name('api.media.index');
        Route::post('api/media/batch', [MediaController::class, 'batchStore'])->middleware('permission:create-media')->name('api.media.batch');
        Route::get('api/media/{id}/download', [MediaController::class, 'download'])->middleware('permission:download-media')->name('api.media.download');
        Route::delete('api/media/{id}', [MediaController::class, 'destroy'])->middleware('permission:delete-media')->name('api.media.destroy');
        Route::post('api/media/directories', [MediaController::class, 'createDirectory'])->name('api.media.directories.create');

        // Permissions routes with granular permissions
        Route::middleware('permission:manage-permissions')->group(function () {
            Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:manage-permissions')->name('permissions.index');
            Route::get('permissions/create', [PermissionController::class, 'create'])->middleware('permission:create-permissions')->name('permissions.create');
            Route::post('permissions', [PermissionController::class, 'store'])->middleware('permission:create-permissions')->name('permissions.store');
            Route::get('permissions/{permission}', [PermissionController::class, 'show'])->middleware('permission:view-permissions')->name('permissions.show');
            Route::get('permissions/{permission}/edit', [PermissionController::class, 'edit'])->middleware('permission:edit-permissions')->name('permissions.edit');
            Route::put('permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:edit-permissions')->name('permissions.update');
            Route::patch('permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:edit-permissions');
            Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:delete-permissions')->name('permissions.destroy');
        });

        // Roles routes with granular permissions
        Route::middleware('permission:manage-roles')->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->middleware('permission:manage-roles')->name('roles.index');
            Route::get('roles/create', [RoleController::class, 'create'])->middleware('permission:create-roles')->name('roles.create');
            Route::post('roles', [RoleController::class, 'store'])->middleware('permission:create-roles')->name('roles.store');
            Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:view-roles')->name('roles.show');
            Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:edit-roles')->name('roles.edit');
            Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:edit-roles')->name('roles.update');
            Route::patch('roles/{role}', [RoleController::class, 'update'])->middleware('permission:edit-roles');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:delete-roles')->name('roles.destroy');
        });

        // Users routes with granular permissions
        Route::middleware('permission:manage-users')->group(function () {
            Route::get('users', [UserController::class, 'index'])->middleware('permission:manage-users')->name('users.index');
            Route::get('users/create', [UserController::class, 'create'])->middleware('permission:create-users')->name('users.create');
            Route::post('users', [UserController::class, 'store'])->middleware('permission:create-users')->name('users.store');
            Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:view-users')->name('users.show');
            Route::get('users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:edit-users')->name('users.edit');
            Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:edit-users')->name('users.update');
            Route::patch('users/{user}', [UserController::class, 'update'])->middleware('permission:edit-users');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:delete-users')->name('users.destroy');

            // Additional user routes
            Route::put('users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:reset-password-users')->name('users.reset-password');
            Route::put('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:toggle-status-users')->name('users.toggle-status');
        });


        // HR Module routes
        // Branch routes
        Route::middleware('permission:manage-branches')->group(function () {
            Route::post('branches/set-active', [BranchController::class, 'setActive'])->name('hr.branches.set-active');
            Route::get('branches/download-template', [BranchController::class, 'importTemplate'])->name('hr.branches.import.template');
            Route::post('branches/import', [BranchController::class, 'import'])->middleware('permission:create-branches')->name('hr.branches.import');
            Route::get('branches', [BranchController::class, 'index'])->name('hr.branches.index');
            Route::post('branches', [BranchController::class, 'store'])->middleware('permission:create-branches')->name('hr.branches.store');
            Route::put('branches/{branch}', [BranchController::class, 'update'])->middleware('permission:edit-branches')->name('hr.branches.update');
            Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->middleware('permission:delete-branches')->name('hr.branches.destroy');
            Route::put('branches/{branch}/toggle-status', [BranchController::class, 'toggleStatus'])->middleware('permission:edit-branches')->name('hr.branches.toggle-status');
        });

        // Week Off Routes
        Route::middleware('permission:manage-branches')->group(function () {
            Route::get('week-offs', [\App\Http\Controllers\WeekOffController::class, 'index'])->name('hr.week-offs.index');
            Route::post('week-offs', [\App\Http\Controllers\WeekOffController::class, 'store'])->name('hr.week-offs.store');
            Route::post('week-offs/individual', [\App\Http\Controllers\WeekOffController::class, 'storeIndividual'])->name('hr.week-offs.individual');
        });

        // Department routes
        Route::middleware('permission:manage-departments')->group(function () {
            Route::get('departments/download-template', [DepartmentController::class, 'importTemplate'])->name('hr.departments.import.template');
            Route::post('departments/import', [DepartmentController::class, 'import'])->middleware('permission:create-departments')->name('hr.departments.import');
            Route::post('departments/bulk-copy', [DepartmentController::class, 'bulkCopyToBranches'])->middleware('permission:create-departments')->name('hr.departments.bulk-copy');
            Route::get('departments', [DepartmentController::class, 'index'])->name('hr.departments.index');
            Route::post('departments', [DepartmentController::class, 'store'])->middleware('permission:create-departments')->name('hr.departments.store');
            Route::put('departments/{department}', [DepartmentController::class, 'update'])->middleware('permission:edit-departments')->name('hr.departments.update');
            Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->middleware('permission:delete-departments')->name('hr.departments.destroy');
            Route::put('departments/{department}/toggle-status', [DepartmentController::class, 'toggleStatus'])->middleware('permission:edit-departments')->name('hr.departments.toggle-status');
            Route::post('departments/{department}/copy-to-branches', [DepartmentController::class, 'copyToBranches'])->middleware('permission:create-departments')->name('hr.departments.copy-to-branches');
        });





        // Designation routes
        Route::middleware('permission:manage-designations')->group(function () {
            Route::get('designations/report', [\App\Http\Controllers\DesignationController::class, 'report'])->name('hr.designations.report');
            Route::get('designations/download-template', [\App\Http\Controllers\DesignationController::class, 'importTemplate'])->name('hr.designations.import.template');
            Route::post('designations/import', [\App\Http\Controllers\DesignationController::class, 'import'])->middleware('permission:create-designations')->name('hr.designations.import');
            Route::post('designations/bulk-copy', [\App\Http\Controllers\DesignationController::class, 'bulkCopyToBranches'])->middleware('permission:create-designations')->name('hr.designations.bulk-copy');
            Route::get('designations', [\App\Http\Controllers\DesignationController::class, 'index'])->name('hr.designations.index');
            Route::post('designations', [\App\Http\Controllers\DesignationController::class, 'store'])->middleware('permission:create-designations')->name('hr.designations.store');
            Route::put('designations/{designation}', [\App\Http\Controllers\DesignationController::class, 'update'])->middleware('permission:edit-designations')->name('hr.designations.update');
            Route::delete('designations/{designation}', [\App\Http\Controllers\DesignationController::class, 'destroy'])->middleware('permission:delete-designations')->name('hr.designations.destroy');
            Route::put('designations/{designation}/toggle-status', [\App\Http\Controllers\DesignationController::class, 'toggleStatus'])->middleware('permission:toggle-status-designations')->name('hr.designations.toggle-status');
            Route::post('designations/{designation}/copy-to-branches', [\App\Http\Controllers\DesignationController::class, 'copyToBranches'])->middleware('permission:create-designations')->name('hr.designations.copy-to-branches');
        });

        // Documenttype Routes
        Route::middleware('permission:manage-document-types')->group(function () {
            Route::get('document-types', [\App\Http\Controllers\DocumentTypeController::class, 'index'])->name('hr.document-types.index');
            Route::post('document-types', [\App\Http\Controllers\DocumentTypeController::class, 'store'])->middleware('permission:create-document-types')->name('hr.document-types.store');
            Route::put('document-types/{documentType}', [\App\Http\Controllers\DocumentTypeController::class, 'update'])->middleware('permission:edit-document-types')->name('hr.document-types.update');
            Route::delete('document-types/{documentType}', [\App\Http\Controllers\DocumentTypeController::class, 'destroy'])->middleware('permission:delete-document-types')->name('hr.document-types.destroy');
        });

        // Industrial Masters
        Route::get('sections/report', [\App\Http\Controllers\SectionController::class, 'report'])->name('hr.sections.report');
        Route::put('sections/{section}/toggle-status', [\App\Http\Controllers\SectionController::class, 'toggleStatus'])->name('hr.sections.toggle-status');
        Route::post('sections/bulk-copy', [\App\Http\Controllers\SectionController::class, 'bulkCopyToBranches'])->name('hr.sections.bulk-copy');
        Route::post('sections/{section}/copy-to-branches', [\App\Http\Controllers\SectionController::class, 'copyToBranches'])->name('hr.sections.copy-to-branches');
        Route::resource('sections', \App\Http\Controllers\SectionController::class)->names('hr.sections');
        Route::put('categories/{category}/toggle-status', [\App\Http\Controllers\CategoryController::class, 'toggleStatus'])->name('hr.categories.toggle-status');
        Route::post('categories/bulk-copy', [\App\Http\Controllers\CategoryController::class, 'bulkCopyToBranches'])->name('hr.categories.bulk-copy');
        Route::post('categories/{category}/copy-to-branches', [\App\Http\Controllers\CategoryController::class, 'copyToBranches'])->name('hr.categories.copy-to-branches');
        Route::resource('categories', \App\Http\Controllers\CategoryController::class)->names('hr.categories');
        Route::resource('bank-masters', \App\Http\Controllers\BankMasterController::class)->names('hr.bank-masters');
        Route::resource('pf-masters', \App\Http\Controllers\PfMasterController::class)->names('hr.pf-masters');
        Route::resource('esi-masters', \App\Http\Controllers\EsiMasterController::class)->names('hr.esi-masters');
        Route::resource('resign-reasons', ResignReasonController::class)->names('hr.resign-reasons');
        Route::resource('overtimes', OvertimeController::class)->names('hr.overtimes');
        Route::put('material-items/{material_item}/toggle-status', [\App\Http\Controllers\MaterialItemController::class, 'toggleStatus'])->name('hr.material-items.toggle-status');
        Route::post('material-items/bulk-copy', [\App\Http\Controllers\MaterialItemController::class, 'bulkCopyToBranches'])->name('hr.material-items.bulk-copy');
        Route::post('material-items/{material_item}/copy-to-branches', [\App\Http\Controllers\MaterialItemController::class, 'copyToBranches'])->name('hr.material-items.copy-to-branches');
        Route::resource('material-items', MaterialItemController::class)->names('hr.material-items');
        Route::resource('incentive-types', \App\Http\Controllers\IncentiveDeductionTypeController::class)->names('hr.incentive-types');
        Route::put('incentive-types/{incentiveType}/toggle-status', [\App\Http\Controllers\IncentiveDeductionTypeController::class, 'toggleStatus'])->name('hr.incentive-types.toggle-status');
        Route::post('deduction-types/reorder', [\App\Http\Controllers\DeductionTypeController::class, 'reorder'])->middleware('permission:edit-deduction-types')->name('hr.deduction-types.reorder');
        Route::put('deduction-types/{deduction_type}/toggle-status', [\App\Http\Controllers\DeductionTypeController::class, 'toggleStatus'])->middleware('permission:edit-deduction-types')->name('hr.deduction-types.toggle-status');
        Route::get('deduction-types/active/list', [\App\Http\Controllers\DeductionTypeController::class, 'activeList'])->name('hr.deduction-types.active-list');
        Route::get('deduction-types', [\App\Http\Controllers\DeductionTypeController::class, 'index'])->middleware('permission:view-deduction-types|manage-deduction-types|manage-any-deduction-types|manage-own-deduction-types')->name('hr.deduction-types.index');
        Route::post('deduction-types', [\App\Http\Controllers\DeductionTypeController::class, 'store'])->middleware('permission:create-deduction-types')->name('hr.deduction-types.store');
        Route::put('deduction-types/{deduction_type}', [\App\Http\Controllers\DeductionTypeController::class, 'update'])->middleware('permission:edit-deduction-types')->name('hr.deduction-types.update');
        Route::delete('deduction-types/{deduction_type}', [\App\Http\Controllers\DeductionTypeController::class, 'destroy'])->middleware('permission:delete-deduction-types')->name('hr.deduction-types.destroy');

        // NEW GLOBAL REPORTS ROUTES
        Route::get('reports/daily', [\App\Http\Controllers\ReportController::class, 'dailyReports'])->name('hr.reports.daily');
        Route::get('reports/monthly', [\App\Http\Controllers\ReportController::class, 'monthlyReports'])->name('hr.reports.monthly');
        Route::get('reports/master', [\App\Http\Controllers\ReportController::class, 'masterReports'])->name('hr.reports.master');
        Route::get('reports/mispunch-dedicated', [\App\Http\Controllers\ReportController::class, 'mispunchReport'])->name('hr.reports.mispunch-dedicated');
        Route::get('reports/mispunch-form-pdf', [\App\Http\Controllers\ReportController::class, 'mispunchFormPdf'])->name('hr.reports.mispunch-form-pdf');
        Route::get('reports/mispunch-download-24h', [\App\Http\Controllers\ReportController::class, 'download24hMispunchForms'])->name('hr.reports.mispunch.download_24h');
        Route::get('reports/birthdays-month-pdf', [\App\Http\Controllers\ReportController::class, 'downloadBirthdaysMonthPdf'])->name('hr.reports.birthdays-month');
        Route::get('reports/anniversaries-month-pdf', [\App\Http\Controllers\ReportController::class, 'downloadAnniversariesMonthPdf'])->name('hr.reports.anniversaries-month');
        Route::get('reports/preview', [\App\Http\Controllers\ReportController::class, 'previewData'])->name('hr.reports.preview');
        Route::get('reports/export-excel', [\App\Http\Controllers\ReportController::class, 'exportExcel'])->name('hr.reports.export-excel');

        // Reports Landing Page
        Route::get('reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('hr.reports.index');
        Route::get('reports/downloads', [ReportController::class, 'downloads'])->name('hr.reports.downloads');
        Route::get('reports/downloads-json', [ReportController::class, 'downloadsJson'])->name('hr.reports.downloads-json');
        Route::get('reports/downloads/{id}/status', [ReportController::class, 'downloadStatus'])->name('hr.reports.downloads.status');
        Route::delete('reports/downloads/bulk-delete', [ReportController::class, 'deleteMultipleDownloads'])->name('hr.reports.downloads.bulk-delete');
        Route::delete('reports/downloads/{id}', [ReportController::class, 'deleteDownload'])->name('hr.reports.downloads.delete');
        Route::get('reports/downloads/{id}', [\App\Http\Controllers\ReportController::class, 'downloadFile'])->name('hr.reports.download_file');
        // Legacy URLs (old frontend used /reports/... without hr/ prefix)
        Route::get('reports/downloads-json', [ReportController::class, 'downloadsJson']);
        Route::get('reports/downloads/{id}/status', [ReportController::class, 'downloadStatus']);
        Route::delete('reports/downloads/{id}', [ReportController::class, 'deleteDownload']);
        Route::delete('reports/downloads/bulk-delete', [ReportController::class, 'deleteMultipleDownloads']);
        Route::get('reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
        Route::post('reports/generate-background', [ReportController::class, 'generateBackground'])->name('reports.generate.background');
        Route::get('reports/biometric-dedicated', [BiometricReportController::class, 'generate'])->name('reports.biometric.dedicated');
        Route::get('reports/master-listing', [ReportController::class, 'generateMasterReport'])->name('reports.master');
        Route::get('reports/master-listing', [\App\Http\Controllers\MasterReportController::class, 'index'])->name('hr.reports.master_listing');

        // Payroll Settings Routes
        Route::get('payroll-settings', [\App\Http\Controllers\PayrollSettingController::class, 'index'])->name('hr.payroll-settings.index');
        Route::post('payroll-settings/parameters', [\App\Http\Controllers\PayrollSettingController::class, 'updateParameters'])->name('hr.payroll-settings.parameters.update');
        Route::post('payroll-settings/slabs', [\App\Http\Controllers\PayrollSettingController::class, 'updateSlabs'])->name('hr.payroll-settings.slabs.update');

        // Employee Routes
        Route::middleware('permission:manage-employees')->group(function () {
            Route::get('employees', [EmployeeController::class, 'index'])->name('hr.employees.index');
            Route::get('employees/get-next-id', [EmployeeController::class, 'getNextId'])->name('hr.employees.get-next-id');
            Route::get('employees/branch-masters', [EmployeeController::class, 'branchMasters'])->name('hr.employees.branch-masters');
            Route::get('employees/create', [EmployeeController::class, 'create'])->middleware('permission:create-employees')->name('hr.employees.create');
            Route::get('employees/report/pdf', [EmployeeController::class, 'reportPdf'])->middleware('permission:view-employees')->name('hr.employees.report.pdf');
            Route::get('employees/download-sample', [EmployeeController::class, 'downloadSample'])->middleware('permission:create-employees')->name('hr.employees.download-sample');
            Route::post('employees', [EmployeeController::class, 'store'])->middleware('permission:create-employees')->name('hr.employees.store');
            Route::get('employees/{employee}', [EmployeeController::class, 'show'])->middleware('permission:view-employees')->name('hr.employees.show');
            Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->middleware('permission:edit-employees')->name('hr.employees.edit');
            Route::put('employees/{employee}', [EmployeeController::class, 'update'])->middleware('permission:edit-employees')->name('hr.employees.update');
            Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:delete-employees')->name('hr.employees.destroy');
            Route::put('employees/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus'])->middleware('permission:edit-employees')->name('hr.employees.toggle-status');
            Route::put('employees/{employee}/change-password', [EmployeeController::class, 'changePassword'])->middleware('permission:edit-employees')->name('hr.employees.change-password');
            Route::delete('employees/{userId}/documents/{documentId}', [EmployeeController::class, 'deleteDocument'])->middleware('permission:edit-employees')->name('hr.employees.documents.destroy');
            Route::put('employees/{employee}/documents/{documentId}/approve', [EmployeeController::class, 'approveDocument'])->middleware('permission:edit-employees')->name('hr.employees.documents.approve');
            Route::put('employees/{employee}/documents/{documentId}/reject', [EmployeeController::class, 'rejectDocument'])->middleware('permission:edit-employees')->name('hr.employees.documents.reject');
            Route::get('employees/{userId}/documents/{documentId}/download', [EmployeeController::class, 'downloadDocument'])->middleware('permission:view-employees')->name('hr.employees.documents.download');
            Route::post('employees/import', [EmployeeController::class, 'import'])->middleware('permission:create-employees')->name('hr.employees.import');
            Route::get('employees/{user}/export', [EmployeeController::class, 'export'])->middleware('permission:view-employees')->name('hr.employees.export');
        });

        // Award Type Routes
        Route::middleware('permission:manage-award-types')->group(function () {
            Route::get('award-types', [AwardTypeController::class, 'index'])->name('hr.award-types.index');
            Route::post('award-types', [AwardTypeController::class, 'store'])->middleware('permission:create-award-types')->name('hr.award-types.store');
            Route::put('award-types/{awardType}', [AwardTypeController::class, 'update'])->middleware('permission:edit-award-types')->name('hr.award-types.update');
            Route::delete('award-types/{awardType}', [AwardTypeController::class, 'destroy'])->middleware('permission:delete-award-types')->name('hr.award-types.destroy');
            Route::put('award-types/{awardType}/toggle-status', [AwardTypeController::class, 'toggleStatus'])->middleware('permission:edit-award-types')->name('hr.award-types.toggle-status');
        });

        // Award Routes
        Route::middleware('permission:manage-awards')->group(function () {
            Route::get('awards', [AwardController::class, 'index'])->name('hr.awards.index');
            Route::get('awards/create', [AwardController::class, 'create'])->middleware('permission:create-awards')->name('hr.awards.create');
            Route::post('awards', [AwardController::class, 'store'])->middleware('permission:create-awards')->name('hr.awards.store');
            Route::get('awards/{award}', [AwardController::class, 'show'])->middleware('permission:view-awards')->name('hr.awards.show');
            Route::get('awards/{award}/edit', [AwardController::class, 'edit'])->middleware('permission:edit-awards')->name('hr.awards.edit');
            Route::put('awards/{award}', [AwardController::class, 'update'])->middleware('permission:edit-awards')->name('hr.awards.update');
            Route::delete('awards/{award}', [AwardController::class, 'destroy'])->middleware('permission:delete-awards')->name('hr.awards.destroy');
            Route::get('awards/{award}/download-certificate', [AwardController::class, 'downloadCertificate'])->middleware('permission:view-awards')->name('hr.awards.download-certificate');
            Route::get('awards/{award}/download-photo', [AwardController::class, 'downloadPhoto'])->middleware('permission:view-awards')->name('hr.awards.download-photo');
        });


        // Promotion Routes
        Route::middleware('permission:manage-promotions')->group(function () {
            Route::get('promotions', [PromotionController::class, 'index'])->name('hr.promotions.index');
            Route::post('promotions', [PromotionController::class, 'store'])->middleware('permission:create-promotions')->name('hr.promotions.store');
            Route::put('promotions/{promotion}', [PromotionController::class, 'update'])->middleware('permission:edit-promotions')->name('hr.promotions.update');
            Route::delete('promotions/{promotion}', [PromotionController::class, 'destroy'])->middleware('permission:delete-promotions')->name('hr.promotions.destroy');
            Route::get('promotions/{promotion}/download-document', [PromotionController::class, 'downloadDocument'])->middleware('permission:view-promotions')->name('hr.promotions.download-document');
            Route::put('promotions/{promotion}/update-status', [PromotionController::class, 'updateStatus'])->middleware('permission:edit-promotions')->name('hr.promotions.update-status');
        });

        // Resignation Routes
        Route::middleware('permission:manage-resignations')->group(function () {
            Route::get('resignations', [ResignationController::class, 'index'])->name('hr.resignations.index');
            Route::post('resignations', [ResignationController::class, 'store'])->middleware('permission:create-resignations')->name('hr.resignations.store');
            Route::put('resignations/{resignation}', [ResignationController::class, 'update'])->middleware('permission:edit-resignations')->name('hr.resignations.update');
            Route::delete('resignations/{resignation}', [ResignationController::class, 'destroy'])->middleware('permission:delete-resignations')->name('hr.resignations.destroy');
            Route::get('resignations/{resignation}/download-document', [ResignationController::class, 'downloadDocument'])->middleware('permission:view-resignations')->name('hr.resignations.download-document');
            Route::put('resignations/{resignation}/change-status', [ResignationController::class, 'changeStatus'])->middleware('permission:edit-resignations')->name('hr.resignations.change-status');
        });

        // Termination Routes
        Route::middleware('permission:manage-terminations')->group(function () {
            Route::get('terminations', [TerminationController::class, 'index'])->name('hr.terminations.index');
            Route::post('terminations', [TerminationController::class, 'store'])->middleware('permission:create-terminations')->name('hr.terminations.store');
            Route::put('terminations/{termination}', [TerminationController::class, 'update'])->middleware('permission:edit-terminations')->name('hr.terminations.update');
            Route::delete('terminations/{termination}', [TerminationController::class, 'destroy'])->middleware('permission:delete-terminations')->name('hr.terminations.destroy');
            Route::get('terminations/{termination}/download-document', [TerminationController::class, 'downloadDocument'])->middleware('permission:view-terminations')->name('hr.terminations.download-document');
            Route::put('terminations/{termination}/change-status', [TerminationController::class, 'changeStatus'])->middleware('permission:edit-terminations')->name('hr.terminations.change-status');
        });

        // Warning Routes
        Route::middleware('permission:manage-warnings')->group(function () {
            Route::get('warnings', [WarningController::class, 'index'])->name('hr.warnings.index');
            Route::post('warnings', [WarningController::class, 'store'])->middleware('permission:create-warnings')->name('hr.warnings.store');
            Route::put('warnings/{warning}', [WarningController::class, 'update'])->middleware('permission:edit-warnings')->name('hr.warnings.update');
            Route::delete('warnings/{warning}', [WarningController::class, 'destroy'])->middleware('permission:delete-warnings')->name('hr.warnings.destroy');
            Route::get('warnings/{warning}/download-document', [WarningController::class, 'downloadDocument'])->middleware('permission:view-warnings')->name('hr.warnings.download-document');
            Route::put('warnings/{warning}/change-status', [WarningController::class, 'changeStatus'])->middleware('permission:edit-warnings')->name('hr.warnings.change-status');
            Route::put('warnings/{warning}/update-improvement-plan', [WarningController::class, 'updateImprovementPlan'])->middleware('permission:edit-warnings')->name('hr.warnings.update-improvement-plan');
        });

        // Trip Routes
        Route::middleware('permission:manage-trips')->group(function () {
            Route::get('trips', [TripController::class, 'index'])->name('hr.trips.index');
            Route::post('trips', [TripController::class, 'store'])->middleware('permission:create-trips')->name('hr.trips.store');
            Route::put('trips/{trip}', [TripController::class, 'update'])->middleware('permission:edit-trips')->name('hr.trips.update');
            Route::delete('trips/{trip}', [TripController::class, 'destroy'])->middleware('permission:delete-trips')->name('hr.trips.destroy');
            Route::get('trips/{trip}/download-document', [TripController::class, 'downloadDocument'])->middleware('permission:view-trips')->name('hr.trips.download-document');
            Route::put('trips/{trip}/change-status', [TripController::class, 'changeStatus'])->middleware('permission:edit-trips')->name('hr.trips.change-status');
            Route::put('trips/{trip}/update-advance-status', [TripController::class, 'updateAdvanceStatus'])->middleware('permission:edit-trips')->name('hr.trips.update-advance-status');
            Route::put('trips/{trip}/update-reimbursement-status', [TripController::class, 'updateReimbursementStatus'])->middleware('permission:edit-trips')->name('hr.trips.update-reimbursement-status');

            // Trip Expenses Routes
            Route::get('trips/{trip}/expenses', [TripController::class, 'showExpenses'])->middleware('permission:manage-trip-expenses')->name('hr.trips.expenses');
            Route::post('trips/{trip}/expenses', [TripController::class, 'storeExpense'])->middleware('permission:manage-trip-expenses')->name('hr.trips.expenses.store');
            Route::put('trips/{trip}/expenses/{expense}', [TripController::class, 'updateExpense'])->middleware('permission:manage-trip-expenses')->name('hr.trips.expenses.update');
            Route::delete('trips/{trip}/expenses/{expense}', [TripController::class, 'destroyExpense'])->middleware('permission:manage-trip-expenses')->name('hr.trips.expenses.destroy');
            Route::get('trips/{trip}/expenses/{expense}/download-receipt', [TripController::class, 'downloadReceipt'])->middleware('permission:manage-trip-expenses')->name('hr.trips.expenses.download-receipt');
        });

        // Complaint Routes
        Route::middleware('permission:manage-complaints')->group(function () {
            Route::get('complaints', [ComplaintController::class, 'index'])->name('hr.complaints.index');
            Route::post('complaints', [ComplaintController::class, 'store'])->middleware('permission:create-complaints')->name('hr.complaints.store');
            Route::put('complaints/{complaint}', [ComplaintController::class, 'update'])->middleware('permission:edit-complaints')->name('hr.complaints.update');
            Route::delete('complaints/{complaint}', [ComplaintController::class, 'destroy'])->middleware('permission:delete-complaints')->name('hr.complaints.destroy');
            Route::get('complaints/{complaint}/download-document', [ComplaintController::class, 'downloadDocument'])->middleware('permission:view-complaints')->name('hr.complaints.download-document');
            Route::put('complaints/{complaint}/change-status', [ComplaintController::class, 'changeStatus'])->middleware('permission:edit-complaints')->name('hr.complaints.change-status');
            Route::put('complaints/{complaint}/assign', [ComplaintController::class, 'assignComplaint'])->middleware('permission:assign-complaints')->name('hr.complaints.assign');
            Route::put('complaints/{complaint}/resolve', [ComplaintController::class, 'resolveComplaint'])->middleware('permission:resolve-complaints')->name('hr.complaints.resolve');
            Route::put('complaints/{complaint}/follow-up', [ComplaintController::class, 'updateFollowUp'])->middleware('permission:resolve-complaints')->name('hr.complaints.follow-up');
        });

        // Employee Transfer Routes
        Route::middleware('permission:manage-employee-transfers')->group(function () {
            Route::get('transfers', [EmployeeTransferController::class, 'index'])->name('hr.transfers.index');
            Route::post('transfers', [EmployeeTransferController::class, 'store'])->middleware('permission:create-employee-transfers')->name('hr.transfers.store');
            Route::put('transfers/{transfer}', [EmployeeTransferController::class, 'update'])->middleware('permission:edit-employee-transfers')->name('hr.transfers.update');
            Route::delete('transfers/{transfer}', [EmployeeTransferController::class, 'destroy'])->middleware('permission:delete-employee-transfers')->name('hr.transfers.destroy');
            Route::get('transfers/{transfer}/download-document', [EmployeeTransferController::class, 'downloadDocument'])->middleware('permission:view-employee-transfers')->name('hr.transfers.download-document');
            Route::put('transfers/{transfer}/approve', [EmployeeTransferController::class, 'approve'])->middleware('permission:approve-employee-transfers')->name('hr.transfers.approve');
            Route::put('transfers/{transfer}/reject', [EmployeeTransferController::class, 'reject'])->middleware('permission:reject-employee-transfers')->name('hr.transfers.reject');
            Route::get('transfers/get-department/{branchId}', [EmployeeTransferController::class, 'getDepartment'])->name('hr.transfers.getdepartment');
            Route::get('transfers/get-designation/{departmentId}', [EmployeeTransferController::class, 'getDesignation'])->name('hr.transfers.getdesignation');
        });

        // Holiday Routes
        Route::middleware('permission:manage-holidays')->group(function () {
            Route::get('holidays', [HolidayController::class, 'index'])->name('hr.holidays.index');
            Route::get('holidays/calendar', [HolidayController::class, 'calendar'])->name('hr.holidays.calendar');
            Route::post('holidays', [HolidayController::class, 'store'])->middleware('permission:create-holidays')->name('hr.holidays.store');
            Route::put('holidays/{holiday}', [HolidayController::class, 'update'])->middleware('permission:edit-holidays')->name('hr.holidays.update');
            Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->middleware('permission:delete-holidays')->name('hr.holidays.destroy');
            Route::get('holidays/export/pdf', [HolidayController::class, 'exportPdf'])->name('hr.holidays.export.pdf');
            Route::get('holidays/export/ical', [HolidayController::class, 'exportIcal'])->name('hr.holidays.export.ical');

            // Import routes
            Route::get('holidays/import-template', [HolidayController::class, 'exportTemplate'])->name('hr.holidays.import.template');
            Route::post('holidays/import', [HolidayController::class, 'import'])->middleware('permission:create-holidays')->name('hr.holidays.import');
        });

        // Announcement Routes
        Route::middleware('permission:manage-announcements')->group(function () {
            Route::get('announcements', [AnnouncementController::class, 'index'])->name('hr.announcements.index');
            Route::get('announcements/dashboard', [AnnouncementController::class, 'dashboard'])->name('hr.announcements.dashboard');
            Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])->name('hr.announcements.show');
            Route::post('announcements', [AnnouncementController::class, 'store'])->middleware('permission:create-announcements')->name('hr.announcements.store');
            Route::put('announcements/{announcement}', [AnnouncementController::class, 'update'])->middleware('permission:edit-announcements')->name('hr.announcements.update');
            Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->middleware('permission:delete-announcements')->name('hr.announcements.destroy');
            Route::get('announcements/{announcement}/download-attachment', [AnnouncementController::class, 'downloadAttachment'])->name('hr.announcements.download-attachment');
            Route::get('announcements/{announcement}/statistics', [AnnouncementController::class, 'viewStatistics'])->name('hr.announcements.statistics');
            Route::post('announcements/{announcement}/mark-as-read', [AnnouncementController::class, 'markAsRead'])->name('hr.announcements.mark-as-read');
            Route::get('announcements/get-departments/{branchIds}', [AnnouncementController::class, 'getDepartments'])->name('hr.announcements.get-departments');
        });

        // Asset Type Routes
        Route::middleware('permission:manage-asset-types')->group(function () {
            Route::get('asset-types', [AssetTypeController::class, 'index'])->name('hr.asset-types.index');
            Route::post('asset-types', [AssetTypeController::class, 'store'])->middleware('permission:create-asset-types')->name('hr.asset-types.store');
            Route::put('asset-types/{assetType}', [AssetTypeController::class, 'update'])->middleware('permission:edit-asset-types')->name('hr.asset-types.update');
            Route::delete('asset-types/{assetType}', [AssetTypeController::class, 'destroy'])->middleware('permission:delete-asset-types')->name('hr.asset-types.destroy');
        });

        // Asset Routes
        Route::middleware('permission:manage-assets')->group(function () {
            Route::get('assets', [AssetController::class, 'index'])->name('hr.assets.index');
            Route::get('assets/dashboard', [AssetController::class, 'dashboard'])->name('hr.assets.dashboard');
            Route::get('assets/depreciation-report', [AssetController::class, 'depreciationReport'])->name('hr.assets.depreciation-report');
            Route::get('assets/export-depreciation-csv', [AssetController::class, 'exportDepreciationCsv'])->name('hr.assets.export-depreciation-csv');
            Route::get('assets/export-depreciation-csv', [AssetController::class, 'exportDepreciationCsv'])->name('hr.assets.export-depreciation-csv');
            Route::get('assets/{asset}', [AssetController::class, 'show'])->name('hr.assets.show');
            Route::post('assets', [AssetController::class, 'store'])->middleware('permission:create-assets')->name('hr.assets.store');
            Route::put('assets/{asset}', [AssetController::class, 'update'])->middleware('permission:edit-assets')->name('hr.assets.update');
            Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->middleware('permission:delete-assets')->name('hr.assets.destroy');
            Route::post('assets/{asset}/assign', [AssetController::class, 'assign'])->middleware('permission:assign-assets')->name('hr.assets.assign');
            Route::post('assets/{asset}/return', [AssetController::class, 'returnAsset'])->middleware('permission:assign-assets')->name('hr.assets.return');
            Route::post('assets/{asset}/schedule-maintenance', [AssetController::class, 'scheduleMaintenance'])->middleware('permission:manage-asset-maintenance')->name('hr.assets.schedule-maintenance');
            Route::put('assets/maintenance/{maintenance}', [AssetController::class, 'updateMaintenance'])->middleware('permission:manage-asset-maintenance')->name('hr.assets.update-maintenance');
            Route::get('assets/{asset}/download-document', [AssetController::class, 'downloadDocument'])->name('hr.assets.download-document');
            Route::get('assets/{asset}/view-image', [AssetController::class, 'viewImage'])->name('hr.assets.view-image');
        });

        // Training Type Routes
        Route::middleware('permission:manage-training-types')->group(function () {
            Route::get('training-types', [TrainingTypeController::class, 'index'])->name('hr.training-types.index');
            Route::post('training-types', [TrainingTypeController::class, 'store'])->middleware('permission:create-training-types')->name('hr.training-types.store');
            Route::put('training-types/{trainingType}', [TrainingTypeController::class, 'update'])->middleware('permission:edit-training-types')->name('hr.training-types.update');
            Route::put('training-types/{trainingType}/assign-departments', [TrainingTypeController::class, 'assignDepartments'])->middleware('permission:edit-training-types')->name('hr.training-types.assign-departments');
            Route::delete('training-types/{trainingType}', [TrainingTypeController::class, 'destroy'])->middleware('permission:delete-training-types')->name('hr.training-types.destroy');
        });

        // Training Program Routes
        Route::middleware('permission:manage-training-programs')->group(function () {
            Route::get('training-programs', [TrainingProgramController::class, 'index'])->name('hr.training-programs.index');
            Route::get('training-programs/{trainingProgram}', [TrainingProgramController::class, 'show'])->name('hr.training-programs.show');
            Route::post('training-programs', [TrainingProgramController::class, 'store'])->middleware('permission:create-training-programs')->name('hr.training-programs.store');
            Route::put('training-programs/{trainingProgram}', [TrainingProgramController::class, 'update'])->middleware('permission:edit-training-programs')->name('hr.training-programs.update');
            Route::delete('training-programs/{trainingProgram}', [TrainingProgramController::class, 'destroy'])->middleware('permission:delete-training-programs')->name('hr.training-programs.destroy');
            Route::get('training-programs/{trainingProgram}/download-materials', [TrainingProgramController::class, 'downloadMaterials'])->name('hr.training-programs.download-materials');
        });

        // Training Session Routes
        Route::middleware('permission:manage-training-sessions')->group(function () {
            Route::get('training-sessions', [TrainingSessionController::class, 'index'])->name('hr.training-sessions.index');
            Route::get('training-sessions/calendar', [TrainingSessionController::class, 'calendar'])->name('hr.training-sessions.calendar');
            Route::get('training-sessions/{trainingSession}', [TrainingSessionController::class, 'show'])->name('hr.training-sessions.show');
            Route::post('training-sessions', [TrainingSessionController::class, 'store'])->middleware('permission:create-training-sessions')->name('hr.training-sessions.store');
            Route::put('training-sessions/{trainingSession}', [TrainingSessionController::class, 'update'])->middleware('permission:edit-training-sessions')->name('hr.training-sessions.update');
            Route::delete('training-sessions/{trainingSession}', [TrainingSessionController::class, 'destroy'])->middleware('permission:delete-training-sessions')->name('hr.training-sessions.destroy');
            Route::post('training-sessions/{trainingSession}/update-attendance', [TrainingSessionController::class, 'updateAttendance'])->middleware('permission:manage-attendance')->name('hr.training-sessions.update-attendance');
        });

        // Employee Training Routes
        Route::middleware('permission:manage-employee-trainings')->group(function () {
            Route::get('employee-trainings', [EmployeeTrainingController::class, 'index'])->name('hr.employee-trainings.index');
            Route::get('employee-trainings/dashboard', [EmployeeTrainingController::class, 'dashboard'])->name('hr.employee-trainings.dashboard');
            Route::get('employee-trainings/{employeeTraining}', [EmployeeTrainingController::class, 'show'])->middleware('permission:view-employee-trainings')->name('hr.employee-trainings.show');
            Route::post('employee-trainings', [EmployeeTrainingController::class, 'store'])->middleware('permission:create-employee-trainings')->name('hr.employee-trainings.store');
            Route::put('employee-trainings/{employeeTraining}', [EmployeeTrainingController::class, 'update'])->middleware('permission:edit-employee-trainings')->name('hr.employee-trainings.update');
            Route::delete('employee-trainings/{employeeTraining}', [EmployeeTrainingController::class, 'destroy'])->middleware('permission:delete-employee-trainings')->name('hr.employee-trainings.destroy');
            Route::get('employee-trainings/{employeeTraining}/download-certification', [EmployeeTrainingController::class, 'downloadCertification'])->middleware('permission:view-employee-trainings')->name('hr.employee-trainings.download-certification');
            Route::post('employee-trainings/bulk-assign', [EmployeeTrainingController::class, 'bulkAssign'])->middleware('permission:create-employee-trainings')->name('hr.employee-trainings.bulk-assign');
            Route::post('employee-trainings/{employeeTraining}/record-assessment', [EmployeeTrainingController::class, 'recordAssessment'])->middleware('permission:record-assessment-results')->name('hr.employee-trainings.record-assessment');
        });

        // Training Assessment Routes
        Route::middleware('permission:manage-assessments')->group(function () {
            Route::get('training-assessments', [TrainingAssessmentController::class, 'index'])->name('hr.training-assessments.index');
            Route::get('training-assessments/{trainingAssessment}', [TrainingAssessmentController::class, 'show'])->name('hr.training-assessments.show');
            Route::post('training-assessments', [TrainingAssessmentController::class, 'store'])->name('hr.training-assessments.store');
            Route::put('training-assessments/{trainingAssessment}', [TrainingAssessmentController::class, 'update'])->name('hr.training-assessments.update');
            Route::delete('training-assessments/{trainingAssessment}', [TrainingAssessmentController::class, 'destroy'])->name('hr.training-assessments.destroy');
        });

        // Performance Module Routes

        // Performance Indicator Categories
        Route::middleware('permission:manage-performance-indicator-categories')->group(function () {
            Route::get('performance/indicator-categories', [PerformanceIndicatorCategoryController::class, 'index'])->name('hr.performance.indicator-categories.index');
            Route::post('performance/indicator-categories', [PerformanceIndicatorCategoryController::class, 'store'])->middleware('permission:create-performance-indicator-categories')->name('hr.performance.indicator-categories.store');
            Route::put('performance/indicator-categories/{indicatorCategory}', [PerformanceIndicatorCategoryController::class, 'update'])->middleware('permission:edit-performance-indicator-categories')->name('hr.performance.indicator-categories.update');
            Route::delete('performance/indicator-categories/{indicatorCategory}', [PerformanceIndicatorCategoryController::class, 'destroy'])->middleware('permission:delete-performance-indicator-categories')->name('hr.performance.indicator-categories.destroy');
            Route::put('performance/indicator-categories/{indicatorCategory}/toggle-status', [PerformanceIndicatorCategoryController::class, 'toggleStatus'])->middleware('permission:edit-performance-indicator-categories')->name('hr.performance.indicator-categories.toggle-status');
        });

        // Performance Indicators
        Route::middleware('permission:manage-performance-indicators')->group(function () {
            Route::get('performance/indicators', [PerformanceIndicatorController::class, 'index'])->name('hr.performance.indicators.index');
            Route::post('performance/indicators', [PerformanceIndicatorController::class, 'store'])->middleware('permission:create-performance-indicators')->name('hr.performance.indicators.store');
            Route::put('performance/indicators/{indicator}', [PerformanceIndicatorController::class, 'update'])->middleware('permission:edit-performance-indicators')->name('hr.performance.indicators.update');
            Route::delete('performance/indicators/{indicator}', [PerformanceIndicatorController::class, 'destroy'])->middleware('permission:delete-performance-indicators')->name('hr.performance.indicators.destroy');
            Route::put('performance/indicators/{indicator}/toggle-status', [PerformanceIndicatorController::class, 'toggleStatus'])->middleware('permission:edit-performance-indicators')->name('hr.performance.indicators.toggle-status');
        });

        // Goal Types
        Route::middleware('permission:manage-goal-types')->group(function () {
            Route::get('performance/goal-types', [GoalTypeController::class, 'index'])->name('hr.performance.goal-types.index');
            Route::post('performance/goal-types', [GoalTypeController::class, 'store'])->middleware('permission:create-goal-types')->name('hr.performance.goal-types.store');
            Route::put('performance/goal-types/{goalType}', [GoalTypeController::class, 'update'])->middleware('permission:edit-goal-types')->name('hr.performance.goal-types.update');
            Route::delete('performance/goal-types/{goalType}', [GoalTypeController::class, 'destroy'])->middleware('permission:delete-goal-types')->name('hr.performance.goal-types.destroy');
            Route::put('performance/goal-types/{goalType}/toggle-status', [GoalTypeController::class, 'toggleStatus'])->middleware('permission:edit-goal-types')->name('hr.performance.goal-types.toggle-status');
        });

        // Employee Goals
        Route::middleware('permission:manage-employee-goals')->group(function () {
            Route::get('performance/employee-goals', [EmployeeGoalController::class, 'index'])->name('hr.performance.employee-goals.index');
            Route::post('performance/employee-goals', [EmployeeGoalController::class, 'store'])->middleware('permission:create-employee-goals')->name('hr.performance.employee-goals.store');
            Route::put('performance/employee-goals/{employeeGoal}', [EmployeeGoalController::class, 'update'])->middleware('permission:edit-employee-goals')->name('hr.performance.employee-goals.update');
            Route::delete('performance/employee-goals/{employeeGoal}', [EmployeeGoalController::class, 'destroy'])->middleware('permission:delete-employee-goals')->name('hr.performance.employee-goals.destroy');
            Route::put('performance/employee-goals/{employeeGoal}/progress', [EmployeeGoalController::class, 'updateProgress'])->middleware('permission:edit-employee-goals')->name('hr.performance.employee-goals.update-progress');
        });

        // Review Cycles
        Route::middleware('permission:manage-review-cycles')->group(function () {
            Route::get('performance/review-cycles', [ReviewCycleController::class, 'index'])->name('hr.performance.review-cycles.index');
            Route::post('performance/review-cycles', [ReviewCycleController::class, 'store'])->middleware('permission:create-review-cycles')->name('hr.performance.review-cycles.store');
            Route::put('performance/review-cycles/{reviewCycle}', [ReviewCycleController::class, 'update'])->middleware('permission:edit-review-cycles')->name('hr.performance.review-cycles.update');
            Route::delete('performance/review-cycles/{reviewCycle}', [ReviewCycleController::class, 'destroy'])->middleware('permission:delete-review-cycles')->name('hr.performance.review-cycles.destroy');
            Route::put('performance/review-cycles/{reviewCycle}/toggle-status', [ReviewCycleController::class, 'toggleStatus'])->middleware('permission:edit-review-cycles')->name('hr.performance.review-cycles.toggle-status');
        });


        // Employee Reviews
        Route::middleware('permission:manage-employee-reviews')->group(function () {
            Route::get('performance/employee-reviews', [EmployeeReviewController::class, 'index'])->name('hr.performance.employee-reviews.index');
            Route::get('performance/employee-reviews/create', [EmployeeReviewController::class, 'create'])->middleware('permission:create-employee-reviews')->name('hr.performance.employee-reviews.create');
            Route::post('performance/employee-reviews', [EmployeeReviewController::class, 'store'])->middleware('permission:create-employee-reviews')->name('hr.performance.employee-reviews.store');
            Route::get('performance/employee-reviews/{employeeReview}', [EmployeeReviewController::class, 'show'])->middleware('permission:view-employee-reviews')->name('hr.performance.employee-reviews.show');
            Route::get('performance/employee-reviews/{employeeReview}/conduct', [EmployeeReviewController::class, 'conduct'])->middleware('permission:edit-employee-reviews')->name('hr.performance.employee-reviews.conduct');
            Route::post('performance/employee-reviews/{employeeReview}/submit-ratings', [EmployeeReviewController::class, 'submitRatings'])->middleware('permission:edit-employee-reviews')->name('hr.performance.employee-reviews.submit-ratings');
            Route::put('performance/employee-reviews/{employeeReview}', [EmployeeReviewController::class, 'update'])->middleware('permission:edit-employee-reviews')->name('hr.performance.employee-reviews.update');
            Route::delete('performance/employee-reviews/{employeeReview}', [EmployeeReviewController::class, 'destroy'])->middleware('permission:delete-employee-reviews')->name('hr.performance.employee-reviews.destroy');
            Route::put('performance/employee-reviews/{employeeReview}/status', [EmployeeReviewController::class, 'updateStatus'])->middleware('permission:edit-employee-reviews')->name('hr.performance.employee-reviews.update-status');
        });

        // Recruitment Module Routes

        // Job Categories Routes
        Route::middleware('permission:manage-job-categories')->group(function () {
            Route::get('recruitment/job-categories', [\App\Http\Controllers\JobCategoryController::class, 'index'])->name('hr.recruitment.job-categories.index');
            Route::post('recruitment/job-categories', [\App\Http\Controllers\JobCategoryController::class, 'store'])->middleware('permission:create-job-categories')->name('hr.recruitment.job-categories.store');
            Route::put('recruitment/job-categories/{jobCategory}', [\App\Http\Controllers\JobCategoryController::class, 'update'])->middleware('permission:edit-job-categories')->name('hr.recruitment.job-categories.update');
            Route::delete('recruitment/job-categories/{jobCategory}', [\App\Http\Controllers\JobCategoryController::class, 'destroy'])->middleware('permission:delete-job-categories')->name('hr.recruitment.job-categories.destroy');
            Route::put('recruitment/job-categories/{jobCategory}/toggle-status', [\App\Http\Controllers\JobCategoryController::class, 'toggleStatus'])->middleware('permission:edit-job-categories')->name('hr.recruitment.job-categories.toggle-status');
        });

        // Job Requisitions Routes
        Route::middleware('permission:manage-job-requisitions')->group(function () {
            Route::get('recruitment/job-requisitions', [\App\Http\Controllers\JobRequisitionController::class, 'index'])->name('hr.recruitment.job-requisitions.index');
            Route::post('recruitment/job-requisitions', [\App\Http\Controllers\JobRequisitionController::class, 'store'])->middleware('permission:create-job-requisitions')->name('hr.recruitment.job-requisitions.store');
            Route::put('recruitment/job-requisitions/{jobRequisition}', [\App\Http\Controllers\JobRequisitionController::class, 'update'])->middleware('permission:edit-job-requisitions')->name('hr.recruitment.job-requisitions.update');
            Route::delete('recruitment/job-requisitions/{jobRequisition}', [\App\Http\Controllers\JobRequisitionController::class, 'destroy'])->middleware('permission:delete-job-requisitions')->name('hr.recruitment.job-requisitions.destroy');
            Route::put('recruitment/job-requisitions/{jobRequisition}/status', [\App\Http\Controllers\JobRequisitionController::class, 'updateStatus'])->middleware('permission:approve-job-requisitions')->name('hr.recruitment.job-requisitions.update-status');
        });

        // Job Types Routes
        Route::middleware('permission:manage-job-types')->group(function () {
            Route::get('recruitment/job-types', [\App\Http\Controllers\JobTypeController::class, 'index'])->name('hr.recruitment.job-types.index');
            Route::post('recruitment/job-types', [\App\Http\Controllers\JobTypeController::class, 'store'])->middleware('permission:create-job-types')->name('hr.recruitment.job-types.store');
            Route::put('recruitment/job-types/{jobType}', [\App\Http\Controllers\JobTypeController::class, 'update'])->middleware('permission:edit-job-types')->name('hr.recruitment.job-types.update');
            Route::delete('recruitment/job-types/{jobType}', [\App\Http\Controllers\JobTypeController::class, 'destroy'])->middleware('permission:delete-job-types')->name('hr.recruitment.job-types.destroy');
            Route::put('recruitment/job-types/{jobType}/toggle-status', [\App\Http\Controllers\JobTypeController::class, 'toggleStatus'])->middleware('permission:edit-job-types')->name('hr.recruitment.job-types.toggle-status');
        });

        // Employee Advance Routes
        Route::middleware('permission:manage-employee-salaries')->group(function () {
            Route::get('employee-advances/download-template', [\App\Http\Controllers\EmployeeAdvanceController::class, 'importTemplate'])->name('hr.employee-advances.import.template');
            Route::post('employee-advances/import', [\App\Http\Controllers\EmployeeAdvanceController::class, 'import'])->name('hr.employee-advances.import');
            Route::get('employee-advances', [\App\Http\Controllers\EmployeeAdvanceController::class, 'index'])->name('hr.employee-advances.index');
            Route::get('employee-advances/export', [\App\Http\Controllers\EmployeeAdvanceController::class, 'export'])->name('hr.employee-advances.export');
            Route::get('employee-advances/create', [\App\Http\Controllers\EmployeeAdvanceController::class, 'create'])->name('hr.employee-advances.create');
            Route::post('employee-advances', [\App\Http\Controllers\EmployeeAdvanceController::class, 'store'])->name('hr.employee-advances.store');
            Route::get('employee-advances/{employeeAdvance}/edit', [\App\Http\Controllers\EmployeeAdvanceController::class, 'edit'])->name('hr.employee-advances.edit');
            Route::put('employee-advances/{employeeAdvance}', [\App\Http\Controllers\EmployeeAdvanceController::class, 'update'])->name('hr.employee-advances.update');
            Route::delete('employee-advances/{employeeAdvance}', [\App\Http\Controllers\EmployeeAdvanceController::class, 'destroy'])->name('hr.employee-advances.destroy');
        });

        // Job Locations Routes
        Route::middleware('permission:manage-job-locations')->group(function () {
            Route::get('recruitment/job-locations', [\App\Http\Controllers\JobLocationController::class, 'index'])->name('hr.recruitment.job-locations.index');
            Route::post('recruitment/job-locations', [\App\Http\Controllers\JobLocationController::class, 'store'])->middleware('permission:create-job-locations')->name('hr.recruitment.job-locations.store');
            Route::put('recruitment/job-locations/{jobLocation}', [\App\Http\Controllers\JobLocationController::class, 'update'])->middleware('permission:edit-job-locations')->name('hr.recruitment.job-locations.update');
            Route::delete('recruitment/job-locations/{jobLocation}', [\App\Http\Controllers\JobLocationController::class, 'destroy'])->middleware('permission:delete-job-locations')->name('hr.recruitment.job-locations.destroy');
            Route::put('recruitment/job-locations/{jobLocation}/toggle-status', [\App\Http\Controllers\JobLocationController::class, 'toggleStatus'])->middleware('permission:edit-job-locations')->name('hr.recruitment.job-locations.toggle-status');
        });

        // Job Postings Routes
        Route::middleware('permission:manage-job-postings')->group(function () {
            Route::get('recruitment/job-postings', [\App\Http\Controllers\JobPostingController::class, 'index'])->name('hr.recruitment.job-postings.index');
            Route::post('recruitment/job-postings', [\App\Http\Controllers\JobPostingController::class, 'store'])->middleware('permission:create-job-postings')->name('hr.recruitment.job-postings.store');
            Route::put('recruitment/job-postings/{jobPosting}', [\App\Http\Controllers\JobPostingController::class, 'update'])->middleware('permission:edit-job-postings')->name('hr.recruitment.job-postings.update');
            Route::delete('recruitment/job-postings/{jobPosting}', [\App\Http\Controllers\JobPostingController::class, 'destroy'])->middleware('permission:delete-job-postings')->name('hr.recruitment.job-postings.destroy');
            Route::put('recruitment/job-postings/{jobPosting}/publish', [\App\Http\Controllers\JobPostingController::class, 'publish'])->middleware('permission:publish-job-postings')->name('hr.recruitment.job-postings.publish');
            Route::put('recruitment/job-postings/{jobPosting}/unpublish', [\App\Http\Controllers\JobPostingController::class, 'unpublish'])->middleware('permission:publish-job-postings')->name('hr.recruitment.job-postings.unpublish');
        });

        // Candidate Sources Routes
        Route::middleware('permission:manage-candidate-sources')->group(function () {
            Route::get('recruitment/candidate-sources', [\App\Http\Controllers\CandidateSourceController::class, 'index'])->name('hr.recruitment.candidate-sources.index');
            Route::post('recruitment/candidate-sources', [\App\Http\Controllers\CandidateSourceController::class, 'store'])->middleware('permission:create-candidate-sources')->name('hr.recruitment.candidate-sources.store');
            Route::put('recruitment/candidate-sources/{candidateSource}', [\App\Http\Controllers\CandidateSourceController::class, 'update'])->middleware('permission:edit-candidate-sources')->name('hr.recruitment.candidate-sources.update');
            Route::delete('recruitment/candidate-sources/{candidateSource}', [\App\Http\Controllers\CandidateSourceController::class, 'destroy'])->middleware('permission:delete-candidate-sources')->name('hr.recruitment.candidate-sources.destroy');
            Route::put('recruitment/candidate-sources/{candidateSource}/toggle-status', [\App\Http\Controllers\CandidateSourceController::class, 'toggleStatus'])->middleware('permission:edit-candidate-sources')->name('hr.recruitment.candidate-sources.toggle-status');
        });

        // Candidates Routes
        Route::middleware('permission:manage-candidates')->group(function () {
            Route::get('recruitment/candidates', [\App\Http\Controllers\CandidateController::class, 'index'])->name('hr.recruitment.candidates.index');
            Route::post('recruitment/candidates', [\App\Http\Controllers\CandidateController::class, 'store'])->middleware('permission:create-candidates')->name('hr.recruitment.candidates.store');
            Route::put('recruitment/candidates/{candidate}', [\App\Http\Controllers\CandidateController::class, 'update'])->middleware('permission:edit-candidates')->name('hr.recruitment.candidates.update');
            Route::delete('recruitment/candidates/{candidate}', [\App\Http\Controllers\CandidateController::class, 'destroy'])->middleware('permission:delete-candidates')->name('hr.recruitment.candidates.destroy');
            Route::put('recruitment/candidates/{candidate}/status', [\App\Http\Controllers\CandidateController::class, 'updateStatus'])->middleware('permission:edit-candidates')->name('hr.recruitment.candidates.update-status');
        });

        // Interview Types Routes
        Route::middleware('permission:manage-interview-types')->group(function () {
            Route::get('recruitment/interview-types', [\App\Http\Controllers\InterviewTypeController::class, 'index'])->name('hr.recruitment.interview-types.index');
            Route::post('recruitment/interview-types', [\App\Http\Controllers\InterviewTypeController::class, 'store'])->middleware('permission:create-interview-types')->name('hr.recruitment.interview-types.store');
            Route::put('recruitment/interview-types/{interviewType}', [\App\Http\Controllers\InterviewTypeController::class, 'update'])->middleware('permission:edit-interview-types')->name('hr.recruitment.interview-types.update');
            Route::delete('recruitment/interview-types/{interviewType}', [\App\Http\Controllers\InterviewTypeController::class, 'destroy'])->middleware('permission:delete-interview-types')->name('hr.recruitment.interview-types.destroy');
            Route::put('recruitment/interview-types/{interviewType}/toggle-status', [\App\Http\Controllers\InterviewTypeController::class, 'toggleStatus'])->middleware('permission:edit-interview-types')->name('hr.recruitment.interview-types.toggle-status');
        });

        // Interview Rounds Routes
        Route::middleware('permission:manage-interview-rounds')->group(function () {
            Route::get('recruitment/interview-rounds', [\App\Http\Controllers\InterviewRoundController::class, 'index'])->name('hr.recruitment.interview-rounds.index');
            Route::post('recruitment/interview-rounds', [\App\Http\Controllers\InterviewRoundController::class, 'store'])->middleware('permission:create-interview-rounds')->name('hr.recruitment.interview-rounds.store');
            Route::put('recruitment/interview-rounds/{interviewRound}', [\App\Http\Controllers\InterviewRoundController::class, 'update'])->middleware('permission:edit-interview-rounds')->name('hr.recruitment.interview-rounds.update');
            Route::delete('recruitment/interview-rounds/{interviewRound}', [\App\Http\Controllers\InterviewRoundController::class, 'destroy'])->middleware('permission:delete-interview-rounds')->name('hr.recruitment.interview-rounds.destroy');
            Route::put('recruitment/interview-rounds/{interviewRound}/toggle-status', [\App\Http\Controllers\InterviewRoundController::class, 'toggleStatus'])->middleware('permission:edit-interview-rounds')->name('hr.recruitment.interview-rounds.toggle-status');
        });

        // Interviews Routes
        Route::middleware('permission:manage-interviews')->group(function () {
            Route::get('recruitment/interviews', [\App\Http\Controllers\InterviewController::class, 'index'])->name('hr.recruitment.interviews.index');
            Route::post('recruitment/interviews', [\App\Http\Controllers\InterviewController::class, 'store'])->middleware('permission:create-interviews')->name('hr.recruitment.interviews.store');
            Route::put('recruitment/interviews/{interview}', [\App\Http\Controllers\InterviewController::class, 'update'])->middleware('permission:edit-interviews')->name('hr.recruitment.interviews.update');
            Route::delete('recruitment/interviews/{interview}', [\App\Http\Controllers\InterviewController::class, 'destroy'])->middleware('permission:delete-interviews')->name('hr.recruitment.interviews.destroy');
            Route::put('recruitment/interviews/{interview}/status', [\App\Http\Controllers\InterviewController::class, 'updateStatus'])->middleware('permission:edit-interviews')->name('hr.recruitment.interviews.update-status');
            Route::get('recruitment/interviews/rounds-by-candidate/{candidate}', [\App\Http\Controllers\InterviewController::class, 'getRoundsByCandidate'])->name('hr.recruitment.interviews.rounds-by-candidate');
        });

        // Interview Feedback Routes
        Route::middleware('permission:manage-interview-feedback')->group(function () {
            Route::get('recruitment/interview-feedback', [\App\Http\Controllers\InterviewFeedbackController::class, 'index'])->name('hr.recruitment.interview-feedback.index');
            Route::post('recruitment/interview-feedback', [\App\Http\Controllers\InterviewFeedbackController::class, 'store'])->middleware('permission:create-interview-feedback')->name('hr.recruitment.interview-feedback.store');
            Route::put('recruitment/interview-feedback/{interviewFeedback}', [\App\Http\Controllers\InterviewFeedbackController::class, 'update'])->middleware('permission:edit-interview-feedback')->name('hr.recruitment.interview-feedback.update');
            Route::delete('recruitment/interview-feedback/{interviewFeedback}', [\App\Http\Controllers\InterviewFeedbackController::class, 'destroy'])->middleware('permission:delete-interview-feedback')->name('hr.recruitment.interview-feedback.destroy');
            Route::get('recruitment/interview-feedback/get-interviewers/{interview}', [\App\Http\Controllers\InterviewFeedbackController::class, 'getInterviewers'])->name('hr.recruitment.interview-feedback.get-interviewers');
        });

        // Candidate Assessments Routes
        Route::middleware('permission:manage-candidate-assessments')->group(function () {
            Route::get('recruitment/candidate-assessments', [\App\Http\Controllers\CandidateAssessmentController::class, 'index'])->name('hr.recruitment.candidate-assessments.index');
            Route::post('recruitment/candidate-assessments', [\App\Http\Controllers\CandidateAssessmentController::class, 'store'])->middleware('permission:create-candidate-assessments')->name('hr.recruitment.candidate-assessments.store');
            Route::put('recruitment/candidate-assessments/{candidateAssessment}', [\App\Http\Controllers\CandidateAssessmentController::class, 'update'])->middleware('permission:edit-candidate-assessments')->name('hr.recruitment.candidate-assessments.update');
            Route::delete('recruitment/candidate-assessments/{candidateAssessment}', [\App\Http\Controllers\CandidateAssessmentController::class, 'destroy'])->middleware('permission:delete-candidate-assessments')->name('hr.recruitment.candidate-assessments.destroy');
        });

        // Offer Templates Routes
        Route::middleware('permission:manage-offer-templates')->group(function () {
            Route::get('recruitment/offer-templates', [\App\Http\Controllers\OfferTemplateController::class, 'index'])->name('hr.recruitment.offer-templates.index');
            Route::post('recruitment/offer-templates', [\App\Http\Controllers\OfferTemplateController::class, 'store'])->middleware('permission:create-offer-templates')->name('hr.recruitment.offer-templates.store');
            Route::put('recruitment/offer-templates/{offerTemplate}', [\App\Http\Controllers\OfferTemplateController::class, 'update'])->middleware('permission:edit-offer-templates')->name('hr.recruitment.offer-templates.update');
            Route::delete('recruitment/offer-templates/{offerTemplate}', [\App\Http\Controllers\OfferTemplateController::class, 'destroy'])->middleware('permission:delete-offer-templates')->name('hr.recruitment.offer-templates.destroy');
            Route::put('recruitment/offer-templates/{offerTemplate}/toggle-status', [\App\Http\Controllers\OfferTemplateController::class, 'toggleStatus'])->middleware('permission:edit-offer-templates')->name('hr.recruitment.offer-templates.toggle-status');
            Route::post('recruitment/offer-templates/{offerTemplate}/preview', [\App\Http\Controllers\OfferTemplateController::class, 'preview'])->middleware('permission:view-offer-templates')->name('hr.recruitment.offer-templates.preview');
            Route::post('recruitment/offer-templates/{offerTemplate}/generate', [\App\Http\Controllers\OfferTemplateController::class, 'generate'])->middleware('permission:view-offer-templates')->name('hr.recruitment.offer-templates.generate');
        });

        // Offers Routes
        Route::middleware('permission:manage-offers')->group(function () {
            Route::get('recruitment/offers', [\App\Http\Controllers\OfferController::class, 'index'])->name('hr.recruitment.offers.index');
            Route::post('recruitment/offers', [\App\Http\Controllers\OfferController::class, 'store'])->middleware('permission:create-offers')->name('hr.recruitment.offers.store');
            Route::put('recruitment/offers/{offer}', [\App\Http\Controllers\OfferController::class, 'update'])->middleware('permission:edit-offers')->name('hr.recruitment.offers.update');
            Route::delete('recruitment/offers/{offer}', [\App\Http\Controllers\OfferController::class, 'destroy'])->middleware('permission:delete-offers')->name('hr.recruitment.offers.destroy');
            Route::put('recruitment/offers/{offer}/status', [\App\Http\Controllers\OfferController::class, 'updateStatus'])->middleware('permission:edit-offers')->name('hr.recruitment.offers.update-status');
        });

        // Onboarding Checklists Routes
        Route::middleware('permission:manage-onboarding-checklists')->group(function () {
            Route::get('recruitment/onboarding-checklists', [\App\Http\Controllers\OnboardingChecklistController::class, 'index'])->name('hr.recruitment.onboarding-checklists.index');
            Route::post('recruitment/onboarding-checklists', [\App\Http\Controllers\OnboardingChecklistController::class, 'store'])->middleware('permission:create-onboarding-checklists')->name('hr.recruitment.onboarding-checklists.store');
            Route::put('recruitment/onboarding-checklists/{onboardingChecklist}', [\App\Http\Controllers\OnboardingChecklistController::class, 'update'])->middleware('permission:edit-onboarding-checklists')->name('hr.recruitment.onboarding-checklists.update');
            Route::delete('recruitment/onboarding-checklists/{onboardingChecklist}', [\App\Http\Controllers\OnboardingChecklistController::class, 'destroy'])->middleware('permission:delete-onboarding-checklists')->name('hr.recruitment.onboarding-checklists.destroy');
            Route::put('recruitment/onboarding-checklists/{onboardingChecklist}/toggle-status', [\App\Http\Controllers\OnboardingChecklistController::class, 'toggleStatus'])->middleware('permission:edit-onboarding-checklists')->name('hr.recruitment.onboarding-checklists.toggle-status');
        });

        // Checklist Items Routes
        Route::middleware('permission:manage-checklist-items')->group(function () {
            Route::get('recruitment/checklist-items', [\App\Http\Controllers\ChecklistItemController::class, 'index'])->name('hr.recruitment.checklist-items.index');
            Route::post('recruitment/checklist-items', [\App\Http\Controllers\ChecklistItemController::class, 'store'])->middleware('permission:create-checklist-items')->name('hr.recruitment.checklist-items.store');
            Route::put('recruitment/checklist-items/{checklistItem}', [\App\Http\Controllers\ChecklistItemController::class, 'update'])->middleware('permission:edit-checklist-items')->name('hr.recruitment.checklist-items.update');
            Route::delete('recruitment/checklist-items/{checklistItem}', [\App\Http\Controllers\ChecklistItemController::class, 'destroy'])->middleware('permission:delete-checklist-items')->name('hr.recruitment.checklist-items.destroy');
            Route::put('recruitment/checklist-items/{checklistItem}/toggle-status', [\App\Http\Controllers\ChecklistItemController::class, 'toggleStatus'])->middleware('permission:edit-checklist-items')->name('hr.recruitment.checklist-items.toggle-status');
        });

        // Skill Routes
        Route::middleware('permission:manage-skills')->group(function () {
            Route::get('skills/download-template', [\App\Http\Controllers\SkillController::class, 'importTemplate'])->name('hr.skills.import.template');
            Route::post('skills/import', [\App\Http\Controllers\SkillController::class, 'import'])->middleware('permission:create-skills')->name('hr.skills.import');
            Route::get('skills', [\App\Http\Controllers\SkillController::class, 'index'])->name('hr.skills.index');
            Route::post('skills', [\App\Http\Controllers\SkillController::class, 'store'])->middleware('permission:create-skills')->name('hr.skills.store');
            Route::put('skills/{skill}', [\App\Http\Controllers\SkillController::class, 'update'])->middleware('permission:edit-skills')->name('hr.skills.update');
            Route::delete('skills/{skill}', [\App\Http\Controllers\SkillController::class, 'destroy'])->middleware('permission:delete-skills')->name('hr.skills.destroy');
            Route::put('skills/{skill}/toggle-status', [\App\Http\Controllers\SkillController::class, 'toggleStatus'])->middleware('permission:edit-skills')->name('hr.skills.toggle-status');
            Route::post('skills/bulk-copy', [\App\Http\Controllers\SkillController::class, 'bulkCopyToBranches'])->middleware('permission:create-skills')->name('hr.skills.bulk-copy');
            Route::post('skills/{skill}/copy-to-branches', [\App\Http\Controllers\SkillController::class, 'copyToBranches'])->middleware('permission:create-skills')->name('hr.skills.copy-to-branches');
        });

        // Work History Routes
        Route::middleware('permission:manage-employees')->group(function () { // Using manage-employees for now, or should I use manage-work-history? Implementation plan mentioned manage-work-history but I haven't seeded it. I'll use manage-employees as a fallback or seed it. Let's use manage-employees for now to be safe as it definitely exists.
            Route::get('work-history', [\App\Http\Controllers\EmployeeWorkHistoryController::class, 'index'])->name('hr.work-history.index');
            Route::post('work-history', [\App\Http\Controllers\EmployeeWorkHistoryController::class, 'store'])->name('hr.work-history.store');
            Route::put('work-history/{workHistory}', [\App\Http\Controllers\EmployeeWorkHistoryController::class, 'update'])->name('hr.work-history.update');
            Route::delete('work-history/{workHistory}', [\App\Http\Controllers\EmployeeWorkHistoryController::class, 'destroy'])->name('hr.work-history.destroy');
            Route::get('work-history/employees/{branch}', [\App\Http\Controllers\EmployeeWorkHistoryController::class, 'getEmployeesByBranch'])->name('hr.work-history.employees');
        });

        // Candidate Onboarding Routes
        Route::middleware('permission:manage-candidate-onboarding')->group(function () {
            Route::get('recruitment/candidate-onboarding', [\App\Http\Controllers\CandidateOnboardingController::class, 'index'])->name('hr.recruitment.candidate-onboarding.index');
            Route::post('recruitment/candidate-onboarding', [\App\Http\Controllers\CandidateOnboardingController::class, 'store'])->middleware('permission:create-candidate-onboarding')->name('hr.recruitment.candidate-onboarding.store');
            Route::put('recruitment/candidate-onboarding/{candidateOnboarding}', [\App\Http\Controllers\CandidateOnboardingController::class, 'update'])->middleware('permission:edit-candidate-onboarding')->name('hr.recruitment.candidate-onboarding.update');
            Route::delete('recruitment/candidate-onboarding/{candidateOnboarding}', [\App\Http\Controllers\CandidateOnboardingController::class, 'destroy'])->middleware('permission:delete-candidate-onboarding')->name('hr.recruitment.candidate-onboarding.destroy');
            Route::put('recruitment/candidate-onboarding/{candidateOnboarding}/status', [\App\Http\Controllers\CandidateOnboardingController::class, 'updateStatus'])->middleware('permission:edit-candidate-onboarding')->name('hr.recruitment.candidate-onboarding.update-status');
        });

        // Meeting Types Routes
        Route::middleware('permission:manage-meeting-types')->group(function () {
            Route::get('meetings/meeting-types', [\App\Http\Controllers\MeetingTypeController::class, 'index'])->name('meetings.meeting-types.index');
            Route::post('meetings/meeting-types', [\App\Http\Controllers\MeetingTypeController::class, 'store'])->middleware('permission:create-meeting-types')->name('meetings.meeting-types.store');
            Route::put('meetings/meeting-types/{meetingType}', [\App\Http\Controllers\MeetingTypeController::class, 'update'])->middleware('permission:edit-meeting-types')->name('meetings.meeting-types.update');
            Route::delete('meetings/meeting-types/{meetingType}', [\App\Http\Controllers\MeetingTypeController::class, 'destroy'])->middleware('permission:delete-meeting-types')->name('meetings.meeting-types.destroy');
            Route::put('meetings/meeting-types/{meetingType}/toggle-status', [\App\Http\Controllers\MeetingTypeController::class, 'toggleStatus'])->middleware('permission:edit-meeting-types')->name('meetings.meeting-types.toggle-status');
        });

        // Meeting Rooms Routes
        Route::middleware('permission:manage-meeting-rooms')->group(function () {
            Route::get('meetings/meeting-rooms', [\App\Http\Controllers\MeetingRoomController::class, 'index'])->name('meetings.meeting-rooms.index');
            Route::post('meetings/meeting-rooms', [\App\Http\Controllers\MeetingRoomController::class, 'store'])->middleware('permission:create-meeting-rooms')->name('meetings.meeting-rooms.store');
            Route::put('meetings/meeting-rooms/{meetingRoom}', [\App\Http\Controllers\MeetingRoomController::class, 'update'])->middleware('permission:edit-meeting-rooms')->name('meetings.meeting-rooms.update');
            Route::delete('meetings/meeting-rooms/{meetingRoom}', [\App\Http\Controllers\MeetingRoomController::class, 'destroy'])->middleware('permission:delete-meeting-rooms')->name('meetings.meeting-rooms.destroy');
            Route::put('meetings/meeting-rooms/{meetingRoom}/toggle-status', [\App\Http\Controllers\MeetingRoomController::class, 'toggleStatus'])->middleware('permission:edit-meeting-rooms')->name('meetings.meeting-rooms.toggle-status');
        });

        // Meetings Routes
        Route::middleware('permission:manage-meetings')->group(function () {
            Route::get('meetings/meetings', [\App\Http\Controllers\MeetingController::class, 'index'])->name('meetings.meetings.index');
            Route::post('meetings/meetings', [\App\Http\Controllers\MeetingController::class, 'store'])->middleware('permission:create-meetings')->name('meetings.meetings.store');
            Route::put('meetings/meetings/{meeting}', [\App\Http\Controllers\MeetingController::class, 'update'])->middleware('permission:edit-meetings')->name('meetings.meetings.update');
            Route::delete('meetings/meetings/{meeting}', [\App\Http\Controllers\MeetingController::class, 'destroy'])->middleware('permission:delete-meetings')->name('meetings.meetings.destroy');
            Route::put('meetings/meetings/{meeting}/status', [\App\Http\Controllers\MeetingController::class, 'updateMeetingStatus'])->middleware('permission:manage-meeting-status')->name('meetings.meetings.update-status');
        });

        // Meeting Attendees Routes
        Route::middleware('permission:manage-meeting-attendees')->group(function () {
            Route::get('meetings/meeting-attendees', [\App\Http\Controllers\MeetingAttendeeController::class, 'index'])->name('meetings.meeting-attendees.index');
            Route::post('meetings/meeting-attendees', [\App\Http\Controllers\MeetingAttendeeController::class, 'store'])->middleware('permission:create-meeting-attendees')->name('meetings.meeting-attendees.store');
            Route::put('meetings/meeting-attendees/{meetingAttendee}', [\App\Http\Controllers\MeetingAttendeeController::class, 'update'])->middleware('permission:edit-meeting-attendees')->name('meetings.meeting-attendees.update');
            Route::delete('meetings/meeting-attendees/{meetingAttendee}', [\App\Http\Controllers\MeetingAttendeeController::class, 'destroy'])->middleware('permission:delete-meeting-attendees')->name('meetings.meeting-attendees.destroy');
            Route::put('meetings/meeting-attendees/{meetingAttendee}/rsvp', [\App\Http\Controllers\MeetingAttendeeController::class, 'updateMeetingRsvp'])->middleware('permission:manage-meeting-rsvp-status')->name('meetings.meeting-attendees.update-rsvp');
            Route::put('meetings/meeting-attendees/{meetingAttendee}/attendance', [\App\Http\Controllers\MeetingAttendeeController::class, 'updateMeetingAttendance'])->middleware('permission:manage-meeting-attendance')->name('meetings.meeting-attendees.update-attendance');
        });

        // Meeting Minutes Routes
        Route::middleware('permission:manage-meeting-minutes')->group(function () {
            Route::get('meetings/meeting-minutes', [\App\Http\Controllers\MeetingMinuteController::class, 'index'])->name('meetings.meeting-minutes.index');
            Route::post('meetings/meeting-minutes', [\App\Http\Controllers\MeetingMinuteController::class, 'store'])->middleware('permission:create-meeting-minutes')->name('meetings.meeting-minutes.store');
            Route::put('meetings/meeting-minutes/{meetingMinute}', [\App\Http\Controllers\MeetingMinuteController::class, 'update'])->middleware('permission:edit-meeting-minutes')->name('meetings.meeting-minutes.update');
            Route::delete('meetings/meeting-minutes/{meetingMinute}', [\App\Http\Controllers\MeetingMinuteController::class, 'destroy'])->middleware('permission:delete-meeting-minutes')->name('meetings.meeting-minutes.destroy');
        });

        // Action Items Routes
        Route::middleware('permission:manage-action-items')->group(function () {
            Route::get('meetings/action-items', [\App\Http\Controllers\ActionItemController::class, 'index'])->name('meetings.action-items.index');
            Route::post('meetings/action-items', [\App\Http\Controllers\ActionItemController::class, 'store'])->middleware('permission:create-action-items')->name('meetings.action-items.store');
            Route::put('meetings/action-items/{actionItem}', [\App\Http\Controllers\ActionItemController::class, 'update'])->middleware('permission:edit-action-items')->name('meetings.action-items.update');
            Route::delete('meetings/action-items/{actionItem}', [\App\Http\Controllers\ActionItemController::class, 'destroy'])->middleware('permission:delete-action-items')->name('meetings.action-items.destroy');
            Route::put('meetings/action-items/{actionItem}/progress', [\App\Http\Controllers\ActionItemController::class, 'updateProgress'])->middleware('permission:edit-action-items')->name('meetings.action-items.update-progress');
        });



        // Contract Types Routes
        Route::middleware('permission:manage-contract-types')->group(function () {
            Route::get('contracts/contract-types', [\App\Http\Controllers\ContractTypeController::class, 'index'])->name('hr.contracts.contract-types.index');
            Route::post('contracts/contract-types', [\App\Http\Controllers\ContractTypeController::class, 'store'])->middleware('permission:create-contract-types')->name('hr.contracts.contract-types.store');
            Route::put('contracts/contract-types/{contractType}', [\App\Http\Controllers\ContractTypeController::class, 'update'])->middleware('permission:edit-contract-types')->name('hr.contracts.contract-types.update');
            Route::delete('contracts/contract-types/{contractType}', [\App\Http\Controllers\ContractTypeController::class, 'destroy'])->middleware('permission:delete-contract-types')->name('hr.contracts.contract-types.destroy');
            Route::put('contracts/contract-types/{contractType}/toggle-status', [\App\Http\Controllers\ContractTypeController::class, 'toggleStatus'])->middleware('permission:edit-contract-types')->name('hr.contracts.contract-types.toggle-status');
        });

        // Employee Contracts Routes
        Route::middleware('permission:manage-employee-contracts')->group(function () {
            Route::get('contracts/employee-contracts', [\App\Http\Controllers\EmployeeContractController::class, 'index'])->name('hr.contracts.employee-contracts.index');
            Route::post('contracts/employee-contracts', [\App\Http\Controllers\EmployeeContractController::class, 'store'])->middleware('permission:create-employee-contracts')->name('hr.contracts.employee-contracts.store');
            Route::put('contracts/employee-contracts/{employeeContract}', [\App\Http\Controllers\EmployeeContractController::class, 'update'])->middleware('permission:edit-employee-contracts')->name('hr.contracts.employee-contracts.update');
            Route::delete('contracts/employee-contracts/{employeeContract}', [\App\Http\Controllers\EmployeeContractController::class, 'destroy'])->middleware('permission:delete-employee-contracts')->name('hr.contracts.employee-contracts.destroy');
            Route::put('contracts/employee-contracts/{employeeContract}/status', [\App\Http\Controllers\EmployeeContractController::class, 'updateStatus'])->middleware('permission:approve-employee-contracts')->name('hr.contracts.employee-contracts.update-status');
        });



        // Contract Renewals Routes
        Route::middleware('permission:manage-contract-renewals')->group(function () {
            Route::get('contracts/contract-renewals', [\App\Http\Controllers\ContractRenewalController::class, 'index'])->name('hr.contracts.contract-renewals.index');
            Route::post('contracts/contract-renewals', [\App\Http\Controllers\ContractRenewalController::class, 'store'])->middleware('permission:create-contract-renewals')->name('hr.contracts.contract-renewals.store');
            Route::put('contracts/contract-renewals/{contractRenewal}', [\App\Http\Controllers\ContractRenewalController::class, 'update'])->middleware('permission:edit-contract-renewals')->name('hr.contracts.contract-renewals.update');
            Route::delete('contracts/contract-renewals/{contractRenewal}', [\App\Http\Controllers\ContractRenewalController::class, 'destroy'])->middleware('permission:delete-contract-renewals')->name('hr.contracts.contract-renewals.destroy');
            Route::put('contracts/contract-renewals/{contractRenewal}/approve', [\App\Http\Controllers\ContractRenewalController::class, 'approve'])->middleware('permission:approve-contract-renewals')->name('hr.contracts.contract-renewals.approve');
            Route::put('contracts/contract-renewals/{contractRenewal}/reject', [\App\Http\Controllers\ContractRenewalController::class, 'reject'])->middleware('permission:reject-contract-renewals')->name('hr.contracts.contract-renewals.reject');
            Route::put('contracts/contract-renewals/{contractRenewal}/process', [\App\Http\Controllers\ContractRenewalController::class, 'process'])->middleware('permission:edit-contract-renewals')->name('hr.contracts.contract-renewals.process');
        });

        // Contract Templates Routes
        Route::middleware('permission:manage-contract-templates')->group(function () {
            Route::get('contracts/contract-templates', [\App\Http\Controllers\ContractTemplateController::class, 'index'])->name('hr.contracts.contract-templates.index');
            Route::post('contracts/contract-templates', [\App\Http\Controllers\ContractTemplateController::class, 'store'])->middleware('permission:create-contract-templates')->name('hr.contracts.contract-templates.store');
            Route::put('contracts/contract-templates/{contractTemplate}', [\App\Http\Controllers\ContractTemplateController::class, 'update'])->middleware('permission:edit-contract-templates')->name('hr.contracts.contract-templates.update');
            Route::delete('contracts/contract-templates/{contractTemplate}', [\App\Http\Controllers\ContractTemplateController::class, 'destroy'])->middleware('permission:delete-contract-templates')->name('hr.contracts.contract-templates.destroy');
            Route::put('contracts/contract-templates/{contractTemplate}/toggle-status', [\App\Http\Controllers\ContractTemplateController::class, 'toggleStatus'])->middleware('permission:edit-contract-templates')->name('hr.contracts.contract-templates.toggle-status');
            Route::post('contracts/contract-templates/{contractTemplate}/generate', [\App\Http\Controllers\ContractTemplateController::class, 'generate'])->middleware('permission:view-contract-templates')->name('hr.contracts.contract-templates.generate');
        });

        // Document Categories Routes
        Route::middleware('permission:manage-document-categories')->group(function () {
            Route::get('documents/document-categories', [\App\Http\Controllers\DocumentCategoryController::class, 'index'])->name('hr.documents.document-categories.index');
            Route::post('documents/document-categories', [\App\Http\Controllers\DocumentCategoryController::class, 'store'])->middleware('permission:create-document-categories')->name('hr.documents.document-categories.store');
            Route::put('documents/document-categories/{documentCategory}', [\App\Http\Controllers\DocumentCategoryController::class, 'update'])->middleware('permission:edit-document-categories')->name('hr.documents.document-categories.update');
            Route::delete('documents/document-categories/{documentCategory}', [\App\Http\Controllers\DocumentCategoryController::class, 'destroy'])->middleware('permission:delete-document-categories')->name('hr.documents.document-categories.destroy');
            Route::put('documents/document-categories/{documentCategory}/toggle-status', [\App\Http\Controllers\DocumentCategoryController::class, 'toggleStatus'])->middleware('permission:edit-document-categories')->name('hr.documents.document-categories.toggle-status');
        });

        // HR Documents Routes
        Route::middleware('permission:manage-hr-documents')->group(function () {
            Route::get('documents/hr-documents', [\App\Http\Controllers\HrDocumentController::class, 'index'])->name('hr.documents.hr-documents.index');
            Route::post('documents/hr-documents', [\App\Http\Controllers\HrDocumentController::class, 'store'])->middleware('permission:create-hr-documents')->name('hr.documents.hr-documents.store');
            Route::put('documents/hr-documents/{hrDocument}', [\App\Http\Controllers\HrDocumentController::class, 'update'])->middleware('permission:edit-hr-documents')->name('hr.documents.hr-documents.update');
            Route::delete('documents/hr-documents/{hrDocument}', [\App\Http\Controllers\HrDocumentController::class, 'destroy'])->middleware('permission:delete-hr-documents')->name('hr.documents.hr-documents.destroy');
            Route::get('documents/hr-documents/{hrDocument}/download', [HrDocumentController::class, 'download'])->middleware('permission:view-hr-documents')->name('hr.documents.hr-documents.download');
            Route::put('documents/hr-documents/{hrDocument}/status', [\App\Http\Controllers\HrDocumentController::class, 'updateStatus'])->middleware('permission:edit-hr-documents')->name('hr.documents.hr-documents.update-status');
        });



        // Document Acknowledgments Routes
        Route::middleware('permission:manage-document-acknowledgments')->group(function () {
            Route::get('documents/document-acknowledgments', [\App\Http\Controllers\DocumentAcknowledgmentController::class, 'index'])->name('hr.documents.document-acknowledgments.index');
            Route::post('documents/document-acknowledgments', [\App\Http\Controllers\DocumentAcknowledgmentController::class, 'store'])->middleware('permission:create-document-acknowledgments')->name('hr.documents.document-acknowledgments.store');
            Route::put('documents/document-acknowledgments/{documentAcknowledgment}', [\App\Http\Controllers\DocumentAcknowledgmentController::class, 'update'])->middleware('permission:edit-document-acknowledgments')->name('hr.documents.document-acknowledgments.update');
            Route::delete('documents/document-acknowledgments/{documentAcknowledgment}', [\App\Http\Controllers\DocumentAcknowledgmentController::class, 'destroy'])->middleware('permission:delete-document-acknowledgments')->name('hr.documents.document-acknowledgments.destroy');
            Route::put('documents/document-acknowledgments/{documentAcknowledgment}/acknowledge', [\App\Http\Controllers\DocumentAcknowledgmentController::class, 'acknowledge'])->middleware('permission:acknowledge-document-acknowledgments')->name('hr.documents.document-acknowledgments.acknowledge');
            Route::post('documents/document-acknowledgments/bulk-assign', [\App\Http\Controllers\DocumentAcknowledgmentController::class, 'bulkAssign'])->middleware('permission:create-document-acknowledgments')->name('hr.documents.document-acknowledgments.bulk-assign');
        });

        // Document Templates Routes
        Route::middleware('permission:manage-document-templates')->group(function () {
            Route::get('documents/document-templates', [\App\Http\Controllers\DocumentTemplateController::class, 'index'])->name('hr.documents.document-templates.index');
            Route::post('documents/document-templates', [\App\Http\Controllers\DocumentTemplateController::class, 'store'])->middleware('permission:create-document-templates')->name('hr.documents.document-templates.store');
            Route::put('documents/document-templates/{documentTemplate}', [\App\Http\Controllers\DocumentTemplateController::class, 'update'])->middleware('permission:edit-document-templates')->name('hr.documents.document-templates.update');
            Route::delete('documents/document-templates/{documentTemplate}', [\App\Http\Controllers\DocumentTemplateController::class, 'destroy'])->middleware('permission:delete-document-templates')->name('hr.documents.document-templates.destroy');
            Route::put('documents/document-templates/{documentTemplate}/toggle-status', [\App\Http\Controllers\DocumentTemplateController::class, 'toggleStatus'])->middleware('permission:edit-document-templates')->name('hr.documents.document-templates.toggle-status');
            Route::post('documents/document-templates/{documentTemplate}/preview', [\App\Http\Controllers\DocumentTemplateController::class, 'preview'])->middleware('permission:view-document-templates')->name('hr.documents.document-templates.preview');
            Route::post('documents/document-templates/{documentTemplate}/generate', [\App\Http\Controllers\DocumentTemplateController::class, 'generate'])->middleware('permission:view-document-templates')->name('hr.documents.document-templates.generate');
        });

        // Leave Types routes
        Route::middleware('permission:manage-leave-types')->group(function () {
            Route::get('leave-types', [\App\Http\Controllers\LeaveTypeController::class, 'index'])->name('hr.leave-types.index');
            Route::post('leave-types', [\App\Http\Controllers\LeaveTypeController::class, 'store'])->middleware('permission:create-leave-types')->name('hr.leave-types.store');
            Route::put('leave-types/{leaveType}', [\App\Http\Controllers\LeaveTypeController::class, 'update'])->middleware('permission:edit-leave-types')->name('hr.leave-types.update');
            Route::delete('leave-types/{leaveType}', [\App\Http\Controllers\LeaveTypeController::class, 'destroy'])->middleware('permission:delete-leave-types')->name('hr.leave-types.destroy');
            Route::put('leave-types/{leaveType}/toggle-status', [\App\Http\Controllers\LeaveTypeController::class, 'toggleStatus'])->middleware('permission:edit-leave-types')->name('hr.leave-types.toggle-status');
        });

        // Leave Policies routes
        Route::middleware('permission:manage-leave-policies')->group(function () {
            Route::get('leave-policies', [\App\Http\Controllers\LeavePolicyController::class, 'index'])->name('hr.leave-policies.index');
            Route::post('leave-policies', [\App\Http\Controllers\LeavePolicyController::class, 'store'])->middleware('permission:create-leave-policies')->name('hr.leave-policies.store');
            Route::put('leave-policies/{leavePolicy}', [\App\Http\Controllers\LeavePolicyController::class, 'update'])->middleware('permission:edit-leave-policies')->name('hr.leave-policies.update');
            Route::delete('leave-policies/{leavePolicy}', [\App\Http\Controllers\LeavePolicyController::class, 'destroy'])->middleware('permission:delete-leave-policies')->name('hr.leave-policies.destroy');
            Route::put('leave-policies/{leavePolicy}/toggle-status', [\App\Http\Controllers\LeavePolicyController::class, 'toggleStatus'])->middleware('permission:edit-leave-policies')->name('hr.leave-policies.toggle-status');
        });

        // Leave Applications routes
        Route::middleware('permission:manage-leave-applications')->group(function () {
            Route::get('leave-applications', [\App\Http\Controllers\LeaveApplicationController::class, 'index'])->name('hr.leave-applications.index');
            Route::post('leave-applications', [\App\Http\Controllers\LeaveApplicationController::class, 'store'])->middleware('permission:create-leave-applications')->name('hr.leave-applications.store');
            Route::put('leave-applications/{leaveApplication}', [\App\Http\Controllers\LeaveApplicationController::class, 'update'])->middleware('permission:edit-leave-applications')->name('hr.leave-applications.update');
            Route::delete('leave-applications/{leaveApplication}', [\App\Http\Controllers\LeaveApplicationController::class, 'destroy'])->middleware('permission:delete-leave-applications')->name('hr.leave-applications.destroy');
            Route::put('leave-applications/{leaveApplication}/status', [\App\Http\Controllers\LeaveApplicationController::class, 'updateStatus'])->middleware('permission:approve-leave-applications')->name('hr.leave-applications.update-status');
        });

        // Leave Balances routes
        Route::middleware('permission:manage-leave-balances')->group(function () {
            Route::get('leave-balances', [\App\Http\Controllers\LeaveBalanceController::class, 'index'])->name('hr.leave-balances.index');
            Route::get('leave-balances/suggest-carry-forward', [\App\Http\Controllers\LeaveBalanceController::class, 'suggestCarryForward'])->name('hr.leave-balances.suggest-carry-forward');
            Route::post('leave-balances', [\App\Http\Controllers\LeaveBalanceController::class, 'store'])->middleware('permission:create-leave-balances')->name('hr.leave-balances.store');
            Route::put('leave-balances/{leaveBalance}', [\App\Http\Controllers\LeaveBalanceController::class, 'update'])->middleware('permission:edit-leave-balances')->name('hr.leave-balances.update');
            Route::delete('leave-balances/{leaveBalance}', [\App\Http\Controllers\LeaveBalanceController::class, 'destroy'])->middleware('permission:delete-leave-balances')->name('hr.leave-balances.destroy');
            Route::put('leave-balances/{leaveBalance}/adjust', [\App\Http\Controllers\LeaveBalanceController::class, 'adjust'])->middleware('permission:adjust-leave-balances')->name('hr.leave-balances.adjust');
        });

        // Shifts routes
        Route::middleware('permission:manage-shifts')->group(function () {
            Route::get('shifts', [\App\Http\Controllers\ShiftController::class, 'index'])->name('hr.shifts.index');
            Route::post('shifts', [\App\Http\Controllers\ShiftController::class, 'store'])->middleware('permission:create-shifts')->name('hr.shifts.store');
            Route::put('shifts/{shift}', [\App\Http\Controllers\ShiftController::class, 'update'])->middleware('permission:edit-shifts')->name('hr.shifts.update');
            Route::delete('shifts/{shift}', [\App\Http\Controllers\ShiftController::class, 'destroy'])->middleware('permission:delete-shifts')->name('hr.shifts.destroy');
            Route::put('shifts/{shift}/toggle-status', [\App\Http\Controllers\ShiftController::class, 'toggleStatus'])->middleware('permission:edit-shifts')->name('hr.shifts.toggle-status');
            Route::post('shifts/{shift}/copy', [\App\Http\Controllers\ShiftController::class, 'copyToBranches'])->middleware('permission:create-shifts')->name('hr.shifts.copy');
            Route::post('shifts/bulk-copy', [\App\Http\Controllers\ShiftController::class, 'bulkCopyToBranches'])->middleware('permission:create-shifts')->name('hr.shifts.bulk_copy');
        });

        // Attendance Policies routes
        Route::middleware('permission:manage-attendance-policies')->group(function () {
            Route::get('attendance-policies', [\App\Http\Controllers\AttendancePolicyController::class, 'index'])->name('hr.attendance-policies.index');
            Route::post('attendance-policies', [\App\Http\Controllers\AttendancePolicyController::class, 'store'])->middleware('permission:create-attendance-policies')->name('hr.attendance-policies.store');
            Route::put('attendance-policies/{attendancePolicy}', [\App\Http\Controllers\AttendancePolicyController::class, 'update'])->middleware('permission:edit-attendance-policies')->name('hr.attendance-policies.update');
            Route::delete('attendance-policies/{attendancePolicy}', [\App\Http\Controllers\AttendancePolicyController::class, 'destroy'])->middleware('permission:delete-attendance-policies')->name('hr.attendance-policies.destroy');
            Route::put('attendance-policies/{attendancePolicy}/toggle-status', [\App\Http\Controllers\AttendancePolicyController::class, 'toggleStatus'])->middleware('permission:edit-attendance-policies')->name('hr.attendance-policies.toggle-status');
        });

        // Attendance Records routes
        Route::middleware('permission:manage-attendance-records')->group(function () {
            Route::get('attendance-records', [\App\Http\Controllers\AttendanceRecordController::class, 'index'])->name('hr.attendance-records.index');
            Route::get('attendance-records/export-daily', [\App\Http\Controllers\AttendanceRecordController::class, 'exportDailyReport'])->name('hr.attendance-records.export-daily');
            Route::post('attendance-records', [\App\Http\Controllers\AttendanceRecordController::class, 'store'])->middleware('permission:create-attendance-records')->name('hr.attendance-records.store');
            Route::put('attendance-records/{attendanceRecord}', [\App\Http\Controllers\AttendanceRecordController::class, 'update'])->middleware('permission:edit-attendance-records')->name('hr.attendance-records.update');
            Route::delete('attendance-records/{attendanceRecord}', [\App\Http\Controllers\AttendanceRecordController::class, 'destroy'])->middleware('permission:delete-attendance-records')->name('hr.attendance-records.destroy');

            // ESSL Sync Report Routes
            Route::get('essl-sync', [\App\Http\Controllers\EsslLogController::class, 'index'])->name('hr.essl-sync.index');
            Route::post('essl-sync/sync', [\App\Http\Controllers\EsslLogController::class, 'sync'])->name('hr.essl-sync.sync');
            Route::post('essl-sync/sync-chunk', [\App\Http\Controllers\EsslLogController::class, 'syncChunk'])->name('hr.essl-sync.sync-chunk');
            Route::get('essl-sync/export', [\App\Http\Controllers\EsslLogController::class, 'export'])->name('hr.essl-sync.export');
            Route::redirect('essl-sync-report', '/essl-sync', 301);
            Route::redirect('essl-sync-report/{path}', '/essl-sync/{path}', 301)->where('path', '.*');

            // Biometric Present Report Routes
            Route::get('biometric-present-report', [\App\Http\Controllers\BiometricPresentReportController::class, 'index'])->name('hr.biometric-present-report.index');
            Route::get('biometric-present-report/pdf', [\App\Http\Controllers\BiometricPresentReportController::class, 'generatePdf'])->name('hr.biometric-present-report.pdf');
        });

        // Clock In/Out routes
        Route::middleware('permission:clock-in-out')->group(function () {
            Route::post('attendance/clock-in', [\App\Http\Controllers\AttendanceRecordController::class, 'clockIn'])->name('hr.attendance.clock-in');
            Route::post('attendance/clock-out', [\App\Http\Controllers\AttendanceRecordController::class, 'clockOut'])->name('hr.attendance.clock-out');
        });

        // Attendance Regularizations routes
        Route::middleware('permission:manage-attendance-regularizations')->group(function () {
            Route::get('attendance-regularizations', [\App\Http\Controllers\AttendanceRegularizationController::class, 'index'])->name('hr.attendance-regularizations.index');
            Route::post('attendance-regularizations', [\App\Http\Controllers\AttendanceRegularizationController::class, 'store'])->middleware('permission:create-attendance-regularizations')->name('hr.attendance-regularizations.store');
            Route::put('attendance-regularizations/{regularization}', [\App\Http\Controllers\AttendanceRegularizationController::class, 'update'])->middleware('permission:edit-attendance-regularizations')->name('hr.attendance-regularizations.update');
            Route::delete('attendance-regularizations/{regularization}', [\App\Http\Controllers\AttendanceRegularizationController::class, 'destroy'])->middleware('permission:delete-attendance-regularizations')->name('hr.attendance-regularizations.destroy');
            Route::put('attendance-regularizations/{regularization}/status', [\App\Http\Controllers\AttendanceRegularizationController::class, 'updateStatus'])->middleware('permission:approve-attendance-regularizations')->name('hr.attendance-regularizations.update-status');
            Route::get('attendance-regularizations/get-employee-attendance/{id}', [\App\Http\Controllers\AttendanceRegularizationController::class, 'getEmployeeAttendance'])->name('hr.attendance-regularizations.get-employee-attendance');
        });

        // Time Entries routes
        Route::middleware('permission:manage-time-entries')->group(function () {
            Route::get('time-entries', [\App\Http\Controllers\TimeEntryController::class, 'index'])->name('hr.time-entries.index');
            Route::post('time-entries', [\App\Http\Controllers\TimeEntryController::class, 'store'])->middleware('permission:create-time-entries')->name('hr.time-entries.store');
            Route::put('time-entries/{timeEntry}', [\App\Http\Controllers\TimeEntryController::class, 'update'])->middleware('permission:edit-time-entries')->name('hr.time-entries.update');
            Route::delete('time-entries/{timeEntry}', [\App\Http\Controllers\TimeEntryController::class, 'destroy'])->middleware('permission:delete-time-entries')->name('hr.time-entries.destroy');
            Route::put('time-entries/{timeEntry}/status', [\App\Http\Controllers\TimeEntryController::class, 'updateStatus'])->middleware('permission:approve-time-entries')->name('hr.time-entries.update-status');
        });

        // Salary Components routes
        Route::middleware('permission:manage-salary-components')->group(function () {
            Route::get('salary-components', [\App\Http\Controllers\SalaryComponentController::class, 'index'])->name('hr.salary-components.index');
            Route::post('salary-components', [\App\Http\Controllers\SalaryComponentController::class, 'store'])->middleware('permission:create-salary-components')->name('hr.salary-components.store');
            Route::put('salary-components/{salaryComponent}', [\App\Http\Controllers\SalaryComponentController::class, 'update'])->middleware('permission:edit-salary-components')->name('hr.salary-components.update');
            Route::delete('salary-components/{salaryComponent}', [\App\Http\Controllers\SalaryComponentController::class, 'destroy'])->middleware('permission:delete-salary-components')->name('hr.salary-components.destroy');
            Route::put('salary-components/{salaryComponent}/toggle-status', [\App\Http\Controllers\SalaryComponentController::class, 'toggleStatus'])->middleware('permission:edit-salary-components')->name('hr.salary-components.toggle-status');
        });

        // Employee Salaries routes
        Route::middleware('permission:manage-employee-salaries')->group(function () {
            Route::get('employee-salaries', [\App\Http\Controllers\EmployeeSalaryController::class, 'index'])->name('hr.employee-salaries.index');
            Route::post('employee-salaries', [\App\Http\Controllers\EmployeeSalaryController::class, 'store'])->middleware('permission:create-employee-salaries')->name('hr.employee-salaries.store');
            Route::put('employee-salaries/{employeeSalary}', [\App\Http\Controllers\EmployeeSalaryController::class, 'update'])->middleware('permission:edit-employee-salaries')->name('hr.employee-salaries.update');
            Route::delete('employee-salaries/{employeeSalary}', [\App\Http\Controllers\EmployeeSalaryController::class, 'destroy'])->middleware('permission:delete-employee-salaries')->name('hr.employee-salaries.destroy');
            Route::put('employee-salaries/{employeeSalary}/toggle-status', [\App\Http\Controllers\EmployeeSalaryController::class, 'toggleStatus'])->middleware('permission:edit-employee-salaries')->name('hr.employee-salaries.toggle-status');
            Route::get('employee-salaries/{employeeSalary}/payroll', [\App\Http\Controllers\EmployeeSalaryController::class, 'showPayroll'])->middleware('permission:view-employee-salaries')->name('hr.employee-salaries.show-payroll');
            Route::get('employee-salaries/{employeeSalary}/payroll/{payrollRun}', [\App\Http\Controllers\EmployeeSalaryController::class, 'getPayrollCalculation'])->middleware('permission:view-employee-salaries')->name('hr.employee-salaries.get-payroll-calculation');
        });

        // Payroll Runs routes
        Route::middleware('permission:manage-payroll-runs')->group(function () {
            Route::get('payroll-runs', [\App\Http\Controllers\PayrollRunController::class, 'index'])->name('hr.payroll-runs.index');
            Route::get('payroll-runs/preview-employees', [\App\Http\Controllers\PayrollRunController::class, 'previewEmployees'])->middleware('permission:create-payroll-runs')->name('hr.payroll-runs.preview-employees');
            Route::get('payroll-runs/check-overlapping', [\App\Http\Controllers\PayrollRunController::class, 'checkOverlapping'])->middleware('permission:create-payroll-runs')->name('hr.payroll-runs.check-overlapping');
            Route::get('payroll-runs/scope-filter-options', [\App\Http\Controllers\PayrollRunController::class, 'scopeFilterOptions'])->middleware('permission:create-payroll-runs')->name('hr.payroll-runs.scope-filter-options');
            Route::get('payroll-runs/export-summary', [\App\Http\Controllers\PayrollRunController::class, 'exportSummary'])->middleware('permission:view-payroll-runs')->name('hr.payroll-runs.export-summary');
            Route::get('payroll-runs/{payrollRun}/export-advances', [\App\Http\Controllers\PayrollRunController::class, 'exportAdvances'])->middleware('permission:view-payroll-runs')->name('hr.payroll-runs.export-advances');
            Route::get('payroll-runs/{payrollRun}/export-salary-register', [\App\Http\Controllers\PayrollRunController::class, 'exportSalaryRegister'])->middleware('permission:view-payroll-runs')->name('hr.payroll-runs.export-salary-register');
            Route::get('payroll-runs/{payrollRun}', [\App\Http\Controllers\PayrollRunController::class, 'show'])->middleware('permission:view-payroll-runs')->name('hr.payroll-runs.show');
            Route::post('payroll-runs', [\App\Http\Controllers\PayrollRunController::class, 'store'])->middleware('permission:create-payroll-runs')->name('hr.payroll-runs.store');
            Route::put('payroll-runs/{payrollRun}', [\App\Http\Controllers\PayrollRunController::class, 'update'])->middleware('permission:edit-payroll-runs')->name('hr.payroll-runs.update');
            Route::delete('payroll-runs/{payrollRun}', [\App\Http\Controllers\PayrollRunController::class, 'destroy'])->middleware('permission:delete-payroll-runs')->name('hr.payroll-runs.destroy');
            Route::get('payroll-runs/{payrollRun}/mispunches', [\App\Http\Controllers\PayrollRunController::class, 'checkMispunches'])->middleware('permission:process-payroll-runs')->name('hr.payroll-runs.mispunches');
            Route::get('payroll-runs/{payrollRun}/mispunch-report', [\App\Http\Controllers\PayrollRunController::class, 'mispunchReport'])->middleware('permission:process-payroll-runs')->name('hr.payroll-runs.mispunch-report');
            Route::get('payroll-runs/{payrollRun}/initiate-process', [\App\Http\Controllers\PayrollRunController::class, 'initiateProcess'])->middleware('permission:process-payroll-runs')->name('hr.payroll-runs.initiate-process');
            Route::post('payroll-runs/{payrollRun}/process-batch', [\App\Http\Controllers\PayrollRunController::class, 'processBatch'])->middleware('permission:process-payroll-runs')->name('hr.payroll-runs.process-batch');
            Route::post('payroll-runs/{payrollRun}/finalize', [\App\Http\Controllers\PayrollRunController::class, 'finalize'])->middleware('permission:process-payroll-runs')->name('hr.payroll-runs.finalize');
            Route::put('payroll-runs/{payrollRun}/process', [\App\Http\Controllers\PayrollRunController::class, 'process'])->middleware('permission:process-payroll-runs')->name('hr.payroll-runs.process');
            Route::put('payroll-runs/{payrollRun}/confirm', [\App\Http\Controllers\PayrollRunController::class, 'confirm'])->middleware('permission:process-payroll-runs')->name('hr.payroll-runs.confirm');
            Route::put('payroll-runs/{payrollRun}/regenerate', [\App\Http\Controllers\PayrollRunController::class, 'regenerate'])->middleware('permission:process-payroll-runs')->name('hr.payroll-runs.regenerate');
            Route::post('payroll-runs/{payrollRun}/regenerate-employee', [\App\Http\Controllers\PayrollRunController::class, 'regenerateEmployee'])->middleware('permission:process-payroll-runs')->name('hr.payroll-runs.regenerate-employee');
        });

        // Payslips routes
        Route::middleware('permission:manage-payslips')->group(function () {
            Route::get('payslips', [\App\Http\Controllers\PayslipController::class, 'index'])->name('hr.payslips.index');
            Route::post('payslips/generate', [\App\Http\Controllers\PayslipController::class, 'generate'])->middleware('permission:create-payslips')->name('hr.payslips.generate');
            Route::post('payslips/bulk-generate', [\App\Http\Controllers\PayslipController::class, 'bulkGenerate'])->middleware('permission:create-payslips')->name('hr.payslips.bulk-generate');
            Route::get('payslips/{payslip}/download', [\App\Http\Controllers\PayslipController::class, 'download'])->middleware('permission:download-payslips')->name('hr.payslips.download');
            Route::put('payslips/{payslip}', [\App\Http\Controllers\PayslipController::class, 'update'])->middleware('permission:create-payslips')->name('hr.payslips.update');
            Route::put('payslips/{id}/toggle-hold', [\App\Http\Controllers\PayslipController::class, 'toggleHold'])->middleware('permission:create-payslips')->name('hr.payslips.toggle-hold');
            Route::get('payslips/export/bulk-excel', [\App\Http\Controllers\PayslipController::class, 'exportBulkExcel'])->middleware('permission:download-payslips')->name('hr.payslips.export-bulk-excel');
        });



        // Plans management routes (admin only)
        Route::middleware('permission:manage-plans')->group(function () {
            Route::get('plans/create', [PlanController::class, 'create'])->middleware('permission:create-plans')->name('plans.create');
            Route::post('plans', [PlanController::class, 'store'])->middleware('permission:create-plans')->name('plans.store');
            Route::get('plans/{plan}/edit', [PlanController::class, 'edit'])->middleware('permission:edit-plans')->name('plans.edit');
            Route::put('plans/{plan}', [PlanController::class, 'update'])->middleware('permission:edit-plans')->name('plans.update');
            Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->middleware('permission:delete-plans')->name('plans.destroy');
            Route::post('plans/{plan}/toggle-status', [PlanController::class, 'toggleStatus'])->name('plans.toggle-status');
        });

        // Plan Orders routes
        Route::middleware('permission:manage-plan-orders')->group(function () {
            Route::get('plan-orders', [PlanOrderController::class, 'index'])->middleware('permission:manage-plan-orders')->name('plan-orders.index');
            Route::post('plan-orders/{planOrder}/approve', [PlanOrderController::class, 'approve'])->middleware('permission:approve-plan-orders')->name('plan-orders.approve');
            Route::post('plan-orders/{planOrder}/reject', [PlanOrderController::class, 'reject'])->middleware('permission:reject-plan-orders')->name('plan-orders.reject');
        });

        // Plan Requests routes (placeholder)
        Route::get('plan-requests', function () {
            return Inertia::render('plans/plan-requests');
        })->name('plan-requests.index');

        // Companies routes
        Route::middleware(['checksaas', 'permission:manage-companies'])->group(function () {
            Route::get('companies', [CompanyController::class, 'index'])->middleware('permission:manage-companies')->name('companies.index');
            Route::post('companies', [CompanyController::class, 'store'])->middleware('permission:create-companies')->name('companies.store');
            Route::put('companies/{company}', [CompanyController::class, 'update'])->middleware('permission:edit-companies')->name('companies.update');
            Route::delete('companies/{company}', [CompanyController::class, 'destroy'])->middleware('permission:delete-companies')->name('companies.destroy');
            Route::put('companies/{company}/reset-password', [CompanyController::class, 'resetPassword'])->middleware('permission:reset-password-companies')->name('companies.reset-password');
            Route::put('companies/{company}/toggle-status', [CompanyController::class, 'toggleStatus'])->middleware('permission:toggle-status-companies')->name('companies.toggle-status');
            Route::get('companies/{company}/plans', [CompanyController::class, 'getPlans'])->middleware('permission:manage-plans-companies')->name('companies.plans');
            Route::put('companies/{company}/upgrade-plan', [CompanyController::class, 'upgradePlan'])->middleware('permission:upgrade-plan-companies')->name('companies.upgrade-plan');
        });


        // Coupons routes
        Route::middleware(['checksaas', 'permission:manage-coupons'])->group(function () {
            Route::get('coupons', [CouponController::class, 'index'])->middleware('permission:manage-coupons')->name('coupons.index');
            Route::get('coupons/{coupon}', [CouponController::class, 'show'])->middleware('permission:view-coupons')->name('coupons.show');
            Route::post('coupons', [CouponController::class, 'store'])->middleware('permission:create-coupons')->name('coupons.store');
            Route::put('coupons/{coupon}', [CouponController::class, 'update'])->middleware('permission:edit-coupons')->name('coupons.update');
            Route::put('coupons/{coupon}/toggle-status', [CouponController::class, 'toggleStatus'])->middleware('permission:toggle-status-coupons')->name('coupons.toggle-status');
            Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->middleware('permission:delete-coupons')->name('coupons.destroy');
        });

        // Plan Requests routes
        Route::middleware(['checksaas', 'permission:manage-plan-requests'])->group(function () {
            Route::get('plan-requests', [PlanRequestController::class, 'index'])->middleware('permission:manage-plan-requests')->name('plan-requests.index');
            Route::post('plan-requests/{planRequest}/approve', [PlanRequestController::class, 'approve'])->middleware('permission:approve-plan-requests')->name('plan-requests.approve');
            Route::post('plan-requests/{planRequest}/reject', [PlanRequestController::class, 'reject'])->middleware('permission:reject-plan-requests')->name('plan-requests.reject');
        });



        // Referral routes
        Route::middleware(['checksaas', 'permission:manage-referral'])->group(function () {
            Route::get('referral', [ReferralController::class, 'index'])->middleware('permission:manage-referral')->name('referral.index');
            Route::get('referral/referred-users', [ReferralController::class, 'getReferredUsers'])->middleware('permission:manage-users-referral')->name('referral.referred-users');
            Route::post('referral/settings', [ReferralController::class, 'updateSettings'])->middleware('permission:manage-setting-referral')->name('referral.settings.update');
            Route::post('referral/payout-request', [ReferralController::class, 'createPayoutRequest'])->middleware('permission:manage-payout-referral')->name('referral.payout-request.create');
            Route::post('referral/payout-request/{payoutRequest}/approve', [ReferralController::class, 'approvePayoutRequest'])->middleware('permission:approve-payout-referral')->name('referral.payout-request.approve');
            Route::post('referral/payout-request/{payoutRequest}/reject', [ReferralController::class, 'rejectPayoutRequest'])->middleware('permission:reject-payout-referral')->name('referral.payout-request.reject');
        });

        // Currencies routes
        Route::middleware('permission:manage-currencies')->group(function () {
            Route::get('currencies', [CurrencyController::class, 'index'])->middleware('permission:manage-currencies')->name('currencies.index');
            Route::post('currencies', [CurrencyController::class, 'store'])->middleware('permission:create-currencies')->name('currencies.store');
            Route::put('currencies/{currency}', [CurrencyController::class, 'update'])->middleware('permission:edit-currencies')->name('currencies.update');
            Route::delete('currencies/{currency}', [CurrencyController::class, 'destroy'])->middleware('permission:delete-currencies')->name('currencies.destroy');
        });

        // ChatGPT routes
        Route::post('api/chatgpt/generate', [\App\Http\Controllers\ChatGptController::class, 'generate'])->name('chatgpt.generate');

        // Language management
        Route::get('manage-language/{lang?}', [LanguageController::class, 'managePage'])->middleware('permission:manage-language')->name('manage-language');
        Route::get('language/load', [LanguageController::class, 'load'])->name('language.load');
        Route::match(['POST', 'PATCH'], 'language/save', [LanguageController::class, 'save'])->middleware('permission:edit-language')->name('language.save');
        Route::post('/languages/create', [LanguageController::class, 'createLanguage'])->name('languages.create');
        Route::delete('/languages/{languageCode}', [LanguageController::class, 'deleteLanguage'])->name('languages.delete');
        Route::patch('/languages/{languageCode}/toggle', [LanguageController::class, 'toggleLanguageStatus'])->name('languages.toggle');
        Route::post('/languages/{locale}/update', [LanguageController::class, 'updateTranslations'])->name('languages.update');

        // Landing Page content management (Super Admin only)
        Route::middleware('App\Http\Middleware\SuperAdminMiddleware')->group(function () {
            Route::get('landing-page/settings', [LandingPageController::class, 'settings'])->name('landing-page.settings');
            Route::post('landing-page/settings', [LandingPageController::class, 'updateSettings'])->name('landing-page.settings.update');

            Route::resource('landing-page/custom-pages', CustomPageController::class)->names([
                'index' => 'landing-page.custom-pages.index',
                'store' => 'landing-page.custom-pages.store',
                'update' => 'landing-page.custom-pages.update',
                'destroy' => 'landing-page.custom-pages.destroy'
            ]);
        });

        // Calendar routes
        Route::middleware('permission:view-calendar')->group(function () {
            Route::get('calendar', [\App\Http\Controllers\CalendarController::class, 'index'])->name('calendar.index');
        });

        // Impersonation routes
        Route::middleware('App\Http\Middleware\SuperAdminMiddleware')->group(function () {
            Route::get('impersonate/{userId}', [ImpersonateController::class, 'start'])->name('impersonate.start');
        });

        Route::post('impersonate/leave', [ImpersonateController::class, 'leave'])->name('impersonate.leave');
    }); // End plan.access middleware group

    Route::get('activity-logs', [App\Http\Controllers\ActivityLogController::class, 'index'])->name('hr.activity-logs.index');
    Route::get('api/activity-logs/latest', [App\Http\Controllers\ActivityLogController::class, 'latest'])->name('api.activity-logs.latest');
    // Legacy /hr/* URLs → direct paths (bookmarks, old links)
    Route::any('hr/{path}', function (string $path) {
        $query = request()->getQueryString();
        $target = '/' . $path . ($query ? '?' . $query : '');

        return redirect($target, 301);
    })->where('path', '.*');

});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';

Route::match(['GET', 'POST'], 'payments/easebuzz/success', [EasebuzzPaymentController::class, 'success'])->name('easebuzz.success');
Route::post('payments/easebuzz/callback', [EasebuzzPaymentController::class, 'callback'])->name('easebuzz.callback');

// Cookie consent routes
Route::post('/cookie-consent/store', [CookieConsentController::class, 'store'])->name('cookie.consent.store');
Route::get('/cookie-consent/download', [CookieConsentController::class, 'download'])->name('cookie.consent.download');
