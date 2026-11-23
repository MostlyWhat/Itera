<?php

namespace App\Http\Controllers\Areas;

use App\Enums\SortingMode;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Save how the user's Tasks are sorted (ITERA-08): Stack order, Deadline,
 * or Balanced. The backlog, the board, and the Priority page use it.
 */
class SortingModeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sorting_mode' => ['required', Rule::enum(SortingMode::class)],
        ]);

        $request->user()->update(['sorting_mode' => $validated['sorting_mode']]);

        return back();
    }
}
