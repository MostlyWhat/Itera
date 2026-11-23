<script module lang="ts">
    import { formatShortDate } from '@/lib/dates';
    import { index, show } from '@/routes/sprints';
    import type { Team } from '@/types';

    export const layout = (props: {
        currentTeam?: Team | null;
        sprint: { id: number; weekStart: string };
    }) => ({
        breadcrumbs: [
            {
                title: 'Sprints',
                href: index(props.currentTeam?.slug ?? ''),
            },
            {
                title: `Week of ${formatShortDate(props.sprint.weekStart)}`,
                href: show({
                    current_team: props.currentTeam?.slug ?? '',
                    sprint: props.sprint.id,
                }),
            },
        ],
    });
</script>

<script lang="ts">
    import { Form, Link, page, router } from '@inertiajs/svelte';
    import Add from 'carbon-icons-svelte/lib/Add.svelte';
    import CheckmarkFilled from 'carbon-icons-svelte/lib/CheckmarkFilled.svelte';
    import Close from 'carbon-icons-svelte/lib/Close.svelte';
    import Edit from 'carbon-icons-svelte/lib/Edit.svelte';
    import Search from 'carbon-icons-svelte/lib/Search.svelte';
    import Undo from 'carbon-icons-svelte/lib/Undo.svelte';
    import WarningAltFilled from 'carbon-icons-svelte/lib/WarningAltFilled.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import FormDialog from '@/components/FormDialog.svelte';
    import InputError from '@/components/InputError.svelte';
    import PointsMeter from '@/components/sprints/PointsMeter.svelte';
    import { Button } from '@/components/ui/button';
    import TaskDetailsDialog from '@/components/tasks/TaskDetailsDialog.svelte';
    import type { TaskDetails } from '@/components/tasks/TaskDetailsDialog.svelte';
    import { Input } from '@/components/ui/input';
    import {
        Select,
        SelectContent,
        SelectGroup,
        SelectGroupHeading,
        SelectItem,
        SelectTrigger,
    } from '@/components/ui/select';
    import { formatWeekRange, weekName } from '@/lib/dates';
    import { cn } from '@/lib/utils';
    import { priority } from '@/routes';
    import { update } from '@/routes/sprints';
    import { store as storeSprintTask } from '@/routes/sprints/tasks';
    import { update as updateStatus } from '@/routes/tasks/status';

    type Candidate = {
        id: number;
        title: string;
        points: number | null;
        dueDate: string | null;
        isOverdue: boolean;
        projectName: string;
        areaName: string;
        takenBy: string | null;
        details: TaskDetails;
    };

    type Commitment = {
        id: number;
        taskId: number | null;
        title: string;
        points: number | null;
        projectName: string | null;
        status: string | null;
        details: TaskDetails | null;
    };

    let {
        sprint,
        commitments,
        candidates,
        projects,
        storyPointOptions,
    }: {
        sprint: {
            id: number;
            weekStart: string;
            weekEnd: string;
            goal: string | null;
            targetPoints: number | null;
            committedPoints: number;
            taskCount: number;
            timing: 'current' | 'upcoming' | 'past';
            editable: boolean;
        };
        commitments: Commitment[];
        candidates: Candidate[];
        projects: { id: number; name: string; areaName: string }[];
        storyPointOptions: number[];
    } = $props();

    const team = $derived((page.props.currentTeam as Team).slug);
    const errors = $derived(
        (page.props.errors ?? {}) as Record<string, string | undefined>,
    );

    let busy = $state(false);
    // The goal and the new task are entered in dialogs, so the two lists
    // below keep their place.
    let editingGoal = $state(false);
    let addingTask = $state(false);
    let filter = $state('');

    // A click on a task opens its details and subtasks. The details come
    // from the props by id, so they update after each change.
    let detailId = $state<number | null>(null);
    let detailOpen = $state(false);
    const detail = $derived(
        [...commitments.map((commitment) => commitment.details), ...candidates.map((task) => task.details)].find(
            (details) => details !== null && details.taskId === detailId,
        ) ?? null,
    );
    const openDetail = (taskId: number | null) => {
        if (taskId === null) {
            return;
        }

        detailId = taskId;
        detailOpen = true;
    };
    // A click on the row opens the details. Its buttons do their own work.
    const rowClick = (event: MouseEvent, taskId: number | null) => {
        if (!(event.target as HTMLElement).closest('button, a')) {
            openDetail(taskId);
        }
    };

    const committedIds = $derived(
        commitments
            .map((commitment) => commitment.taskId)
            .filter((id): id is number => id !== null),
    );

    // The backlog: open tasks that are not in this sprint yet.
    const backlog = $derived(
        candidates.filter((task) => !committedIds.includes(task.id)),
    );
    const filtered = $derived.by(() => {
        const query = filter.trim().toLowerCase();

        return query === ''
            ? backlog
            : backlog.filter((task) =>
                  `${task.title} ${task.projectName} ${task.areaName}`
                      .toLowerCase()
                      .includes(query),
              );
    });
    // The new-task form: the project stays for the next task, the points reset.
    // svelte-ignore state_referenced_locally
    let newProject = $state(projects[0] ? String(projects[0].id) : '');
    let newPoints = $state('');
    const newProjectName = $derived(
        projects.find((project) => String(project.id) === newProject)?.name ?? 'Project',
    );

    const projectGroups = $derived.by(() => {
        const byArea = new Map<string, typeof projects>();

        for (const project of projects) {
            byArea.set(project.areaName, [...(byArea.get(project.areaName) ?? []), project]);
        }

        return [...byArea.entries()];
    });

    // Add or remove a task. The plan saves at once.
    const saveTasks = (taskIds: number[]) => {
        router.patch(
            update({ current_team: team, sprint: sprint.id }).url,
            { task_ids: taskIds },
            {
                preserveScroll: true,
                onStart: () => (busy = true),
                onFinish: () => (busy = false),
            },
        );
    };

    const addExisting = (id: number) => saveTasks([...committedIds, id]);
    const remove = (id: number) =>
        saveTasks(committedIds.filter((value) => value !== id));

    const timingLabel = $derived(
        { current: 'This week', upcoming: 'Upcoming', past: 'Ended' }[sprint.timing],
    );
    const statusLabels: Record<string, string> = {
        todo: 'To Do',
        in_progress: 'In Progress',
        done: 'Done',
    };

    // A done task is locked in the sprint. The only action brings it back.
    const reopen = (taskId: number) =>
        router.patch(
            updateStatus({ current_team: team, task: taskId }).url,
            { status: 'todo' },
            { preserveScroll: true },
        );

    const pointsLabel = (points: number | null) =>
        points === null ? '– pts' : `${points} pts`;
