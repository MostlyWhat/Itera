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
    import { page, router } from '@inertiajs/svelte';
    import Checkmark from 'carbon-icons-svelte/lib/Checkmark.svelte';
    import WarningAltFilled from 'carbon-icons-svelte/lib/WarningAltFilled.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PointsMeter from '@/components/sprints/PointsMeter.svelte';
    import WeekStrip from '@/components/sprints/WeekStrip.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { formatWeekRange, weekName } from '@/lib/dates';
    import { cn } from '@/lib/utils';
    import { update } from '@/routes/sprints';

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
    } = $props();

    const team = $derived((page.props.currentTeam as Team).slug);
    const errors = $derived(
        (page.props.errors ?? {}) as Record<string, string | undefined>,
    );

    // The plan the student is editing. Saved only with "Save plan".
    let goal = $state('');
    let target = $state('');
    let selected = $state<number[]>([]);
    let saving = $state(false);

    // Reset the local plan whenever the server sends a new version of it.
    $effect(() => {
        goal = sprint.goal ?? '';
        target = sprint.targetPoints === null ? '' : String(sprint.targetPoints);
        selected = commitments
            .map((commitment) => commitment.taskId)
            .filter((id): id is number => id !== null);
    });

    const pointsOf = $derived(
        new Map(candidates.map((task) => [task.id, task.points ?? 0])),
    );
    const plannedPoints = $derived(
        selected.reduce((sum, id) => sum + (pointsOf.get(id) ?? 0), 0),
    );
    const targetNumber = $derived(target === '' ? null : Number(target));

    const savedIds = $derived(
        commitments
            .map((commitment) => commitment.taskId)
            .filter((id): id is number => id !== null)
            .sort((a, b) => a - b)
            .join(','),
    );
    const dirty = $derived(
        goal !== (sprint.goal ?? '') ||
            target !==
                (sprint.targetPoints === null ? '' : String(sprint.targetPoints)) ||
            [...selected].sort((a, b) => a - b).join(',') !== savedIds,
    );

    const groups = $derived.by(() => {
        const byProject = new Map<string, Candidate[]>();

        for (const task of candidates) {
            const key = `${task.areaName} · ${task.projectName}`;
            byProject.set(key, [...(byProject.get(key) ?? []), task]);
        }

        return [...byProject.entries()].map(([name, tasks]) => ({ name, tasks }));
    });

    const toggle = (id: number) => {
        selected = selected.includes(id)
            ? selected.filter((value) => value !== id)
            : [...selected, id];
    };

    const save = () => {
        router.patch(
            update({ current_team: team, sprint: sprint.id }).url,
            { goal, target_points: target, task_ids: selected },
            {
                preserveScroll: true,
                onStart: () => (saving = true),
                onFinish: () => (saving = false),
            },
        );
    };

    const timingLabel = $derived(
        { current: 'This week', upcoming: 'Upcoming', past: 'Ended' }[sprint.timing],
    );
</script>

<AppHead title={`Sprint ${formatWeekRange(sprint.weekStart, sprint.weekEnd)}`} />

