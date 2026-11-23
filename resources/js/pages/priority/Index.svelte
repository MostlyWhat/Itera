<script module lang="ts">
    import { priority } from '@/routes';
    import type { Team } from '@/types';

    export const layout = (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [{ title: 'Priority', href: priority(props.currentTeam?.slug ?? '') }],
    });
</script>

<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import ArrowRight from 'carbon-icons-svelte/lib/ArrowRight.svelte';
    import Calendar from 'carbon-icons-svelte/lib/Calendar.svelte';
    import ListNumbered from 'carbon-icons-svelte/lib/ListNumbered.svelte';
    import RadioButton from 'carbon-icons-svelte/lib/RadioButton.svelte';
    import RadioButtonChecked from 'carbon-icons-svelte/lib/RadioButtonChecked.svelte';
    import ScalesTipped from 'carbon-icons-svelte/lib/ScalesTipped.svelte';
    import Time from 'carbon-icons-svelte/lib/Time.svelte';
    import WarningAltFilled from 'carbon-icons-svelte/lib/WarningAltFilled.svelte';
    import type { Component } from 'svelte';
    import { flip } from 'svelte/animate';
    import { quintOut } from 'svelte/easing';
    import { prefersReducedMotion } from 'svelte/motion';
    import AppHead from '@/components/AppHead.svelte';
    import PriorityStack from '@/components/priority/PriorityStack.svelte';
    import { buttonVariants } from '@/components/ui/button';
    import { formatShortDate } from '@/lib/dates';
    import { cn } from '@/lib/utils';
    import { mode as saveMode } from '@/routes/priority';
    import { index as projectsIndex, show as showProject } from '@/routes/projects';
    import { index as sprintsIndex, show as showSprint } from '@/routes/sprints';

    // The Priority page answers one question: what do I do first? The left
    // column holds the two rules: how Itera picks (the sorting mode,
    // ITERA-08) and the priority stack (ITERA-07). The right column is the
    // result: the queue of open Tasks in that order, with a line where the
    // room in the next sprint ends. A change of either rule reorders the
    // queue at once.
    type QueueTask = {
        id: number;
        title: string;
        status: 'todo' | 'in_progress' | 'done';
        points: number | null;
        dueDate: string | null;
        isOverdue: boolean;
        isDueSoon: boolean;
        projectId: number;
        projectName: string;
        areaName: string;
        sprintWeek: string | null;
    };

    type NextSprint = {
        id: number;
        weekStart: string;
        current: boolean;
        targetPoints: number | null;
        committedPoints: number;
    };

    let {
        areas,
        tasks,
        nextSprint,
        sortingMode,
        sortingModes,
    }: {
        areas: { id: number; name: string; projects: { id: number; name: string }[] }[];
        tasks: QueueTask[];
        nextSprint: NextSprint | null;
        sortingMode: string;
        sortingModes: { value: string; label: string; description: string }[];
    } = $props();

    const team = $derived((page.props.currentTeam as Team).slug);

    // The chosen mode changes on the click. The server saves it and sends
    // the queue in the new order.
    let mode = $derived(sortingMode);
    const chooseMode = (value: string) => {
        mode = value;
        router.put(saveMode(team).url, { sorting_mode: value }, { preserveScroll: true, preserveState: true });
    };
    const modeIcons: Record<string, Component> = {
        stack: ListNumbered,
        deadline: Calendar,
        balanced: ScalesTipped,
    };
    const modeLabel = $derived(sortingModes.find((item) => item.value === mode)?.label ?? '');

    // The room in the next sprint, and the open Tasks that fill it in
    // queue order. Tasks already in a sprint do not count.
    const room = $derived(
        nextSprint?.targetPoints ? Math.max(0, nextSprint.targetPoints - nextSprint.committedPoints) : null,
    );
    const fit = $derived.by(() => {
        if (room === null) {
            return { ids: new Set<number>(), points: 0, lastIndex: -1 };
        }

        const ids = new Set<number>();
        let points = 0;
        let lastIndex = -1;

        for (const [index, task] of tasks.entries()) {
            if (task.sprintWeek !== null) {
                continue;
            }

            const size = task.points ?? 0;

            if (points + size > room) {
                break;
            }

            points += size;
            ids.add(task.id);
            lastIndex = index;
        }

        return { ids, points, lastIndex };
    });

    const weekLabel = $derived(
        nextSprint ? (nextSprint.current ? 'This week' : `Week of ${formatShortDate(nextSprint.weekStart)}`) : '',
    );

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

    // The rules column stays in view while the queue scrolls, but only when
    // it fits the screen.
    let rules = $state<HTMLElement>();
    let rulesHeight = $state(0);
    let screenHeight = $state(0);
    const rulesFit = $derived(rulesHeight > 0 && rulesHeight + 48 <= screenHeight);

    $effect(() => {
        if (!rules) {
            return;
        }

        const observer = new ResizeObserver(([entry]) => (rulesHeight = entry.contentRect.height));
        observer.observe(rules);

        return () => observer.disconnect();
    });

    // Rows settle with a fast start and no overshoot. Reduced motion moves
    // them at once.
    const motion = () => ({ duration: prefersReducedMotion.current ? 0 : 240, easing: quintOut });
