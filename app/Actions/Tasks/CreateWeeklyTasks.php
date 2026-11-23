<?php

namespace App\Actions\Tasks;

use App\Models\RecurrenceTemplate;
use App\Models\Task;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Make the Tasks of weekly tasks (ITERA-09, FR-10). For each template,
 * Itera makes one Task for each date in the next WINDOW_DAYS days that
 * falls on its weekday, so this week and next week are ready to plan.
 *
 * The action is safe to run again and again (NFR-4). It skips dates that
 * already have a Task, and the unique index on (template, date) stops a
 * second Task when two requests run at the same time. Dates before the
 * template was made, weeks that the user missed, and Tasks that the user
 * deleted, get no Task.
 */
class CreateWeeklyTasks
{
    /**
     * The number of days ahead, from today, that get Tasks.
     */
    public const WINDOW_DAYS = 14;

    /**
     * Make the missing Tasks for all weekly tasks of a team's active Projects.
     */
    public function forTeam(Team $team, ?CarbonImmutable $today = null): int
    {
        $templates = RecurrenceTemplate::query()
            ->whereHas('project', fn ($projects) => $projects->active()
                ->whereHas('area', fn ($areas) => $areas->active()->where('team_id', $team->id)))
            ->get();

        return $templates->sum(fn (RecurrenceTemplate $template) => $this->forTemplate($template, $today));
    }

    /**
     * Make the missing Tasks of one weekly task. Returns the number made.
     */
    public function forTemplate(RecurrenceTemplate $template, ?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $start = $template->created_at !== null && $template->created_at->startOfDay()->gt($today)
            ? $template->created_at->startOfDay()
            : $today;

        // Only dates after the latest Task get a new one, so a Task that the
        // user deleted does not come back.
        $latest = $template->tasks()->max('recurrence_date');

        if ($latest !== null && CarbonImmutable::parse($latest)->gte($start)) {
            $start = CarbonImmutable::parse($latest)->addDay();
        }

        $made = 0;

        foreach ($this->dates($template->weekday, $start, $today->addDays(self::WINDOW_DAYS - 1)) as $date) {
            $exists = $template->tasks()->whereDate('recurrence_date', $date->toDateString())->exists();

            if ($exists) {
                continue;
            }

            try {
                $task = new Task([
                    'title' => $template->title,
                    'description' => $template->description,
                    'story_points' => $template->story_points,
                    'due_date' => $date,
                ]);
                $task->project_id = $template->project_id;
                $task->recurrence_template_id = $template->id;
                $task->recurrence_date = $date;
                $task->save();
                $made++;
            } catch (UniqueConstraintViolationException) {
                // Another request made this Task first.
            }
        }

        return $made;
    }

    /**
     * The dates from `$from` to `$to` that fall on the weekday.
     *
     * @return list<CarbonImmutable>
     */
    private function dates(int $weekday, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $date = $from->addDays(($weekday - $from->dayOfWeek + 7) % 7);
        $dates = [];

        while ($date->lte($to)) {
            $dates[] = $date;
            $date = $date->addWeek();
        }

        return $dates;
    }
}
