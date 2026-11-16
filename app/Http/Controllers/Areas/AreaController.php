<?php

namespace App\Http\Controllers\Areas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Areas\SaveAreaRequest;
use App\Models\Area;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Areas of the current team. The team comes from the URL. The
 * EnsureTeamMembership middleware checks the membership and makes it the
 * current team. AreaPolicy checks each Area that the URL names.
 */
class AreaController extends Controller
{
    /**
     * The Areas page is part of the Projects page now. Old links go there.
     */
    public function index(): RedirectResponse
    {
        return to_route('projects.index');
    }

    /**
     * Create an Area at the end of the current team's list.
     */
    public function store(SaveAreaRequest $request): RedirectResponse
    {
        $request->user()->currentTeam->areas()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Area created.')]);

        return back();
    }

    /**
     * Rename an Area.
     */
    public function update(SaveAreaRequest $request, Area $area): RedirectResponse
    {
        Gate::authorize('update', $area);

        $area->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Area renamed.')]);

        return back();
    }

    /**
     * Archive an Area. Its Projects stay with it and come back on restore.
     */
    public function archive(Area $area): RedirectResponse
    {
        Gate::authorize('update', $area);

        $area->archive();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Area archived.')]);

        return back();
    }

    /**
     * Restore an archived Area.
     */
    public function unarchive(Area $area): RedirectResponse
    {
        Gate::authorize('update', $area);

        $area->unarchive();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Area restored.')]);

        return back();
    }
}
