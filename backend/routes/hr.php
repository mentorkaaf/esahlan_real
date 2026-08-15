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
