<?php

use App\Http\Controllers\Areas\ArchiveController;
use App\Http\Controllers\Areas\AreaController;
use App\Http\Controllers\Areas\PriorityController;
use App\Http\Controllers\Areas\PriorityOrderController;
use App\Http\Controllers\Areas\ProjectController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Sprints\SprintController;
use App\Http\Controllers\Tasks\SubtaskController;
use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\Tasks\TaskStatusController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // Areas and Projects (ITERA-02)
        Route::get('areas', [AreaController::class, 'index'])->name('areas.index');
        Route::post('areas', [AreaController::class, 'store'])->name('areas.store');
        Route::patch('areas/{area}', [AreaController::class, 'update'])->name('areas.update');
        Route::post('areas/{area}/archive', [AreaController::class, 'archive'])->name('areas.archive');
        Route::delete('areas/{area}/archive', [AreaController::class, 'unarchive'])->name('areas.unarchive');

        // Priority order of Areas and Projects (ITERA-07)
        Route::get('priority', PriorityController::class)->name('priority');
        Route::put('areas/order', [PriorityOrderController::class, 'areas'])->name('areas.order');
        Route::put('areas/{area}/projects/order', [PriorityOrderController::class, 'projects'])->name('projects.order');

        Route::post('areas/{area}/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::post('projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
        Route::delete('projects/{project}/archive', [ProjectController::class, 'unarchive'])->name('projects.unarchive');

        // Tasks (ITERA-03)
        Route::get('projects', [TaskController::class, 'projects'])->name('projects.index');
        Route::get('archive', ArchiveController::class)->name('archive');

        // Guide to the method and the app
        Route::inertia('guide', 'Guide')->name('guide');
        Route::get('projects/{project}', [TaskController::class, 'index'])->name('projects.show');
        Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

        // Sprints (ITERA-04)
        Route::get('sprints', [SprintController::class, 'index'])->name('sprints.index');
        Route::post('sprints', [SprintController::class, 'store'])->name('sprints.store');
        Route::get('sprints/{sprint}', [SprintController::class, 'show'])->name('sprints.show');
        Route::patch('sprints/{sprint}', [SprintController::class, 'update'])->name('sprints.update');
        Route::post('sprints/{sprint}/tasks', [SprintController::class, 'addTask'])->name('sprints.tasks.store');

        // Kanban board on the dashboard (ITERA-05)
        Route::patch('tasks/{task}/status', TaskStatusController::class)->name('tasks.status.update');

        // Subtasks (ITERA-06)
        Route::post('tasks/{task}/subtasks', [SubtaskController::class, 'store'])->name('subtasks.store');
        Route::patch('subtasks/{subtask}', [SubtaskController::class, 'update'])->name('subtasks.update');
        Route::delete('subtasks/{subtask}', [SubtaskController::class, 'destroy'])->name('subtasks.destroy');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
