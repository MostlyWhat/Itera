<?php

namespace App\Http\Controllers\Sprints;

use App\Actions\Sprints\CompleteSprintReview;
use App\Enums\ClosingStatus;
use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sprints\ReviewSprintRequest;
use App\Models\Sprint;
use App\Models\SprintCommitment;
use App\Queries\TargetSuggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The review of a Sprint after its week ends (ITERA-10): the points of the
 * week, a short retrospective, a choice for each unfinished Task, and a
 * suggested target for the next Sprint. After the review, the user can edit
 * the notes and the next target. The results of the week stay as saved.
 */
class SprintReviewController extends Controller
{
    /**
     * Show the review form: new for a Sprint that waits for its review, or
     * the saved review of a reviewed Sprint.
     */
    public function show(Sprint $sprint, TargetSuggestion $suggestion): Response|RedirectResponse
    {
        Gate::authorize('view', $sprint);

        $reviewed = $sprint->status === SprintStatus::Completed;

        if (! $reviewed && ! $sprint->needsReview()) {
            return to_route('sprints.show', ['sprint' => $sprint]);
        }

        $sprint->load(['commitments.task.project', 'retrospective']);
        [$done, $open] = $this->partition($sprint, $reviewed);
        $completed = $reviewed ? (int) $sprint->completed_points : (int) $done->sum('committed_points');

        $next = $sprint->team->sprints()->currentOrUpcoming()->with('commitments')->reorder('week_start')->first();

        return Inertia::render('sprints/Review', [
            'reviewed' => $reviewed,
            'sprint' => [
                'id' => $sprint->id,
                'weekStart' => $sprint->week_start->toDateString(),
                'weekEnd' => $sprint->week_end->toDateString(),
                'goal' => $sprint->goal,
                'targetPoints' => $sprint->target_points,
                'committedPoints' => $sprint->committedPoints(),
                'completedPoints' => $completed,
            ],
            'done' => $done->map(fn (SprintCommitment $commitment) => $this->row($commitment))->values(),
            'unfinished' => $open->map(fn (SprintCommitment $commitment) => [
                ...$this->row($commitment),
                'deleted' => $commitment->task === null,
                'statusLabel' => $commitment->task?->status->label(),
                'closingStatus' => $commitment->closing_status?->value,
                'closingLabel' => $commitment->closing_status?->label(),
            ])->values(),
            'retrospective' => $sprint->retrospective?->only(['went_well', 'blockers', 'improve_next']),
            'nextSprint' => $next === null ? null : [
                'weekStart' => $next->week_start->toDateString(),
                'weekEnd' => $next->week_end->toDateString(),
                'targetPoints' => $next->target_points,
                'committedPoints' => $next->committedPoints(),
            ],
            'suggestion' => $suggestion->for($sprint, $completed),
        ]);
    }

    /**
     * Save the review, close the Sprint, and move the unfinished Tasks.
     */
    public function store(ReviewSprintRequest $request, Sprint $sprint, CompleteSprintReview $review): RedirectResponse
    {
        Gate::authorize('review', $sprint);

        $next = $review->handle(
            $sprint,
            $request->user(),
            $request->validated('decisions', []),
            $request->safe()->only(['went_well', 'blockers', 'improve_next']),
            $this->nextTarget($request),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $next === null
            ? __('Review saved.')
            : __('Review saved. The sprint of :week is ready for you.', ['week' => $next->week_start->format('M j')])]);

        return to_route('sprints.show', ['sprint' => $sprint]);
    }

    /**
     * Change the notes of a reviewed Sprint, and the target of the next one.
     */
    public function update(ReviewSprintRequest $request, Sprint $sprint, CompleteSprintReview $review): RedirectResponse
    {
        Gate::authorize('editReview', $sprint);

        $review->edit(
            $sprint,
            $request->user(),
            $request->safe()->only(['went_well', 'blockers', 'improve_next']),
            $this->nextTarget($request),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review saved.')]);

        return to_route('sprints.show', ['sprint' => $sprint]);
    }

    /**
     * Split the commitments into done and unfinished. Before the review the
     * Task status decides. After it, the saved closing status decides.
     *
     * @return array{0: Collection<int, SprintCommitment>, 1: Collection<int, SprintCommitment>}
     */
    private function partition(Sprint $sprint, bool $reviewed): array
    {
        [$done, $open] = $sprint->commitments->toBase()->partition(fn (SprintCommitment $commitment) => $reviewed
            ? $commitment->closing_status === ClosingStatus::Done
            : $commitment->task?->status === TaskStatus::Done);

        return [$done, $open];
    }

    private function nextTarget(ReviewSprintRequest $request): ?int
    {
        $target = $request->validated('next_target');

        return $target === null ? null : (int) $target;
    }

    /**
     * @return array{id: int, title: string, points: int|null, projectName: string|null}
     */
    private function row(SprintCommitment $commitment): array
    {
        return [
            'id' => $commitment->id,
            'title' => $commitment->task_title,
            'points' => $commitment->committed_points,
            'projectName' => $commitment->task?->project->name,
        ];
    }
}
