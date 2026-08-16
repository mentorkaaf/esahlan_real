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
use App\Http\Controllers\HR\HrJobPostingController;
use App\Http\Controllers\HR\HrApplicantController;
use App\Http\Controllers\HR\HrPerformanceCycleController;
use App\Http\Controllers\HR\HrDisciplinaryController;
use App\Http\Controllers\HR\HrAnnouncementController;
use App\Http\Controllers\HR\HrReportController;
use App\Http\Controllers\HR\HrWorkforceController;
use App\Http\Controllers\HR\HrModuleDepartmentController;
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

    // ── Recruitment ───────────────────────────────────────────────────────
    Route::prefix('recruitment')->name('recruitment.')->group(function () {
        // Job postings
        Route::get('postings',            [HrJobPostingController::class, 'index'])->name('postings.index');
        Route::get('postings/create',     [HrJobPostingController::class, 'create'])->name('postings.create');
        Route::post('postings',           [HrJobPostingController::class, 'store'])->name('postings.store');
        Route::get('postings/{posting}',  [HrJobPostingController::class, 'show'])->name('postings.show');
        Route::get('postings/{posting}/edit', [HrJobPostingController::class, 'edit'])->name('postings.edit');
        Route::put('postings/{posting}',  [HrJobPostingController::class, 'update'])->name('postings.update');
        Route::delete('postings/{posting}',[HrJobPostingController::class, 'destroy'])->name('postings.destroy');

        // Applicants
        Route::get('applicants/{applicant}',        [HrApplicantController::class, 'show'])->name('applicants.show');
        Route::patch('applicants/{applicant}',      [HrApplicantController::class, 'update'])->name('applicants.update');
        Route::post('applicants/{applicant}/stage', [HrApplicantController::class, 'moveStage'])->name('applicants.stage');
        Route::get('applicants/{applicant}/cv',     [HrApplicantController::class, 'downloadCv'])->name('applicants.cv');
        Route::post('applicants/{applicant}/interview',           [HrApplicantController::class, 'scheduleInterview'])->name('applicants.interview');
        Route::post('interviews/{interview}/feedback',            [HrApplicantController::class, 'interviewFeedback'])->name('interviews.feedback');
    });

    // ── Performance ───────────────────────────────────────────────────────
    Route::prefix('performance')->name('performance.')->group(function () {
        Route::get('/',                [HrPerformanceCycleController::class, 'index'])->name('index');
        Route::post('/',               [HrPerformanceCycleController::class, 'store'])->name('store');
        Route::patch('{cycle}',        [HrPerformanceCycleController::class, 'update'])->name('update');
        Route::delete('{cycle}',       [HrPerformanceCycleController::class, 'destroy'])->name('destroy');
        Route::get('{cycle}/goals',    [HrPerformanceCycleController::class, 'goals'])->name('goals');
        Route::post('{cycle}/goals',   [HrPerformanceCycleController::class, 'storeGoal'])->name('goals.store');
        Route::delete('goals/{goal}',  [HrPerformanceCycleController::class, 'destroyGoal'])->name('goals.destroy');
        Route::get('{cycle}/reviews',  [HrPerformanceCycleController::class, 'reviews'])->name('reviews');
        Route::post('{cycle}/reviews/{employee}', [HrPerformanceCycleController::class, 'storeReview'])->name('reviews.store');
        Route::get('{cycle}/report',   [HrPerformanceCycleController::class, 'report'])->name('report');
        Route::post('{cycle}/import-commissions', [HrPerformanceCycleController::class, 'importCommissions'])->name('import_commissions');
    });

    // ── Discipline ────────────────────────────────────────────────────────
    Route::prefix('discipline')->name('discipline.')->group(function () {
        Route::get('/',             [HrDisciplinaryController::class, 'index'])->name('index');
        Route::get('create',        [HrDisciplinaryController::class, 'create'])->name('create');
        Route::post('/',            [HrDisciplinaryController::class, 'store'])->name('store');
        Route::get('{case}',        [HrDisciplinaryController::class, 'show'])->name('show');
        Route::post('{case}/investigate', [HrDisciplinaryController::class, 'investigate'])->name('investigate');
        Route::post('{case}/close', [HrDisciplinaryController::class, 'close'])->name('close')
             ->middleware('hr.role:hr_manager,hr_officer');
        Route::post('warnings/{warning}/acknowledge', [HrDisciplinaryController::class, 'acknowledge'])->name('warning.acknowledge');
        Route::get('warnings/{warning}/pdf', [HrDisciplinaryController::class, 'warningPdf'])->name('warning.pdf');
    });

    // ── Announcements ─────────────────────────────────────────────────────
    Route::prefix('announcements')->name('announcements.')->group(function () {
        Route::get('/',             [HrAnnouncementController::class, 'index'])->name('index');
        Route::get('create',        [HrAnnouncementController::class, 'create'])->name('create');
        Route::post('/',            [HrAnnouncementController::class, 'store'])->name('store');
        Route::post('{announcement}/publish', [HrAnnouncementController::class, 'publish'])->name('publish')
             ->middleware('hr.role:hr_manager,hr_officer');
        Route::delete('{announcement}', [HrAnnouncementController::class, 'destroy'])->name('destroy')
             ->middleware('hr.role:hr_manager');
    });

    // ── Module Departments ────────────────────────────────────────────────
    Route::prefix('module-departments')->name('module-departments.')->middleware('hr.role:hr_manager,hr_officer')->group(function () {
        Route::get('/',                                          [HrModuleDepartmentController::class, 'index'])->name('index');
        Route::get('/create',                                    [HrModuleDepartmentController::class, 'create'])->name('create');
        Route::post('/',                                         [HrModuleDepartmentController::class, 'store'])->name('store');
        Route::get('/{moduleDepartment}',                        [HrModuleDepartmentController::class, 'show'])->name('show');
        Route::get('/{moduleDepartment}/edit',                   [HrModuleDepartmentController::class, 'edit'])->name('edit');
        Route::put('/{moduleDepartment}',                        [HrModuleDepartmentController::class, 'update'])->name('update');
        Route::post('/{moduleDepartment}/status',                [HrModuleDepartmentController::class, 'status'])->name('status')
             ->middleware('hr.role:hr_manager');
        Route::post('/{moduleDepartment}/assign-manager',        [HrModuleDepartmentController::class, 'assignManager'])->name('assign-manager')
             ->middleware('hr.role:hr_manager');
        Route::post('/{moduleDepartment}/add-employee',          [HrModuleDepartmentController::class, 'addEmployee'])->name('add-employee');
        Route::delete('/assignment/{assignment}/remove-employee', [HrModuleDepartmentController::class, 'removeEmployee'])->name('remove-employee');
        // JSON helper for dynamic department picker
        Route::get('/by-module/{module}', [HrModuleDepartmentController::class, 'byModule'])->name('by-module');
    });

    // ── Workforce Assignments ─────────────────────────────────────────────
    Route::prefix('workforce')->name('workforce.')->middleware('hr.role:hr_manager,hr_officer')->group(function () {
        Route::get('/',                              [HrWorkforceController::class, 'index'])->name('index');
        Route::get('/module/{module}',               [HrWorkforceController::class, 'module'])->name('module');
        Route::get('/assign',                        [HrWorkforceController::class, 'create'])->name('create');
        Route::post('/assign',                       [HrWorkforceController::class, 'store'])->name('store');
        Route::get('/employee/{employee}',           [HrWorkforceController::class, 'employee'])->name('employee');
        Route::delete('/{assignment}',               [HrWorkforceController::class, 'destroy'])->name('destroy');
        Route::post('/{assignment}/suspend',         [HrWorkforceController::class, 'suspend'])->name('suspend')
             ->middleware('hr.role:hr_manager');
        Route::post('/{assignment}/reactivate',      [HrWorkforceController::class, 'reactivate'])->name('reactivate')
             ->middleware('hr.role:hr_manager');
    });

    // ── Reports ───────────────────────────────────────────────────────────
    Route::get('reports', [HrReportController::class, 'index'])->name('reports.index');

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