</script>

<svelte:window bind:innerHeight={screenHeight} />

<AppHead title="Priority" />

<div class="flex flex-col gap-8 px-4 py-8 sm:py-10">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-w-prose space-y-1">
            <h1 class="type-title">Priority</h1>
            <p class="text-sm text-pretty text-muted-foreground">
                Set how Itera picks your tasks. The queue on this page, the sprint backlog, and the
                board all follow it.
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
        <div class="flex max-w-xl flex-col items-start gap-3 rounded-lg border border-dashed border-border px-5 py-8">
            <p class="text-sm font-medium">No areas to rank yet</p>
            <p class="text-sm text-muted-foreground">
                Areas are the parts of your life, for example a class or a job. Create one, add its
                projects, and come back to set what comes first.
            </p>
            <Link href={projectsIndex(team)} class={buttonVariants({ variant: 'outline', size: 'sm' })}>
                Open Projects
            </Link>
        </div>
    {:else}
        <div class="grid items-start gap-10 lg:grid-cols-[20rem_minmax(0,1fr)] lg:gap-14">
            <!-- The rules. -->
            <!-- Sticky only when the rules fit the screen. A taller column would hide its lower rows. -->
            <div
                bind:this={rules}
                class={cn('flex flex-col gap-10', rulesFit && 'lg:sticky lg:top-6')}
            >
                <section aria-labelledby="picks-title" class="flex flex-col gap-3">
                    <h2 id="picks-title" class="type-section">How Itera picks</h2>
                    <fieldset class="flex flex-col gap-2">
                        <legend class="sr-only">Sort tasks by</legend>
                        {#each sortingModes as option (option.value)}
                            {@const Icon = modeIcons[option.value] ?? ListNumbered}
                            {@const chosen = mode === option.value}
                            <label
                                class={cn(
                                    'flex cursor-pointer items-start gap-3 rounded-lg border bg-card px-3.5 py-3 transition-[border-color,background-color,scale] duration-150 select-none has-focus-visible:ring-3 has-focus-visible:ring-ring/50 motion-safe:active:scale-[0.98]',
                                    chosen ? 'border-primary bg-accent/40' : 'border-border hover:border-input hover:bg-muted/50',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="sorting_mode"
                                    value={option.value}
                                    checked={chosen}
                                    onchange={() => chooseMode(option.value)}
                                    class="sr-only"
                                />
                                <span
                                    class={cn(
                                        'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md transition-colors duration-150',
                                        chosen ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground',
                                    )}
                                    aria-hidden="true"
                                >
                                    <Icon class="size-4" />
                                </span>
                                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span class="text-sm font-medium">{option.label}</span>
                                    <span class="text-xs text-pretty text-muted-foreground">{option.description}</span>
                                </span>
                                <span class={cn('mt-0.5 shrink-0', chosen ? 'text-primary' : 'text-muted-foreground/60')} aria-hidden="true">
                                    {#if chosen}
                                        <RadioButtonChecked class="size-4" />
                                    {:else}
                                        <RadioButton class="size-4" />
                                    {/if}
                                </span>
                            </label>
                        {/each}
                    </fieldset>
                </section>

                <section aria-labelledby="stack-title" class="flex flex-col gap-3">
                    <div class="space-y-1">
                        <h2 id="stack-title" class="type-section">Your stack</h2>
                        <p class="text-sm text-muted-foreground">
                            Most important at the top. Drag a row, or use its arrows.
                        </p>
                    </div>
                    <PriorityStack stack={areas} {team} />
                </section>
            </div>

            <!-- The result. -->
            <section aria-labelledby="queue-title" class="flex min-w-0 flex-col gap-4">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <h2 id="queue-title" class="type-section">
                        Your queue
                        <span class="font-normal text-muted-foreground tabular-nums">({tasks.length})</span>
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Sorted by <span class="font-medium text-foreground">{modeLabel}</span>
                    </p>
                </div>

                {#if nextSprint && room !== null && nextSprint.targetPoints}
                    {@const target = nextSprint.targetPoints}
                    <div class="flex flex-col gap-2 rounded-lg border border-border bg-card px-4 py-3">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm tabular-nums">
                            <span class="font-medium">{weekLabel}</span>
                            <span class="text-muted-foreground">
                                {nextSprint.committedPoints} of {target} points planned.
                                {#if room > 0}
                                    The first tasks below fill {fit.points} of the {room} left.
                                {:else}
                                    The sprint is full.
                                {/if}
                            </span>
                        </div>
                        <div class="flex h-1.5 overflow-hidden rounded-full bg-muted" aria-hidden="true">
                            <span
                                class="h-full bg-primary motion-safe:transition-[width] motion-safe:duration-300"
                                style:width={`${Math.min(100, (nextSprint.committedPoints / target) * 100)}%`}
                            ></span>
                            <span
                                class="h-full bg-primary/35 motion-safe:transition-[width] motion-safe:duration-300"
                                style:width={`${Math.min(100, (fit.points / target) * 100)}%`}
                            ></span>
                        </div>
                    </div>
                {/if}

                {#if tasks.length === 0}
                    <div class="flex flex-col items-start gap-3 rounded-lg border border-border bg-card px-5 py-8">
                        <p class="text-sm text-muted-foreground">
                            No open tasks. Add tasks to your projects, and they line up here.
                        </p>
                        <Link href={projectsIndex(team)} class={buttonVariants({ variant: 'outline', size: 'sm' })}>
                            Open Projects
                        </Link>
                    </div>
                {:else}
                    <ol class="rounded-lg border border-border bg-card" aria-label="Open tasks, first one first">
                        {#each tasks as task, index (task.id)}
                            {@const planned = task.sprintWeek !== null}
                            {@const fits = fit.ids.has(task.id)}
                            {@const cut = fit.lastIndex >= 0 && index === fit.lastIndex + 1}
                            <li
                                animate:flip={motion()}
                                class={cn(
                                    'relative flex min-h-14 items-center gap-3 border-t border-border py-2.5 pr-3 pl-3 first:rounded-t-lg first:border-t-0 last:rounded-b-lg',
                                    cut && 'border-t-primary/60',
                                )}
                            >
                                {#if cut}
                                    <span class="absolute -top-2.5 right-3 rounded-sm bg-card px-1.5 text-[11px] leading-5 font-medium text-primary">
                                        Room ends here
                                    </span>
                                {/if}
                                <span
                                    class={cn(
                                        'flex size-6 shrink-0 items-center justify-center rounded-full text-xs tabular-nums',
                                        fits ? 'bg-accent font-medium text-accent-foreground' : 'text-muted-foreground',
                                    )}
                                >
                                    {index + 1}
                                </span>
                                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span class={cn('text-sm wrap-break-word', planned && 'text-muted-foreground')}>
                                        {task.title}
                                        {#if fits}<span class="sr-only">, fits this week</span>{/if}
                                    </span>
                                    <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-muted-foreground tabular-nums">
                                        <Link
                                            href={showProject({ current_team: team, project: task.projectId })}
                                            class="wrap-break-word underline-offset-4 hover:text-foreground hover:underline"
                                        >
                                            {task.projectName} · {task.areaName}
                                        </Link>
                                        {#if task.status === 'in_progress'}
                                            <span class="font-medium text-accent-foreground">In progress</span>
                                        {/if}
                                        {#if task.dueDate}
                                            <span
                                                class={cn(
                                                    'flex items-center gap-1',
                                                    task.isOverdue && 'font-medium text-destructive',
                                                    task.isDueSoon && 'font-medium text-foreground',
                                                )}
                                            >
                                                {#if task.isOverdue}
                                                    <WarningAltFilled class="size-3.5" />
                                                    Overdue, {formatShortDate(task.dueDate)}
                                                {:else if task.isDueSoon}
                                                    <Time class="size-3.5" />
                                                    Due soon, {formatShortDate(task.dueDate)}
                                                {:else}
                                                    Due {formatShortDate(task.dueDate)}
                                                {/if}
                                            </span>
                                        {/if}
                                        {#if planned && task.sprintWeek}
                                            <span>Planned for the week of {formatShortDate(task.sprintWeek)}</span>
                                        {/if}
                                    </span>
                                </span>
                                <span
                                    class={cn(
                                        'flex h-6 min-w-12 shrink-0 items-center justify-center rounded-md border px-1.5 text-xs tabular-nums',
                                        task.points === null ? 'border-dashed border-border text-muted-foreground' : 'border-border',
                                        planned && 'text-muted-foreground',
                                    )}
                                >
                                    {task.points === null ? '– pts' : `${task.points} pts`}
                                </span>
                            </li>
                        {/each}
                    </ol>
                {/if}
            </section>
        </div>
    {/if}
</div>
