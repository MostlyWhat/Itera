<script module lang="ts">
    import type { SubtaskItem } from '@/components/tasks/SubtaskList.svelte';
    import type { Progress } from '@/components/tasks/SubtaskProgress.svelte';

    export type TaskDetails = {
        taskId: number;
        title: string;
        description: string | null;
        statusLabel: string;
        points: number | null;
        dueDate: string | null;
        isOverdue: boolean;
        repeatsOn: string | null;
        projectId: number;
        projectName: string;
        areaName: string;
        progress: Progress | null;
        subtasks: SubtaskItem[];
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Repeat from 'carbon-icons-svelte/lib/Repeat.svelte';
    import FormDialog from '@/components/FormDialog.svelte';
    import SubtaskList from '@/components/tasks/SubtaskList.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button, buttonVariants } from '@/components/ui/button';
    import { formatShortDate } from '@/lib/dates';
    import { cn } from '@/lib/utils';
    import { show as showProject } from '@/routes/projects';

    // The details of one Task, with its subtask checklist and a link to its
    // Project. The parent keeps the Task in its props, so the dialog shows
    // the new state after each subtask change.
    let {
        open = $bindable(false),
        team,
        task,
    }: {
        open?: boolean;
        team: string;
        task: TaskDetails | null;
    } = $props();
</script>

<FormDialog bind:open title={task?.title ?? 'Task'} size="lg">
    {#if task}
        {#if task.repeatsOn}
            <Badge variant="secondary" class="-mt-2">
                <Repeat aria-hidden="true" />
                Weekly · {task.repeatsOn}
            </Badge>
        {/if}
        <dl class="grid grid-cols-[7rem_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
            <dt class="text-muted-foreground">Status</dt>
            <dd>{task.statusLabel}</dd>
            <dt class="text-muted-foreground">Project</dt>
            <dd class="wrap-break-word">{task.projectName} · {task.areaName}</dd>
            <dt class="text-muted-foreground">Story points</dt>
            <dd class="tabular-nums">{task.points ?? 'None'}</dd>
            <dt class="text-muted-foreground">Deadline</dt>
            <dd class={cn('tabular-nums', task.isOverdue && 'font-medium text-destructive')}>
                {#if task.dueDate}
                    {formatShortDate(task.dueDate)}{task.isOverdue ? ', overdue' : ''}
                {:else}
                    None
                {/if}
            </dd>
        </dl>
        {#if task.description}
            <div class="flex flex-col gap-2">
                <h3 class="text-sm font-medium">Description</h3>
                <p class="max-h-60 overflow-y-auto text-sm whitespace-pre-line wrap-break-word text-muted-foreground">{task.description}</p>
            </div>
        {/if}
        <SubtaskList {team} taskId={task.taskId} subtasks={task.subtasks} progress={task.progress} />
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button variant="ghost" onclick={() => (open = false)}>Close</Button>
            <Link
                href={showProject({ current_team: team, project: task.projectId })}
                class={buttonVariants({ variant: 'outline' })}
            >
                Open the project
            </Link>
        </div>
    {/if}
</FormDialog>
