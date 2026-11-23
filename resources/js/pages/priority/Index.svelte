<script module lang="ts">
    import { priority } from '@/routes';
    import type { Team } from '@/types';

    export const layout = (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [{ title: 'Priority', href: priority(props.currentTeam?.slug ?? '') }],
    });
</script>

<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ArrowRight from 'carbon-icons-svelte/lib/ArrowRight.svelte';
    import WarningAltFilled from 'carbon-icons-svelte/lib/WarningAltFilled.svelte';
    import { flip } from 'svelte/animate';
    import { quintOut } from 'svelte/easing';
    import { prefersReducedMotion } from 'svelte/motion';
    import AppHead from '@/components/AppHead.svelte';
    import PriorityStack from '@/components/priority/PriorityStack.svelte';
    import { buttonVariants } from '@/components/ui/button';
    import { formatShortDate } from '@/lib/dates';
    import { cn } from '@/lib/utils';
    import { index as projectsIndex, show as showProject } from '@/routes/projects';
    import { index as sprintsIndex, show as showSprint } from '@/routes/sprints';

    // The priority stack (ITERA-07). The user ranks Areas and Projects on the
    // left, sees on the right which open Tasks that puts first, and goes on
    // to fill the next sprint. The task list keeps the stack's groups, so a
    // move in the stack shows its effect at once.
    type StackTask = {
        id: number;
        title: string;
        status: 'todo' | 'in_progress' | 'done';
        points: number | null;
        dueDate: string | null;
        isOverdue: boolean;
        projectId: number;
        projectName: string;
        areaName: string;
        sprintWeek: string | null;
    };

    let {
        areas,
        tasks,
        taskCount,
        nextSprint,
    }: {
        areas: { id: number; name: string; projects: { id: number; name: string }[] }[];
        tasks: StackTask[];
        taskCount: number;
        nextSprint: { id: number; weekStart: string; current: boolean } | null;
    } = $props();

    const team = $derived((page.props.currentTeam as Team).slug);

    // Consecutive Tasks of one Project form a group. The rank counts across
    // all groups.
    const groups = $derived.by(() => {
        const result: { projectId: number; projectName: string; areaName: string; tasks: { task: StackTask; rank: number }[] }[] = [];

        tasks.forEach((task, index) => {
            const last = result.at(-1);

            if (last && last.projectId === task.projectId) {
                last.tasks.push({ task, rank: index + 1 });
            } else {
                result.push({
                    projectId: task.projectId,
                    projectName: task.projectName,
                    areaName: task.areaName,
                    tasks: [{ task, rank: index + 1 }],
                });
            }
        });

        return result;
    });

    const plannedCount = $derived(tasks.filter((task) => task.sprintWeek !== null).length);

    // The next step: fill the sprint that comes next, or plan one.
    const next = $derived(
        nextSprint
            ? {
                  href: showSprint({ current_team: team, sprint: nextSprint.id }),
                  label: nextSprint.current
                      ? 'Add tasks to this week'
                      : `Add tasks to the week of ${formatShortDate(nextSprint.weekStart)}`,
              }
            : { href: sprintsIndex(team), label: 'Plan a sprint' },
    );

    // The groups settle with the same curve as the stack: no overshoot.
    const motion = () => ({ duration: prefersReducedMotion.current ? 0 : 220, easing: quintOut });
</script>

<AppHead title="Priority" />

