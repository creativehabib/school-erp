<?php

use App\Http\Controllers\Documents\DownloadStudentIDCardsController;
use App\Livewire\Academic\ManageAcademicYears;
use App\Livewire\Academic\ManageClasses;
use App\Livewire\Academic\ManageShifts;
use App\Livewire\Academic\MarksEntry;
use App\Livewire\Academic\StudentAdmission;
use App\Livewire\Academic\TakeAttendance;
use App\Livewire\Documents\IDCardGenerator;
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
        Route::livewire('dashboard', 'pages::dashboards.admin')->name('dashboard');
        Route::livewire('users', 'pages::admin.users.index')->name('users.index');
        Route::get('academic/shifts', ManageShifts::class)->name('academic.shifts');
        Route::get('academic/classes', ManageClasses::class)->name('academic.classes');
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

    Route::prefix('teacher')->middleware('role:teacher')->name('teacher.')->group(function () {
        Route::livewire('dashboard', 'pages::dashboards.teacher')->name('dashboard');
    });

    Route::prefix('student')->middleware('role:student')->name('student.')->group(function () {
        Route::livewire('dashboard', 'pages::dashboards.student')->name('dashboard');
    });

    Route::prefix('guardian')->middleware('role:guardian')->name('guardian.')->group(function () {
        Route::livewire('dashboard', 'pages::dashboards.guardian')->name('dashboard');
    });
});

require __DIR__.'/settings.php';
