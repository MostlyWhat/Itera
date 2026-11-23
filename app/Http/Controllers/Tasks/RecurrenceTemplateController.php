<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\RecurrenceTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Weekly tasks (ITERA-09). They are made from the task form. Here the user
 * stops one. The Tasks it already made stay, with their status.
 */
class RecurrenceTemplateController extends Controller
{
    /**
     * Stop a weekly task. Its Tasks keep their history.
     */
    public function destroy(RecurrenceTemplate $recurrenceTemplate): RedirectResponse
    {
        Gate::authorize('update', $recurrenceTemplate->project);

        $recurrenceTemplate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The task no longer repeats. Its past tasks stay.')]);

        return back();
    }
}
