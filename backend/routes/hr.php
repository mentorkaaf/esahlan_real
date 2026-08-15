<?php

use App\Http\Controllers\HR\HrAuthController;
use App\Http\Controllers\HR\HrDashboardController;
use App\Http\Controllers\HR\HrEmployeeController;
use App\Http\Controllers\HR\HrDepartmentController;
use App\Http\Controllers\HR\HrPositionController;
use App\Http\Controllers\HR\HrContractController;
use App\Http\Controllers\HR\HrDocumentController;
use App\Http\Controllers\HR\HrAttendanceController;
use App\Http\Controllers\HR\HrLeaveController;
use App\Http\Controllers\HR\HrAuditController;
use App\Http\Controllers\HR\HrPayrollController;
use App\Http\Controllers\HR\HrComponentController;
use App\Http\Controllers\HR\HrCommissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HR Panel Routes  (prefix: /hr,  name prefix: hr.)
|--------------------------------------------------------------------------
*/

// ── Auth (guest) ─────────────────────────────────────────────────────────
Route::middleware('guest:hr')->group(function () {
    Route::get('login',          [HrAuthController::class, 'showLogin'])->name('login');
    Route::post('login',         [HrAuthController::class, 'login'])->name('login.submit');
});

Route::post('logout', [HrAuthController::class, 'logout'])->name('logout');

// ── Authenticated HR panel ────────────────────────────────────────────────
Route::middleware('auth.hr')->group(function () {

    // Dashboard
    Route::get('/',           [HrDashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard',   [HrDashboardController::class, 'index'])->name('dashboard.alt');

    // Employees
    Route::resource('employees', HrEmployeeController::class);
    Route::post('employees/{employee}/terminate', [HrEmployeeController::class, 'terminate'])
         ->name('employees.terminate')
         ->middleware('hr.role:hr_manager');

    // Departments (manager only)
    Route::resource('departments', HrDepartmentController::class)
         ->middleware('hr.role:hr_manager');

    // Positions (manager only)
    Route::resource('positions', HrPositionController::class)
         ->middleware('hr.role:hr_manager');

    // Contracts
    Route::resource('employees.contracts', HrContractController::class)
         ->shallow();

    // Documents
    Route::resource('employees.documents', HrDocumentController::class)
         ->shallow();

    // Audit log
    Route::get('audit', [HrAuditController::class, 'index'])
         ->name('audit.index')
         ->middleware('hr.role:hr_manager');

    // ── Attendance ────────────────────────────────────────────────────────
    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('daily',          [HrAttendanceController::class, 'daily'])->name('daily');
        Route::post('mark',          [HrAttendanceController::class, 'mark'])->name('mark');
        Route::post('bulk-present',  [HrAttendanceController::class, 'bulkMarkPresent'])->name('bulk_present');
        Route::get('monthly/{employee}', [HrAttendanceController::class, 'monthly'])->name('monthly');
        Route::get('import',         [HrAttendanceController::class, 'importForm'])->name('import');
        Route::post('import',        [HrAttendanceController::class, 'importSubmit'])->name('import.submit');
        Route::post('{attendance}/correct', [HrAttendanceController::class, 'correct'])
             ->name('correct')
             ->middleware('hr.role:hr_manager');
    });

    // ── Payroll ───────────────────────────────────────────────────────────
    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/',                 [HrPayrollController::class, 'index'])->name('index');
        Route::post('generate',         [HrPayrollController::class, 'generate'])->name('generate');
        Route::get('{payroll}',         [HrPayrollController::class, 'show'])->name('show');
        Route::post('{payroll}/submit', [HrPayrollController::class, 'submit'])->name('submit');
        Route::post('{payroll}/approve',[HrPayrollController::class, 'approve'])->name('approve')
             ->middleware('hr.role:hr_manager');
        Route::post('{payroll}/reject', [HrPayrollController::class, 'reject'])->name('reject')
             ->middleware('hr.role:hr_manager');
        Route::delete('{payroll}',      [HrPayrollController::class, 'destroy'])->name('destroy')
             ->middleware('hr.role:hr_manager');
        Route::post('{payroll}/bulk-paid', [HrPayrollController::class, 'bulkMarkPaid'])->name('bulk_paid')
             ->middleware('hr.role:hr_manager');

        // Payslip
        Route::get('payslip/{payslip}',      [HrPayrollController::class, 'payslip'])->name('payslip');
        Route::get('payslip/{payslip}/pdf',  [HrPayrollController::class, 'payslipPdf'])->name('payslip.pdf');
        Route::post('payslip/{payslip}/paid',[HrPayrollController::class, 'markPaid'])->name('payslip.paid')
             ->middleware('hr.role:hr_manager');
    });

    // ── Salary Components (manager only) ──────────────────────────────────
    Route::resource('components', HrComponentController::class)
         ->middleware('hr.role:hr_manager');

    // ── Commissions ───────────────────────────────────────────────────────
    Route::prefix('commissions')->name('commissions.')->group(function () {
        Route::get('/',                      [HrCommissionController::class, 'index'])->name('index');
        Route::get('create',                 [HrCommissionController::class, 'create'])->name('create');
        Route::post('/',                     [HrCommissionController::class, 'store'])->name('store');
        Route::post('{commission}/approve',  [HrCommissionController::class, 'approve'])->name('approve')
             ->middleware('hr.role:hr_manager');
        Route::post('{commission}/reject',   [HrCommissionController::class, 'reject'])->name('reject')
             ->middleware('hr.role:hr_manager');
        Route::delete('{commission}',        [HrCommissionController::class, 'destroy'])->name('destroy');
    });

    // ── Leave ─────────────────────────────────────────────────────────────
    Route::prefix('leaves')->name('leaves.')->group(function () {
        Route::get('/',              [HrLeaveController::class, 'index'])->name('index');
        Route::get('create',         [HrLeaveController::class, 'create'])->name('create');
        Route::post('/',             [HrLeaveController::class, 'store'])->name('store');
        Route::get('{leave}',        [HrLeaveController::class, 'show'])->name('show');
        Route::post('{leave}/approve',[HrLeaveController::class, 'approve'])->name('approve')
             ->middleware('hr.role:hr_manager,hr_officer');
        Route::post('{leave}/reject', [HrLeaveController::class, 'reject'])->name('reject')
             ->middleware('hr.role:hr_manager,hr_officer');
        Route::get('calendar/view',  [HrLeaveController::class, 'calendar'])->name('calendar');
        Route::get('balances/all',   [HrLeaveController::class, 'balances'])->name('balances');
    });
});