<div class="flex flex-col gap-6 px-4 py-8 sm:py-10">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <p class="text-xs font-medium text-muted-foreground">
                {timingLabel}
            </p>
            <h1 class="type-title">
                {weekName(sprint.weekStart)}
                <span class="font-normal text-muted-foreground tabular-nums"
                    >· {formatWeekRange(sprint.weekStart, sprint.weekEnd)}</span
                >
            </h1>
        </div>
    </header>

    <WeekStrip weekStart={sprint.weekStart} />

    {#if sprint.editable}
        <div
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start"
        >
            <section aria-labelledby="backlog-title" class="min-w-0">
                <div class="mb-3 flex items-baseline justify-between gap-3">
                    <h2 id="backlog-title" class="type-section">
                        Choose tasks
                    </h2>
                    <span class="text-xs text-muted-foreground tabular-nums"
                        >{selected.length} chosen</span
                    >
                </div>

                {#if candidates.length === 0}
                    <p
                        class="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        No open tasks. Add tasks to a project first.
                    </p>
                {/if}

                <div class="flex flex-col gap-4">
                    {#each groups as group (group.name)}
                        <div
                            class="overflow-hidden rounded-lg border border-border bg-card"
                        >
                            <h3
                                class="border-b border-border bg-muted/30 px-4 py-2 text-xs font-medium text-muted-foreground"
                            >
                                {group.name}
                            </h3>
                            <ul>
                                {#each group.tasks as task (task.id)}
                                    {@const checked = selected.includes(task.id)}
                                    {@const taken = task.takenBy !== null}
                                    <li
                                        class="border-t border-border first:border-t-0"
                                    >
                                        <label
                                            class={cn(
                                                'flex min-h-11 items-center gap-3 px-4 py-2 transition-colors',
                                                taken
                                                    ? 'cursor-not-allowed opacity-60'
                                                    : 'cursor-pointer hover:bg-muted/40',
                                                checked && 'bg-accent/50',
                                            )}
                                        >
                                            <input
                                                type="checkbox"
                                                class="peer sr-only"
                                                {checked}
                                                disabled={taken}
                                                onchange={() => toggle(task.id)}
                                            />
                                            <span
                                                aria-hidden="true"
                                                class={cn(
                                                    'flex size-4 shrink-0 items-center justify-center rounded-sm border peer-focus-visible:ring-3 peer-focus-visible:ring-ring/50',
                                                    checked
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'border-input',
                                                )}
                                            >
                                                {#if checked}
                                                    <Checkmark class="size-3" />
                                                {/if}
                                            </span>
                                            <span
                                                class="flex min-w-0 flex-1 flex-col sm:flex-row sm:items-center sm:gap-3"
                                            >
                                                <span class="truncate text-sm"
                                                    >{task.title}</span
                                                >
                                                {#if taken && task.takenBy}
                                                    <span
                                                        class="shrink-0 text-xs text-muted-foreground"
                                                        >In the sprint of {formatShortDate(
                                                            task.takenBy,
                                                        )}</span
                                                    >
                                                {/if}
                                            </span>
                                            {#if task.dueDate}
                                                <span
                                                    class={cn(
                                                        'flex shrink-0 items-center gap-1 text-xs tabular-nums',
                                                        task.isOverdue
                                                            ? 'font-medium text-destructive'
                                                            : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {#if task.isOverdue}
                                                        <WarningAltFilled
                                                            class="size-3.5"
                                                        />
                                                        <span class="sr-only"
                                                            >Overdue,</span
                                                        >
                                                    {/if}
                                                    {formatShortDate(task.dueDate)}
                                                </span>
                                            {/if}
                                            <span
                                                class="w-12 shrink-0 text-right text-xs text-muted-foreground tabular-nums"
                                            >
                                                {task.points === null
                                                    ? '– pts'
                                                    : `${task.points} pts`}
                                            </span>
                                        </label>
                                    </li>
                                {/each}
                            </ul>
                        </div>
                    {/each}
                </div>
                <InputError class="mt-2" message={errors.task_ids} />
            </section>

            <aside
                class="flex flex-col gap-5 rounded-lg border border-border bg-card p-4 lg:sticky lg:top-6"
                aria-label="Sprint plan"
            >
                <div class="flex flex-col gap-1.5">
                    <label
                        for="sprint-goal"
                        class="text-xs font-medium text-muted-foreground"
                        >Sprint goal</label
                    >
                    <Input
                        id="sprint-goal"
                        bind:value={goal}
                        maxlength={200}
                        placeholder="For example: Hand in the lab report"
                        autocomplete="off"
                    />
                    <InputError message={errors.goal} />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label
                        for="sprint-target"
                        class="text-xs font-medium text-muted-foreground"
                        >Point target</label
                    >
                    <Input
                        id="sprint-target"
                        type="number"
                        min={1}
                        max={999}
                        inputmode="numeric"
                        bind:value={target}
                        placeholder="For example 13"
                        class="w-32"
                    />
                    <InputError message={errors.target_points} />
                </div>

                <PointsMeter committed={plannedPoints} target={targetNumber} />

                <div class="flex flex-col gap-2 border-t border-border pt-4">
                    <Button disabled={!dirty || saving} onclick={save}>
                        {saving ? 'Saving…' : 'Save plan'}
                    </Button>
                    <p class="text-center text-xs text-muted-foreground">
                        {dirty ? 'You have changes that are not saved.' : 'The plan is saved.'}
                    </p>
                </div>
            </aside>
        </div>
    {:else}
        <section aria-labelledby="plan-title" class="flex flex-col gap-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1">
                    <h2 id="plan-title" class="type-section">Plan</h2>
                    <p class="text-sm text-muted-foreground">
                        {sprint.goal ?? 'No goal'}
                    </p>
                </div>
                <PointsMeter
                    class="w-64"
                    committed={sprint.committedPoints}
                    target={sprint.targetPoints}
                />
            </div>
            <ul class="overflow-hidden rounded-lg border border-border bg-card">
                {#each commitments as commitment (commitment.id)}
                    <li
                        class="flex min-h-11 items-center justify-between gap-3 border-t border-border px-4 py-2 text-sm first:border-t-0"
                    >
                        <span class="min-w-0 truncate">{commitment.title}</span>
                        <span class="shrink-0 text-xs text-muted-foreground tabular-nums">
                            {commitment.points === null ? '– pts' : `${commitment.points} pts`}
                        </span>
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
