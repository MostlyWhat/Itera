<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PlanningUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Planning settings: the first day of the user's week. New sprints start on
 * this day. Sprints that exist keep their dates.
 */
class PlanningController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Planning', [
            'weekStart' => $request->user()->week_start,
        ]);
    }

    public function update(PlanningUpdateRequest $request): RedirectResponse
    {
        $request->user()->update(['week_start' => $request->integer('week_start')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Planning settings saved.')]);

        return to_route('planning.edit');
    }
}
