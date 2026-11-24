<script module lang="ts">
    import { index, review, show } from '@/routes/sprints';
    import type { Team } from '@/types';

    export const layout = (props: {
        currentTeam?: Team | null;
        sprint: { id: number };
    }) => ({
        breadcrumbs: [
            { title: 'Sprints', href: index(props.currentTeam?.slug ?? '') },
            {
                title: 'Sprint',
                href: show({ current_team: props.currentTeam?.slug ?? '', sprint: props.sprint.id }),
            },
            {
                title: 'Review',
                href: review({ current_team: props.currentTeam?.slug ?? '', sprint: props.sprint.id }),
            },
        ],
    });
</script>

<script lang="ts">
    import { Form, Link, page } from '@inertiajs/svelte';
    import CheckmarkFilled from 'carbon-icons-svelte/lib/CheckmarkFilled.svelte';
    import ChevronRight from 'carbon-icons-svelte/lib/ChevronRight.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button, buttonVariants } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Textarea } from '@/components/ui/textarea';
    import { formatWeekRange } from '@/lib/dates';
    import { cn } from '@/lib/utils';
    import { store, update } from '@/routes/sprints/review';

    type Row = { id: number; title: string; points: number | null; projectName: string | null };

    let {
        reviewed,
        sprint,
        done,
        unfinished,
        retrospective,
        nextSprint,
        suggestion,
    }: {
        reviewed: boolean;
        sprint: {
            id: number;
            weekStart: string;
            weekEnd: string;
            goal: string | null;
            targetPoints: number | null;
            committedPoints: number;
            completedPoints: number;
        };
        done: Row[];
        unfinished: (Row & {
            deleted: boolean;
            statusLabel: string | null;
            closingStatus: string | null;
            closingLabel: string | null;
        })[];
        retrospective: { went_well: string | null; blockers: string | null; improve_next: string | null } | null;
        nextSprint: { weekStart: string; weekEnd: string; targetPoints: number | null; committedPoints: number } | null;
        suggestion: { points: number; sprints: number; average: number } | null;
    } = $props();

    const team = $derived((page.props.currentTeam as Team).slug);
    const range = $derived(formatWeekRange(sprint.weekStart, sprint.weekEnd));
    const percent = $derived(
        sprint.committedPoints === 0 ? 0 : Math.round((sprint.completedPoints / sprint.committedPoints) * 100),
    );
    const choosable = $derived(unfinished.filter((task) => !task.deleted));
    const taskCount = $derived(done.length + unfinished.length);

    // Each unfinished Task starts in the backlog. "Plan again" is a choice the
    // user makes, because it adds the Task to a sprint.
    let decisions = $state<Record<number, 'carried_over' | 'dropped'>>({});
    const decisionOf = (id: number) => decisions[id] ?? 'dropped';
    const setAll = (value: 'carried_over' | 'dropped') => {
        decisions = Object.fromEntries(choosable.map((task) => [task.id, value]));
    };
    const plannedAgainTasks = $derived(
        reviewed ? [] : choosable.filter((task) => decisionOf(task.id) === 'carried_over'),
    );
    const plannedAgain = $derived(plannedAgainTasks.length);
    const plannedAgainPoints = $derived(plannedAgainTasks.reduce((sum, task) => sum + (task.points ?? 0), 0));

    // The target of the next sprint. The suggestion is the average of the
    // points completed in the last sprints. A new review turns the change on
    // when the suggestion differs from the target. An edit starts with it off.
    // svelte-ignore state_referenced_locally
    let setTarget = $state(!reviewed && suggestion !== null && suggestion.points !== nextSprint?.targetPoints);
    // svelte-ignore state_referenced_locally
    let target = $state<number | null>(suggestion?.points ?? nextSprint?.targetPoints ?? null);
    const nextPlanned = $derived((nextSprint?.committedPoints ?? 0) + plannedAgainPoints);

    const nextLabel = $derived(
        nextSprint === null
            ? 'a new sprint for this week'
            : `the sprint of ${formatWeekRange(nextSprint.weekStart, nextSprint.weekEnd)}`,
    );

    const choices = [
        { value: 'carried_over', label: 'Plan again' },
        { value: 'dropped', label: 'Back to backlog' },
    ] as const;

    const questions = [
        { name: 'went_well', label: 'What went well?', placeholder: 'For example: I booked the lab early' },
        { name: 'blockers', label: 'What got in the way?', placeholder: 'For example: Two meetings moved' },
        { name: 'improve_next', label: 'What will you change next sprint?', placeholder: 'For example: Plan fewer points in exam weeks' },
    ] as const;

    const heading = 'text-sm font-semibold';
