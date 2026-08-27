<?php

use App\Http\Controllers\Documents\DownloadMarksheetsController;
use App\Http\Controllers\Documents\DownloadOwnMarksheetController;
use App\Http\Controllers\Documents\DownloadStudentIDCardsController;
use App\Http\Controllers\Hrm\DownloadPayslipController;
use App\Livewire\Academic\ManageAcademicYears;
use App\Livewire\Academic\ManageClasses;
use App\Livewire\Academic\ManageExams;
use App\Livewire\Academic\ManageSections;
use App\Livewire\Academic\ManageShifts;
use App\Livewire\Academic\MarksEntry;
use App\Livewire\Academic\StudentAdmission;
use App\Livewire\Academic\TakeAttendance;
use App\Livewire\Documents\IDCardGenerator;
use App\Livewire\Documents\MarksheetGenerator;
use App\Livewire\Hrm\LeaveApprovals;
use App\Livewire\Hrm\MyLeaves;
use App\Livewire\Hrm\MyPayslips;
use App\Livewire\Hrm\PayrollGenerator;
use App\Livewire\Hrm\StaffDirectory;
use App\Livewire\Library\BookInventory;
use App\Livewire\Library\IssueReturnManager;
use App\Livewire\Library\MyBooks;
use App\Livewire\MainDashboard;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function (Request $request): RedirectResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return to_route($user->dashboardRoute());
    })->name('dashboard');

    Route::livewire('dashboard/unassigned', 'pages::dashboards.unassigned')
        ->name('dashboard.unassigned');

    Route::prefix('admin')->middleware('role:super_admin|admin')->name('admin.')->group(function () {
        Route::get('dashboard', MainDashboard::class)->name('dashboard');
        Route::livewire('users', 'pages::admin.users.index')->name('users.index');
        Route::get('academic/shifts', ManageShifts::class)->name('academic.shifts');
        Route::get('academic/classes', ManageClasses::class)->name('academic.classes');
        Route::get('academic/exams', ManageExams::class)->name('academic.exams');
        Route::get('academic/sections', ManageSections::class)->name('academic.sections');
        Route::get('academic/years', ManageAcademicYears::class)->name('academic.years');
        Route::get('students/admit', StudentAdmission::class)->name('students.admit');
        Route::get('documents/id-cards', IDCardGenerator::class)->name('documents.id_cards.index');
        Route::get('documents/id-cards/download', DownloadStudentIDCardsController::class)
            ->middleware('signed')
            ->name('documents.id_cards.download');
    });

    Route::prefix('attendance')->middleware('role:super_admin|admin|teacher')->name('attendance.')->group(function () {
        Route::get('take', TakeAttendance::class)->name('take');
    });

    Route::prefix('exams')->middleware('role:super_admin|admin|teacher')->name('exams.')->group(function () {
        Route::get('marks', MarksEntry::class)->name('marks');
    });

    Route::prefix('marksheets')->middleware('role:super_admin|admin|teacher')->name('documents.marksheets.')->group(function () {
        Route::get('/', MarksheetGenerator::class)->name('index');
        Route::get('download', DownloadMarksheetsController::class)->middleware('signed')->name('download');
    });

    Route::get('my-marksheets/{result}', DownloadOwnMarksheetController::class)
        ->middleware('role:student|guardian')
        ->name('portal.marksheets.download');

    Route::prefix('hrm')->middleware('role:super_admin|admin')->name('hrm.admin.')->group(function () {
        Route::get('staff', StaffDirectory::class)->name('staff');
        Route::get('leave-approvals', LeaveApprovals::class)->name('leave_approvals');
        Route::get('payroll', PayrollGenerator::class)->name('payroll');
    });

    Route::prefix('my-hr')->middleware('role:teacher')->name('hrm.self.')->group(function () {
        Route::get('leaves', MyLeaves::class)->name('leaves');
        Route::get('payslips', MyPayslips::class)->name('payslips');
        Route::get('payslips/{payslip}/download', DownloadPayslipController::class)->middleware('signed')->name('payslips.download');
    });

    Route::prefix('library')->middleware('role:super_admin|admin')->name('library.admin.')->group(function () {
        Route::get('books', BookInventory::class)->name('books');
        Route::get('issues', IssueReturnManager::class)->name('issues');
    });

    Route::get('my-library-books', MyBooks::class)
        ->middleware('role:student|teacher')
        ->name('library.my_books');

    Route::prefix('teacher')->middleware('role:teacher')->name('teacher.')->group(function () {
        Route::get('dashboard', MainDashboard::class)->name('dashboard');
    });

    Route::prefix('student')->middleware('role:student')->name('student.')->group(function () {
        Route::get('dashboard', MainDashboard::class)->name('dashboard');
    });

    Route::prefix('guardian')->middleware('role:guardian')->name('guardian.')->group(function () {
        Route::get('dashboard', MainDashboard::class)->name('dashboard');
    });
});

require __DIR__.'/settings.php';