</script>

<AppHead title={`Sprint ${formatWeekRange(sprint.weekStart, sprint.weekEnd)}`} />

<div class="flex flex-col gap-6 px-4 py-8 sm:py-10">
    <header class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
        <div class="min-w-0 space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="type-title">{weekName(sprint.weekStart)}</h1>
                {#if sprint.timing === 'past'}
                    <span class="rounded-sm bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground"
                        >{timingLabel}</span
                    >
                {/if}
            </div>
            <p class="flex flex-wrap items-center gap-x-2 text-sm">
                <span class={cn('wrap-break-word', !sprint.goal && 'text-muted-foreground')}
                    >{sprint.goal ?? 'No goal yet'}</span
                >
                {#if sprint.editable}
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-7 gap-1 px-1.5 text-muted-foreground hover:text-foreground"
                        onclick={() => (editingGoal = true)}
                    >
                        <Edit class="size-3.5" />
                        {sprint.goal ? 'Edit goal' : 'Add a goal'}
                    </Button>
                {/if}
            </p>
            <p class="text-sm text-muted-foreground tabular-nums">
                {formatWeekRange(sprint.weekStart, sprint.weekEnd)} · {sprint.taskCount}
                {sprint.taskCount === 1 ? 'task' : 'tasks'}
            </p>
        </div>
        <PointsMeter
            class="w-full sm:w-72"
            committed={sprint.committedPoints}
            target={sprint.targetPoints}
        />
    </header>

    {#if sprint.editable}

        <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
            <!-- The sprint: the work for this week. -->
            <section aria-labelledby="sprint-tasks" class="flex min-w-0 flex-col gap-3">
                <div class="flex min-h-8 items-center justify-between gap-3">
                    <h2 id="sprint-tasks" class="type-section">
                        In this sprint
                        <span class="font-normal text-muted-foreground tabular-nums"
                            >({commitments.length})</span
                        >
                    </h2>
                    <Button variant="outline" size="sm" class="gap-1.5" onclick={() => (addingTask = true)}>
                        <Add class="size-4" />
                        New task
                    </Button>
                </div>


                {#if commitments.length === 0}
                    <p
                        class="rounded-lg border border-border bg-card px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        No tasks yet. Add tasks from the backlog, or use New task.
                    </p>
                {:else}
                    <ul class="overflow-hidden rounded-lg border border-border bg-card">
                        {#each commitments as commitment (commitment.id)}
                            {@const done = commitment.status === 'done'}
                            <!-- The title button is the keyboard path to the details. -->
                            <!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_noninteractive_element_interactions -->
                            <li
                                class={cn(
                                    'flex min-h-12 items-center gap-3 border-t border-border py-2 pr-2 pl-4 transition-colors first:border-t-0',
                                    commitment.details && 'cursor-pointer hover:bg-muted/40',
                                )}
                                onclick={(event: MouseEvent) => rowClick(event, commitment.taskId)}
                            >
                                <span class="flex min-w-0 flex-1 flex-col">
                                    {#if commitment.details}
                                    <button
                                        type="button"
                                        onclick={() => openDetail(commitment.taskId)}
                                        class={cn(
                                            'w-fit rounded-sm text-start text-sm wrap-break-word focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                                            done && 'text-muted-foreground line-through decoration-muted-foreground/60',
                                        )}
                                    >
                                        {commitment.title}
                                        <span class="sr-only">, show details</span>
                                    </button>
                                    {:else}
                                        <span class="text-sm wrap-break-word">{commitment.title}</span>
                                    {/if}
                                    <span class="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                                        {#if commitment.projectName}
                                            <span class="wrap-break-word">{commitment.projectName}</span>
                                        {/if}
                                        {#if commitment.status}
                                            <span
                                                class={cn(
                                                    'flex items-center gap-1 font-medium',
                                                    commitment.status === 'in_progress' && 'text-accent-foreground',
                                                    done && 'text-primary',
                                                )}
                                            >
                                                {#if done}<CheckmarkFilled class="size-3.5" />{/if}
                                                {statusLabels[commitment.status]}
                                            </span>
                                        {/if}
                                    </span>
                                </span>
                                <span class="shrink-0 text-xs text-muted-foreground tabular-nums"
                                    >{pointsLabel(commitment.points)}</span
                                >
                                {#if commitment.taskId !== null && done}
                                    {@const taskId = commitment.taskId}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 shrink-0 gap-1.5 text-muted-foreground hover:text-foreground"
                                        onclick={() => reopen(taskId)}
                                    >
                                        <Undo class="size-4" />
                                        Move to To Do
                                    </Button>
                                {:else if commitment.taskId !== null}
                                    {@const taskId = commitment.taskId}
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 shrink-0 text-muted-foreground hover:text-foreground"
                                        aria-label={`Remove ${commitment.title} from the sprint`}
                                        disabled={busy}
                                        onclick={() => remove(taskId)}
                                    >
                                        <Close class="size-4" />
                                    </Button>
                                {/if}
                            </li>
                        {/each}
                    </ul>
                {/if}
                <InputError message={errors.task_ids} />
            </section>

            <!-- The backlog: open tasks to pull into the sprint. -->
            <section aria-labelledby="backlog-title" class="flex min-w-0 flex-col gap-3">
                <h2 id="backlog-title" class="flex min-h-8 items-center gap-1 type-section">
                    Backlog
                    <span class="font-normal text-muted-foreground tabular-nums"
                        >({backlog.length})</span
                    >
                </h2>
                <p class="-mt-2 text-xs text-muted-foreground">
                    In the order of your
                    <Link href={priority(team)} class="font-medium text-foreground underline-offset-4 hover:underline">priority stack</Link>.
                </p>

                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <label for="backlog-filter" class="sr-only">Find a task</label>
                    <Input
                        id="backlog-filter"
                        bind:value={filter}
                        placeholder="Find a task or project"
                        autocomplete="off"
                        class="pl-9"
                    />
                </div>

                {#if backlog.length === 0}
                    <p
                        class="rounded-lg border border-border bg-card px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        The backlog is empty. All open tasks are in a sprint.
                    </p>
                {:else if filtered.length === 0}
                    <p class="px-1 text-sm text-muted-foreground">
                        No task matches "{filter}".
                    </p>
                {:else}
                    <ul class="overflow-hidden rounded-lg border border-border bg-card">
                        {#each filtered as task (task.id)}
                            {@const taken = task.takenBy !== null}
                            <!-- The title button is the keyboard path to the details. -->
                            <!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_noninteractive_element_interactions -->
                            <li
                                class={cn(
                                    'flex min-h-12 cursor-pointer items-center gap-3 border-t border-border py-2 pr-2 pl-4 transition-colors first:border-t-0 hover:bg-muted/40',
                                    taken && 'opacity-60',
                                )}
                                onclick={(event: MouseEvent) => rowClick(event, task.id)}
                            >
                                <span class="flex min-w-0 flex-1 flex-col">
                                    <button
                                        type="button"
                                        onclick={() => openDetail(task.id)}
                                        class={cn(
                                            'w-fit rounded-sm text-start text-sm wrap-break-word focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                                            ''
                                        )}
                                    >
                                        {task.title}
                                        <span class="sr-only">, show details</span>
                                    </button>
                                    <span
                                        class="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground tabular-nums"
                                    >
                                        <span class="wrap-break-word">{task.projectName}</span>
                                        {#if taken && task.takenBy}
                                            <span><span aria-hidden="true">·</span> In the sprint of {formatShortDate(task.takenBy)}</span>
                                        {:else if task.dueDate}
                                            <span
                                                class={cn(
                                                    'flex items-center gap-1',
                                                    task.isOverdue && 'font-medium text-destructive',
                                                )}
                                            >
                                                <span aria-hidden="true">·</span>
                                                {#if task.isOverdue}
                                                    <WarningAltFilled class="size-3.5" />
                                                    Overdue,
                                                {:else}
                                                    Due
                                                {/if}
                                                {formatShortDate(task.dueDate)}
                                            </span>
                                        {/if}
                                    </span>
                                </span>
                                <span class="shrink-0 text-xs text-muted-foreground tabular-nums"
                                    >{pointsLabel(task.points)}</span
                                >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-8 shrink-0"
                                    aria-label={`Add ${task.title} to the sprint`}
                                    disabled={busy || taken}
                                    onclick={() => addExisting(task.id)}
                                >
                                    <Add class="size-4" />
                                    Add
                                </Button>
                            </li>
                        {/each}
                    </ul>
                {/if}
            </section>
        </div>
    {:else}
        <section aria-labelledby="plan-title" class="flex flex-col gap-3">
            <div class="space-y-1">
                <h2 id="plan-title" class="type-section">Plan</h2>
                <p class="text-sm text-muted-foreground">
                    {sprint.goal ?? 'No goal'}
                </p>
            </div>
            <ul class="overflow-hidden rounded-lg border border-border bg-card">
                {#each commitments as commitment (commitment.id)}
                    <!-- The title button is the keyboard path to the details. -->
                    <!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_noninteractive_element_interactions -->
                    <li
                        class={cn(
                            'flex min-h-11 items-center justify-between gap-3 border-t border-border px-4 py-2 text-sm transition-colors first:border-t-0',
                            commitment.details && 'cursor-pointer hover:bg-muted/40',
                        )}
                        onclick={(event: MouseEvent) => rowClick(event, commitment.taskId)}
                    >
                        {#if commitment.details}
                            <button
                                type="button"
                                onclick={() => openDetail(commitment.taskId)}
                                class="min-w-0 rounded-sm text-start wrap-break-word focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                            >
                                {commitment.title}
                                <span class="sr-only">, show details</span>
                            </button>
                        {:else}
                            <span class="min-w-0 wrap-break-word">{commitment.title}</span>
                        {/if}
                        <span class="shrink-0 text-xs text-muted-foreground tabular-nums"
                            >{pointsLabel(commitment.points)}</span
                        >
                    </li>
                {:else}
                    <li class="px-4 py-6 text-sm text-muted-foreground">
                        This sprint had no tasks.
                    </li>
                {/each}
            </ul>
        </section>
    {/if}
</div>

{#if sprint.editable}
    <FormDialog
        bind:open={editingGoal}
        title={sprint.goal ? 'Edit sprint goal' : 'Add a sprint goal'}
        description="Say what this week needs to achieve, and how many points fit."
    >
        <Form
            {...update.form({ current_team: team, sprint: sprint.id })}
            options={{ preserveScroll: true }}
            onSuccess={() => (editingGoal = false)}
            class="flex flex-col gap-5"
        >
            {#snippet children({ processing })}
                <div class="flex flex-col gap-2">
                    <label for="sprint-goal" class="text-sm font-medium">Sprint goal</label>
                    <Input
                        id="sprint-goal"
                        name="goal"
                        value={sprint.goal ?? ''}
                        maxlength={200}
                        placeholder="For example: Hand in the lab report"
                        autocomplete="off"
                    />
                    <InputError message={errors.goal} />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="sprint-target" class="text-sm font-medium">Point target</label>
                    <Input
                        id="sprint-target"
                        name="target_points"
                        type="number"
                        min={1}
                        max={999}
                        inputmode="numeric"
                        value={sprint.targetPoints === null ? '' : String(sprint.targetPoints)}
                        placeholder="For example 13"
                        class="w-32"
                    />
                    <InputError message={errors.target_points} />
                </div>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button type="button" variant="ghost" onclick={() => (editingGoal = false)}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={processing}>Save goal</Button>
                </div>
            {/snippet}
        </Form>
    </FormDialog>

    <FormDialog
        bind:open={addingTask}
        title="New task in this sprint"
        description="The task goes into its project and into this week."
    >
        {#if projects.length === 0}
            <p class="text-sm text-muted-foreground">
                Create a project first, on the Projects page.
            </p>
        {:else}
            <Form
                {...storeSprintTask.form({ current_team: team, sprint: sprint.id })}
                options={{ preserveScroll: true }}
                resetOnSuccess
                onSuccess={() => {
                    newPoints = '';
                    addingTask = false;
                }}
                class="flex flex-col gap-5"
            >
                {#snippet children({ errors: formErrors, processing })}
                    <div class="flex flex-col gap-2">
                        <label for="new-task-title" class="text-sm font-medium">Title</label>
                        <Input
                            id="new-task-title"
                            name="title"
                            maxlength={200}
                            placeholder="For example: Write the method section"
                            autocomplete="off"
                            required
                            aria-invalid={formErrors.title ? 'true' : undefined}
                        />
                        <InputError message={formErrors.title} />
                    </div>
                    <div class="grid gap-5 sm:grid-cols-[minmax(0,1fr)_8rem]">
                        <div class="flex min-w-0 flex-col gap-2">
                            <label for="new-task-project" class="text-sm font-medium">Project</label>
                            <Select type="single" name="project_id" bind:value={newProject}>
                                <SelectTrigger id="new-task-project" class="w-full">
                                    <span class="truncate">{newProjectName}</span>
                                </SelectTrigger>
                                <SelectContent>
                                    {#each projectGroups as [areaName, areaProjects] (areaName)}
                                        <SelectGroup>
                                            <SelectGroupHeading>{areaName}</SelectGroupHeading>
                                            {#each areaProjects as project (project.id)}
                                                <SelectItem value={String(project.id)} label={project.name} />
                                            {/each}
                                        </SelectGroup>
                                    {/each}
                                </SelectContent>
                            </Select>
                            <InputError message={formErrors.project_id} />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="new-task-points" class="text-sm font-medium">Story points</label>
                            <Select type="single" name="story_points" bind:value={newPoints}>
                                <SelectTrigger
                                    id="new-task-points"
                                    class={cn('w-full tabular-nums', newPoints === '' && 'text-muted-foreground')}
                                >
                                    {newPoints === '' ? 'None' : `${newPoints} pts`}
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="" label="No points" />
                                    {#each storyPointOptions as points (points)}
                                        <SelectItem value={String(points)} label={`${points} pts`} />
                                    {/each}
                                </SelectContent>
                            </Select>
                            <InputError message={formErrors.story_points} />
                        </div>
                    </div>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button type="button" variant="ghost" onclick={() => (addingTask = false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>Add task</Button>
                    </div>
                {/snippet}
            </Form>
        {/if}
    </FormDialog>
{/if}

<TaskDetailsDialog bind:open={detailOpen} {team} task={detail} />
