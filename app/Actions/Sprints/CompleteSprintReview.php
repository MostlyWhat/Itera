<?php

namespace App\Actions\Sprints;

use App\Enums\ClosingStatus;
use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use App\Models\Sprint;
use App\Models\SprintCommitment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Complete the review of a Sprint (ITERA-10, FR-8, FR-9).
 *
 * The review saves the results of the week, so later changes to Tasks do
 * not change them: the completed story points, and a closing status for
 * each commitment. A done Task closes as Done. For each unfinished Task, the
 * user chooses: plan it again in the next Sprint, or send it back to the
 * backlog. The Task itself does not change. Its old commitment keeps the
 * title and the points of that week.
 */
class CompleteSprintReview
{
    /**
     * @param  array<int|string, string>  $decisions  The choice for each unfinished commitment id.
     * @param  array{went_well?: string|null, blockers?: string|null, improve_next?: string|null}  $notes
     * @param  int|null  $nextTarget  A new story point target for the next Sprint.
     * @return Sprint|null The next Sprint, when the review put Tasks or a target in it.
     */
    public function handle(Sprint $sprint, User $user, array $decisions, array $notes, ?int $nextTarget = null): ?Sprint
    {
        return DB::transaction(function () use ($sprint, $user, $decisions, $notes, $nextTarget): ?Sprint {
            $sprint->load('commitments.task');
            $next = null;
            $completed = 0;

            foreach ($sprint->commitments as $commitment) {
                $status = $this->closingStatus($commitment, $decisions);
                $commitment->closing_status = $status;
                $commitment->save();

                if ($status === ClosingStatus::Done) {
                    $completed += $commitment->committed_points ?? 0;
                }

                if ($status === ClosingStatus::CarriedOver && $commitment->task !== null && ! $this->isPlanned($sprint, $commitment)) {
                    $next ??= $this->nextSprint($sprint, $user);
                    $next->commitments()->firstOrCreate(['task_id' => $commitment->task->id], [
                        'task_title' => $commitment->task->title,
                        'committed_points' => $commitment->task->story_points?->value,
                    ]);
                }
            }

            $sprint->retrospective()->updateOrCreate([], $notes);

            if ($nextTarget !== null) {
                $next = $this->setNextTarget($sprint, $user, $nextTarget);
            }

            $sprint->forceFill([
                'status' => SprintStatus::Completed,
                'completed_points' => $completed,
                'review_completed_at' => now(),
            ])->save();

            return $next;
        });
    }

    /**
     * Change the notes of a reviewed Sprint, and the target of the next
     * Sprint. The results of the week stay as the review saved them.
     *
     * @param  array{went_well?: string|null, blockers?: string|null, improve_next?: string|null}  $notes
     */
    public function edit(Sprint $sprint, User $user, array $notes, ?int $nextTarget = null): ?Sprint
    {
        return DB::transaction(function () use ($sprint, $user, $notes, $nextTarget): ?Sprint {
            $sprint->retrospective()->updateOrCreate([], $notes);

            return $nextTarget === null ? null : $this->setNextTarget($sprint, $user, $nextTarget);
        });
    }

    /**
     * Give the next Sprint a new story point target.
     */
    private function setNextTarget(Sprint $sprint, User $user, int $points): Sprint
    {
        $next = $this->nextSprint($sprint, $user);
        $next->update(['target_points' => $points]);

        return $next;
    }

    /**
     * A done Task closes as Done, and a deleted one goes back to the backlog.
     * Otherwise the user's choice counts. Without a choice, the Task goes
     * back to the backlog.
     *
     * @param  array<int|string, string>  $decisions
     */
    private function closingStatus(SprintCommitment $commitment, array $decisions): ClosingStatus
    {
        if ($commitment->task === null) {
            return ClosingStatus::Dropped;
        }

        if ($commitment->task->status === TaskStatus::Done) {
            return ClosingStatus::Done;
        }

        return ClosingStatus::tryFrom($decisions[$commitment->id] ?? '') === ClosingStatus::CarriedOver
            ? ClosingStatus::CarriedOver
            : ClosingStatus::Dropped;
    }

    /**
     * Determine if the user already put the Task in a current or upcoming
     * Sprint. Then it stays there, because a Task is in one such Sprint only.
     */
    private function isPlanned(Sprint $sprint, SprintCommitment $commitment): bool
    {
        return SprintCommitment::query()
            ->where('task_id', $commitment->task_id)
            ->whereHas('sprint', fn ($sprints) => $sprints->currentOrUpcoming()->where('team_id', $sprint->team_id))
            ->exists();
    }

    /**
     * The first open Sprint of the team whose week has not ended. Without
     * one, a new Sprint for the user's current week.
     */
    private function nextSprint(Sprint $sprint, User $user): Sprint
    {
        $next = $sprint->team->sprints()
            ->currentOrUpcoming()
            ->reorder('week_start')
            ->first();

        return $next ?? $sprint->team->sprints()->create([
            'week_start' => $user->startOfWeek(),
        ]);
    }
}