</script>

<AppHead title="Sprint review" />

<Form
    {...reviewed
        ? update.form({ current_team: team, sprint: sprint.id })
        : store.form({ current_team: team, sprint: sprint.id })}
    class="mx-auto flex w-full max-w-3xl flex-col gap-10 px-4 py-8 sm:py-10"
>
    {#snippet children({ errors, processing })}
        <header class="flex flex-col gap-1">
            <p class="text-sm text-muted-foreground tabular-nums">
                Sprint review · {range}{reviewed ? ' · Reviewed' : ''}
            </p>
            <h1 class="type-title text-balance">{reviewed ? 'Edit your review' : 'How did the week go?'}</h1>
            {#if sprint.goal}
                <p class="text-sm text-muted-foreground">Goal: {sprint.goal}</p>
            {/if}
        </header>

        <section aria-labelledby="results" class="flex flex-col gap-3">
            <h2 id="results" class="sr-only">Results</h2>
            <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-border bg-border sm:grid-cols-3">
                <div class="flex flex-col gap-1 bg-card px-4 py-4">
                    <dt class="text-xs text-muted-foreground">Committed</dt>
                    <dd class="tabular-nums">
                        <span class="text-2xl font-semibold">{sprint.committedPoints}</span>
                        <span class="text-sm text-muted-foreground">
                            {sprint.targetPoints === null ? 'points' : `of ${sprint.targetPoints} target`}
                        </span>
                    </dd>
                </div>
                <div class="flex flex-col gap-1 bg-card px-4 py-4">
                    <dt class="text-xs text-muted-foreground">Completed</dt>
                    <dd class="tabular-nums">
                        <span class="text-2xl font-semibold">{sprint.completedPoints}</span>
                        <span class="text-sm text-muted-foreground">points, {percent}%</span>
                    </dd>
                </div>
                <div class="col-span-2 flex flex-col gap-1 bg-card px-4 py-4 sm:col-span-1">
                    <dt class="text-xs text-muted-foreground">Tasks done</dt>
                    <dd class="tabular-nums">
                        <span class="text-2xl font-semibold">{done.length}</span>
                        <span class="text-sm text-muted-foreground">of {taskCount}</span>
                    </dd>
                </div>
            </dl>
            <div
                class="h-1.5 overflow-hidden rounded-full bg-muted"
                role="meter"
                aria-label="Completed story points"
                aria-valuemin={0}
                aria-valuemax={sprint.committedPoints}
                aria-valuenow={sprint.completedPoints}
            >
                <div class="h-full rounded-full bg-primary" style:width={`${percent}%`}></div>
            </div>
            <p class="text-xs text-muted-foreground">
                Itera saves these numbers with the review. Later changes to the tasks do not change them.
            </p>
        </section>

        {#if unfinished.length > 0}
            <section aria-labelledby="unfinished" class="flex flex-col gap-3">
                <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-2">
                    <div class="flex flex-col gap-0.5">
                        <h2 id="unfinished" class={heading}>Unfinished tasks ({unfinished.length})</h2>
                        <p class="text-sm text-pretty text-muted-foreground">
                            {#if reviewed}
                                The review saved these choices as part of the results of the week.
                            {:else}
                                Plan again adds a task to {nextLabel}. Back to backlog keeps it free to plan later.
                            {/if}
                        </p>
                    </div>
                    {#if !reviewed && choosable.length > 1}
                        <div class="flex gap-1 text-sm">
                            <Button type="button" variant="ghost" size="sm" onclick={() => setAll('carried_over')}>
                                Plan all again
                            </Button>
                            <Button type="button" variant="ghost" size="sm" onclick={() => setAll('dropped')}>
                                All to backlog
                            </Button>
                        </div>
                    {/if}
                </div>

                <ul class="overflow-hidden rounded-lg border border-border bg-card">
                    {#each unfinished as task (task.id)}
                        <li class="flex flex-col gap-3 border-t border-border px-4 py-3 first:border-t-0 sm:flex-row sm:items-center sm:gap-4">
                            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span class={cn('text-sm wrap-break-word', task.deleted && 'text-muted-foreground line-through')}>
                                    {task.title}
                                </span>
                                <span class="text-xs text-muted-foreground tabular-nums">
                                    {#if task.deleted}
                                        Deleted. It stays in the sprint history.
                                    {:else}
                                        {task.projectName} · {task.statusLabel} · {task.points ?? '–'} pts
                                    {/if}
                                </span>
                            </div>
                            {#if reviewed}
                                {#if task.closingLabel}
                                    <span class="w-fit shrink-0 rounded-sm bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground">
                                        {task.closingLabel}
                                    </span>
                                {/if}
                            {:else if !task.deleted}
                                <fieldset class="shrink-0">
                                    <legend class="sr-only">What to do with {task.title}</legend>
                                    <div class="inline-flex h-9 overflow-hidden rounded-md border border-input shadow-xs">
                                        {#each choices as choice (choice.value)}
                                            <label
                                                class={cn(
                                                    'flex cursor-pointer items-center border-l border-input px-3 text-sm whitespace-nowrap first:border-l-0',
                                                    'text-muted-foreground hover:bg-muted hover:text-foreground',
                                                    'has-[:checked]:bg-primary has-[:checked]:text-primary-foreground',
                                                    'has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring/50',
                                                )}
                                            >
                                                <input
                                                    type="radio"
                                                    class="sr-only"
                                                    name={`decisions[${task.id}]`}
                                                    value={choice.value}
                                                    checked={decisionOf(task.id) === choice.value}
                                                    onchange={() => (decisions[task.id] = choice.value)}
                                                />
                                                {choice.label}
                                            </label>
                                        {/each}
                                    </div>
                                    <InputError message={errors[`decisions.${task.id}`]} />
                                </fieldset>
                            {/if}
                        </li>
                    {/each}
                </ul>
            </section>
        {/if}

        <section aria-labelledby="next-target" class="flex flex-col gap-3">
            <div class="flex flex-col gap-0.5">
                <h2 id="next-target" class={heading}>Next sprint target</h2>
                <p class="text-sm text-pretty text-muted-foreground">
                    {#if suggestion && suggestion.sprints === 1}
                        You completed {suggestion.average} points in this sprint. A target near that number is realistic.
                    {:else if suggestion}
                        In your last {suggestion.sprints} sprints, you completed {suggestion.average} points a week on
                        average. A target near that number is realistic.
                    {:else}
                        You completed no points yet. Keep the next target small until you know your pace.
                    {/if}
                </p>
            </div>
            <div class="flex flex-col gap-4 rounded-lg border border-border bg-card px-4 py-4">
                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                    <div class="flex flex-col gap-0.5">
                        <dt class="text-xs text-muted-foreground">Suggested</dt>
                        <dd class="tabular-nums">
                            {#if suggestion}
                                <span class="text-lg font-semibold">{suggestion.points}</span> points
                            {:else}
                                <span class="text-muted-foreground">No data yet</span>
                            {/if}
                        </dd>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <dt class="text-xs text-muted-foreground">Target now</dt>
                        <dd class="tabular-nums">
                            {#if nextSprint?.targetPoints}
                                <span class="text-lg font-semibold">{nextSprint.targetPoints}</span> points
                            {:else}
                                <span class="text-muted-foreground">No target</span>
                            {/if}
                        </dd>
                    </div>
                    <div class="col-span-2 flex flex-col gap-0.5 sm:col-span-1">
                        <dt class="text-xs text-muted-foreground">Planned</dt>
                        <dd class="tabular-nums">
                            <span class="text-lg font-semibold">{nextPlanned}</span> points
                            {#if plannedAgainPoints > 0}
                                <span class="text-xs text-muted-foreground">({plannedAgainPoints} planned again)</span>
                            {/if}
                        </dd>
                    </div>
                </dl>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-border pt-4">
                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                        <input type="checkbox" bind:checked={setTarget} class="size-4 accent-primary" />
                        Set the target of {nextLabel} to
                    </label>
                    <div class="flex items-center gap-2">
                        <label for="next-target-input" class="sr-only">Target points</label>
                        <Input
                            id="next-target-input"
                            name="next_target"
                            type="number"
                            inputmode="numeric"
                            min={1}
                            max={999}
                            bind:value={target}
                            disabled={!setTarget}
                            aria-invalid={errors.next_target ? 'true' : undefined}
                            class="h-9 w-24 tabular-nums"
                        />
                        <span class="text-sm text-muted-foreground">points</span>
                        {#if suggestion && target !== suggestion.points}
                            {@const suggested = suggestion.points}
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onclick={() => {
                                    target = suggested;
                                    setTarget = true;
                                }}
                            >
                                Use {suggested}
                            </Button>
                        {/if}
                    </div>
                </div>
                <InputError message={errors.next_target} />
            </div>
        </section>

        {#if done.length > 0}
            <details class="group">
                <summary
                    class="flex w-fit cursor-pointer list-none items-center gap-1.5 rounded-md text-sm font-semibold focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none [&::-webkit-details-marker]:hidden"
                >
                    <ChevronRight class="size-4 text-muted-foreground group-open:rotate-90 motion-safe:transition-transform" />
                    Done ({done.length})
                </summary>
                <ul class="mt-3 overflow-hidden rounded-lg border border-border bg-card">
                    {#each done as task (task.id)}
                        <li class="flex items-center gap-3 border-t border-border px-4 py-2.5 first:border-t-0">
                            <CheckmarkFilled class="size-4 shrink-0 text-primary" aria-hidden="true" />
                            <span class="min-w-0 flex-1 text-sm wrap-break-word">{task.title}</span>
                            <span class="shrink-0 text-xs text-muted-foreground tabular-nums">{task.points ?? '–'} pts</span>
                        </li>
                    {/each}
                </ul>
            </details>
        {/if}

        <section aria-labelledby="retrospective" class="flex flex-col gap-5">
            <div class="flex flex-col gap-0.5">
                <h2 id="retrospective" class={heading}>Retrospective</h2>
                <p class="text-sm text-muted-foreground">A few words help you plan the next sprint. Each answer is optional.</p>
            </div>
            {#each questions as question (question.name)}
                <div class="flex flex-col gap-2">
                    <label for={`retro-${question.name}`} class="text-sm font-medium">{question.label}</label>
                    <Textarea
                        id={`retro-${question.name}`}
                        name={question.name}
                        rows={3}
                        maxlength={2000}
                        placeholder={question.placeholder}
                        value={retrospective?.[question.name] ?? ''}
                        aria-invalid={errors[question.name] ? 'true' : undefined}
                        class="max-h-48"
                    />
                    <InputError message={errors[question.name]} />
                </div>
            {/each}
        </section>

        <div class="flex flex-col-reverse gap-3 border-t border-border pt-6 sm:flex-row sm:items-center sm:justify-end">
            <Link
                href={show({ current_team: team, sprint: sprint.id })}
                class={cn(buttonVariants({ variant: 'ghost' }), 'sm:me-auto')}
            >
                {reviewed ? 'Cancel' : 'Review later'}
            </Link>
            <p class="text-xs text-muted-foreground tabular-nums empty:hidden sm:text-end" aria-live="polite">{plannedAgain > 0
                    ? `${plannedAgain} ${plannedAgain === 1 ? 'task goes' : 'tasks go'} to ${nextLabel}.`
                    : ''}</p>
            <Button type="submit" disabled={processing}>{reviewed ? 'Save changes' : 'Complete review'}</Button>
        </div>
    {/snippet}
</Form>