<div class="flex flex-col gap-8 px-4 py-8 sm:py-10">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-w-prose space-y-1">
            <h1 class="type-title">Priority</h1>
            <p class="text-sm text-pretty text-muted-foreground">
                Rank what matters most. Your open tasks follow this order in the sprint backlog and on
                the board.
            </p>
        </div>
        {#if tasks.length > 0}
            <Link href={next.href} class={cn(buttonVariants(), 'gap-1.5')}>
                {next.label}
                <ArrowRight class="size-4" />
            </Link>
        {/if}
    </header>

    {#if areas.length === 0}
        <div class="flex flex-col items-start gap-3 rounded-lg border border-dashed border-border px-5 py-8">
            <p class="text-sm font-medium">No areas to rank yet</p>
            <p class="max-w-prose text-sm text-muted-foreground">
                Areas are the parts of your life, for example a class or a job. Create one, add its
                projects, and rank them here.
            </p>
            <Link href={projectsIndex(team)} class={buttonVariants({ variant: 'outline', size: 'sm' })}>
                Open Projects
            </Link>
        </div>
    {:else}
        <div class="grid items-start gap-10 lg:grid-cols-[18rem_minmax(0,1fr)] lg:gap-12">
            <section aria-labelledby="stack-title" class="flex flex-col gap-3 lg:sticky lg:top-6">
                <div class="space-y-1">
                    <h2 id="stack-title" class="type-section">Your stack</h2>
                    <p class="text-sm text-muted-foreground">Drag a row, or use its arrows. Changes save at once.</p>
                </div>
                <PriorityStack stack={areas} {team} />
            </section>

            <section aria-labelledby="tasks-title" class="flex min-w-0 flex-col gap-3">
                <div class="space-y-1">
                    <h2 id="tasks-title" class="type-section">What comes first</h2>
                    <p class="text-sm text-muted-foreground tabular-nums">
                        {#if taskCount === 0}
                            No open tasks.
                        {:else}
                            {taskCount} open {taskCount === 1 ? 'task' : 'tasks'}, by project, then by
                            deadline.{#if plannedCount > 0}{' '}
                                {plannedCount} already in a sprint.{/if}
                        {/if}
                    </p>
                </div>

                {#if tasks.length === 0}
                    <div class="flex flex-col items-start gap-3 rounded-lg border border-border bg-card px-5 py-8">
                        <p class="text-sm text-muted-foreground">
                            Add tasks to your projects, and they show here in the order of your stack.
                        </p>
                        <Link href={projectsIndex(team)} class={buttonVariants({ variant: 'outline', size: 'sm' })}>
                            Open Projects
                        </Link>
                    </div>
                {:else}
                    <ol class="flex flex-col gap-4">
                        {#each groups as group (group.projectId)}
                            <li animate:flip={motion()} class="flex flex-col gap-1.5">
                                <h3 class="flex flex-wrap items-baseline gap-x-1.5 px-1 text-xs text-muted-foreground">
                                    <span class="font-medium tracking-[0.06em] uppercase">{group.areaName}</span>
                                    <span aria-hidden="true">›</span>
                                    <Link
                                        href={showProject({ current_team: team, project: group.projectId })}
                                        class="font-medium text-foreground underline-offset-4 hover:underline"
                                    >
                                        {group.projectName}
                                    </Link>
                                </h3>
                                <ol class="overflow-hidden rounded-lg border border-border bg-card">
                                    {#each group.tasks as { task, rank } (task.id)}
                                        {@const planned = task.sprintWeek !== null}
                                        <li
                                            class="flex min-h-12 items-center gap-3 border-t border-border py-2 pr-3 pl-3 first:border-t-0"
                                        >
                                            <span
                                                class="w-6 shrink-0 text-end text-xs text-muted-foreground tabular-nums"
                                                aria-label={`Number ${rank}`}
                                            >
                                                {rank}
                                            </span>
                                            <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                                <span
                                                    class={cn(
                                                        'text-sm wrap-break-word',
                                                        planned && 'text-muted-foreground',
                                                    )}
                                                >
                                                    {task.title}
                                                </span>
                                                {#if task.dueDate || planned || task.status === 'in_progress'}
                                                    <span class="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground tabular-nums">
                                                        {#if task.status === 'in_progress'}
                                                            <span class="font-medium text-accent-foreground">In progress</span>
                                                        {/if}
                                                        {#if task.dueDate}
                                                            <span
                                                                class={cn(
                                                                    'flex items-center gap-1',
                                                                    task.isOverdue && 'font-medium text-destructive',
                                                                )}
                                                            >
                                                                {#if task.isOverdue}
                                                                    <WarningAltFilled class="size-3.5" />
                                                                    Overdue, {formatShortDate(task.dueDate)}
                                                                {:else}
                                                                    Due {formatShortDate(task.dueDate)}
                                                                {/if}
                                                            </span>
                                                        {/if}
                                                        {#if planned && task.sprintWeek}
                                                            <span>Planned for the week of {formatShortDate(task.sprintWeek)}</span>
                                                        {/if}
                                                    </span>
                                                {/if}
                                            </span>
                                            <span
                                                class={cn(
                                                    'flex h-6 min-w-12 shrink-0 items-center justify-center rounded-md border px-1.5 text-xs tabular-nums',
                                                    task.points === null
                                                        ? 'border-dashed border-border text-muted-foreground'
                                                        : 'border-border',
                                                    planned && 'text-muted-foreground',
                                                )}
                                            >
                                                {task.points === null ? '– pts' : `${task.points} pts`}
                                            </span>
                                        </li>
                                    {/each}
                                </ol>
                            </li>
                        {/each}
                    </ol>
                    {#if taskCount > tasks.length}
                        <p class="text-xs text-muted-foreground">
                            The list shows the first {tasks.length} of {taskCount} open tasks.
                        </p>
                    {/if}
                {/if}
            </section>
        </div>
    {/if}
</div>
