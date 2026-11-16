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
    import { Form, page, router } from '@inertiajs/svelte';
    import Add from 'carbon-icons-svelte/lib/Add.svelte';
    import Close from 'carbon-icons-svelte/lib/Close.svelte';
    import Search from 'carbon-icons-svelte/lib/Search.svelte';
    import WarningAltFilled from 'carbon-icons-svelte/lib/WarningAltFilled.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PointsMeter from '@/components/sprints/PointsMeter.svelte';
    import WeekStrip from '@/components/sprints/WeekStrip.svelte';
    import { Button } from '@/components/ui/button';
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
    import { update } from '@/routes/sprints';
    import { store as storeSprintTask } from '@/routes/sprints/tasks';

    type Candidate = {
        id: number;
        title: string;
        points: number | null;
        dueDate: string | null;
        isOverdue: boolean;
        projectName: string;
        areaName: string;
        takenBy: string | null;
    };

    type Commitment = {
        id: number;
        taskId: number | null;
        title: string;
        points: number | null;
        projectName: string | null;
        status: string | null;
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
    let filter = $state('');

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
    const backlogGroups = $derived.by(() => {
        const byProject = new Map<string, Candidate[]>();

        for (const task of filtered) {
            const key = `${task.areaName} · ${task.projectName}`;
            byProject.set(key, [...(byProject.get(key) ?? []), task]);
        }

        return [...byProject.entries()].map(([name, tasks]) => ({ name, tasks }));
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
    const pointsLabel = (points: number | null) =>
        points === null ? '– pts' : `${points} pts`;
</script>

<AppHead title={`Sprint ${formatWeekRange(sprint.weekStart, sprint.weekEnd)}`} />

<div class="flex flex-col gap-6 px-4 py-8 sm:py-10">
    <header class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
        <div class="space-y-1.5">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="type-title">{weekName(sprint.weekStart)}</h1>
                <span
                    class={cn(
                        'rounded-sm px-1.5 py-0.5 text-[11px] font-medium',
                        sprint.timing === 'past'
                            ? 'bg-muted text-muted-foreground'
                            : 'bg-accent text-accent-foreground',
                    )}>{timingLabel}</span
                >
            </div>
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

    <WeekStrip weekStart={sprint.weekStart} />

    {#if sprint.editable}
        <Form
            {...update.form({ current_team: team, sprint: sprint.id })}
            options={{ preserveScroll: true }}
            class="flex flex-col gap-1.5 rounded-lg border border-border bg-card p-4"
        >
            {#snippet children({ processing, isDirty })}
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex min-w-0 flex-1 basis-64 flex-col gap-1.5">
                        <label
                            for="sprint-goal"
                            class="text-xs font-medium text-muted-foreground"
                            >Sprint goal</label
                        >
                        <Input
                            id="sprint-goal"
                            name="goal"
                            value={sprint.goal ?? ''}
                            maxlength={200}
                            placeholder="What does this week need to achieve?"
                            autocomplete="off"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            for="sprint-target"
                            class="text-xs font-medium text-muted-foreground"
                            >Point target</label
                        >
                        <Input
                            id="sprint-target"
                            name="target_points"
                            type="number"
                            min={1}
                            max={999}
                            inputmode="numeric"
                            value={sprint.targetPoints === null ? '' : String(sprint.targetPoints)}
                            class="w-28"
                        />
                    </div>
                    <Button
                        type="submit"
                        variant={isDirty ? 'default' : 'outline'}
                        disabled={processing || !isDirty}
                    >
                        Save goal
                    </Button>
                </div>
                <InputError message={errors.goal ?? errors.target_points} />
            {/snippet}
        </Form>

        <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
            <!-- The sprint: the work for this week. -->
            <section aria-labelledby="sprint-tasks" class="flex min-w-0 flex-col gap-3">
                <h2 id="sprint-tasks" class="type-section">
                    In this sprint
                    <span class="font-normal text-muted-foreground tabular-nums"
                        >({commitments.length})</span
                    >
                </h2>

                <Form
                    {...storeSprintTask.form({ current_team: team, sprint: sprint.id })}
                    options={{ preserveScroll: true }}
                    resetOnSuccess={['title']}
                    onSuccess={() => (newPoints = '')}
                    class="rounded-lg border border-dashed border-border p-3"
                >
                    {#snippet children({ errors: formErrors, processing })}
                        <p class="mb-2 text-xs font-medium text-muted-foreground">
                            New task in this sprint
                        </p>
                        {#if projects.length === 0}
                            <p class="text-sm text-muted-foreground">
                                Create a project first, on the Areas page.
                            </p>
                        {:else}
                            <div class="flex flex-col gap-2">
                                <label for="new-task-title" class="sr-only">Task title</label>
                                <Input
                                    id="new-task-title"
                                    name="title"
                                    maxlength={200}
                                    placeholder="What needs to be done?"
                                    autocomplete="off"
                                    required
                                />
                                <div class="flex flex-wrap items-center gap-2">
                                    <label for="new-task-project" class="sr-only">Project</label>
                                    <Select type="single" name="project_id" bind:value={newProject}>
                                        <SelectTrigger
                                            id="new-task-project"
                                            class="w-auto min-w-0 flex-1 basis-40"
                                        >
                                            <span class="truncate">{newProjectName}</span>
                                        </SelectTrigger>
                                        <SelectContent>
                                            {#each projectGroups as [areaName, areaProjects] (areaName)}
                                                <SelectGroup>
                                                    <SelectGroupHeading>{areaName}</SelectGroupHeading>
                                                    {#each areaProjects as project (project.id)}
                                                        <SelectItem
                                                            value={String(project.id)}
                                                            label={project.name}
                                                        />
                                                    {/each}
                                                </SelectGroup>
                                            {/each}
                                        </SelectContent>
                                    </Select>
                                    <label for="new-task-points" class="sr-only">Story points</label>
                                    <Select type="single" name="story_points" bind:value={newPoints}>
                                        <SelectTrigger
                                            id="new-task-points"
                                            class={cn(
                                                'w-28 tabular-nums',
                                                newPoints === '' && 'text-muted-foreground',
                                            )}
                                        >
                                            {newPoints === '' ? '– pts' : `${newPoints} pts`}
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="" label="No points" />
                                            {#each storyPointOptions as points (points)}
                                                <SelectItem
                                                    value={String(points)}
                                                    label={`${points} pts`}
                                                />
                                            {/each}
                                        </SelectContent>
                                    </Select>
                                    <Button type="submit" disabled={processing}>
                                        <Add class="size-4" />
                                        Add
                                    </Button>
                                </div>
                                <InputError
                                    message={formErrors.title ??
                                        formErrors.project_id ??
                                        formErrors.story_points}
                                />
                            </div>
                        {/if}
                    {/snippet}
                </Form>

                {#if commitments.length === 0}
                    <p
                        class="rounded-lg border border-border bg-card px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        No tasks yet. Add a new task above, or add one from the
                        backlog.
                    </p>
                {:else}
                    <ul class="overflow-hidden rounded-lg border border-border bg-card">
                        {#each commitments as commitment (commitment.id)}
                            <li
                                class="flex min-h-12 items-center gap-3 border-t border-border py-2 pr-2 pl-4 first:border-t-0"
                            >
                                <span class="flex min-w-0 flex-1 flex-col">
                                    <span class="truncate text-sm">{commitment.title}</span>
                                    {#if commitment.projectName}
                                        <span class="truncate text-xs text-muted-foreground"
                                            >{commitment.projectName}</span
                                        >
                                    {/if}
                                </span>
                                <span class="shrink-0 text-xs text-muted-foreground tabular-nums"
                                    >{pointsLabel(commitment.points)}</span
                                >
                                {#if commitment.taskId !== null}
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
                <h2 id="backlog-title" class="type-section">
                    Backlog
                    <span class="font-normal text-muted-foreground tabular-nums"
                        >({backlog.length})</span
                    >
                </h2>

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
                {:else if backlogGroups.length === 0}
                    <p class="px-1 text-sm text-muted-foreground">
                        No task matches "{filter}".
                    </p>
                {/if}

                {#each backlogGroups as group (group.name)}
                    <div class="overflow-hidden rounded-lg border border-border bg-card">
                        <h3
                            class="border-b border-border bg-muted/30 px-4 py-2 text-xs font-medium text-muted-foreground"
                        >
                            {group.name}
                        </h3>
                        <ul>
                            {#each group.tasks as task (task.id)}
                                {@const taken = task.takenBy !== null}
                                <li
                                    class={cn(
                                        'flex min-h-12 items-center gap-3 border-t border-border py-2 pr-2 pl-4 first:border-t-0',
                                        taken && 'opacity-60',
                                    )}
                                >
                                    <span class="flex min-w-0 flex-1 flex-col">
                                        <span class="truncate text-sm">{task.title}</span>
                                        {#if taken && task.takenBy}
                                            <span class="text-xs text-muted-foreground"
                                                >In the sprint of {formatShortDate(task.takenBy)}</span
                                            >
                                        {:else if task.dueDate}
                                            <span
                                                class={cn(
                                                    'flex items-center gap-1 text-xs tabular-nums',
                                                    task.isOverdue
                                                        ? 'font-medium text-destructive'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
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
                    </div>
                {/each}
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
                    <li
                        class="flex min-h-11 items-center justify-between gap-3 border-t border-border px-4 py-2 text-sm first:border-t-0"
                    >
                        <span class="min-w-0 truncate">{commitment.title}</span>
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
