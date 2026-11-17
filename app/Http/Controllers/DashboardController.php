<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TeamInvitation;
use App\Queries\CurrentSprintBoard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentSprintBoard $board): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $team = $request->user()->currentTeam;
        $currentBoard = $board->for($team);

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'board' => $currentBoard,
            // Without a sprint, the dashboard shows the next setup step.
            'setup' => $currentBoard === null ? [
                'hasProject' => Project::query()->whereHas('area', fn ($area) => $area->whereBelongsTo($team))->exists(),
                'hasTask' => Task::query()->whereHas('project.area', fn ($area) => $area->whereBelongsTo($team))->exists(),
            ] : null,
        ]);
    }
}
