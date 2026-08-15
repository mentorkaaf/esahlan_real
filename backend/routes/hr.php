<?php

use App\Http\Controllers\HR\HrAuthController;
use App\Http\Controllers\HR\HrDashboardController;
use App\Http\Controllers\HR\HrEmployeeController;
use App\Http\Controllers\HR\HrDepartmentController;
use App\Http\Controllers\HR\HrPositionController;
use App\Http\Controllers\HR\HrContractController;
use App\Http\Controllers\HR\HrDocumentController;
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
    Route::get('audit', [\App\Http\Controllers\HR\HrAuditController::class, 'index'])
         ->name('audit.index')
         ->middleware('hr.role:hr_manager');
});
