<script module lang="ts">
    import { index } from '@/routes/sprints';
    import type { Team } from '@/types';

    export const layout = (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Sprints',
                href: index(props.currentTeam?.slug ?? ''),
            },
        ],
    });
</script>

<script lang="ts">
    import { Form, Link, page } from '@inertiajs/svelte';
    import Add from 'carbon-icons-svelte/lib/Add.svelte';
    import ChevronRight from 'carbon-icons-svelte/lib/ChevronRight.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import FormDialog from '@/components/FormDialog.svelte';
    import InputError from '@/components/InputError.svelte';
    import PointsMeter from '@/components/sprints/PointsMeter.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import {
        addDays,
        formatWeekRange,
        parseDate,
        toDateString,
        weekName,
    } from '@/lib/dates';
    import { cn } from '@/lib/utils';
    import { show, store } from '@/routes/sprints';

    type SprintSummary = {
        id: number;
        weekStart: string;
        weekEnd: string;
        goal: string | null;
        targetPoints: number | null;
        committedPoints: number;
        taskCount: number;
        timing: 'current' | 'upcoming' | 'past';
    };

    let {
        sprints,
        freeWeeks,
    }: {
        sprints: SprintSummary[];
        freeWeeks: string[];
    } = $props();

    const team = $derived((page.props.currentTeam as Team).slug);

    const groups = $derived(
        (
            [
                ['current', 'This week'],
                ['upcoming', 'Upcoming'],
                ['past', 'Past'],
            ] as const
        )
            .map(([timing, title]) => ({
                timing,
                title,
                items: sprints.filter((sprint) => sprint.timing === timing),
            }))
            .filter((group) => group.items.length > 0),
    );

    const sundayOf = (monday: string) =>
        toDateString(addDays(parseDate(monday), 6));

    // The plan form opens in a dialog. The list of sprints is the page.
    const hasCurrent = $derived(sprints.some((sprint) => sprint.timing === 'current'));
    let planning = $state(false);
</script>

<AppHead title="Sprints" />

<div class="flex flex-col gap-8 px-4 py-8 sm:py-10">
    <header class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="type-title">Sprints</h1>
        {#if freeWeeks.length > 0}
            <Button onclick={() => (planning = true)}>
                <Add class="size-4" />
                Plan a sprint
            </Button>
        {/if}
    </header>

    {#if !hasCurrent && freeWeeks.length > 0}
        <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-dashed border-border px-4 py-4"
        >
            <p class="text-sm">
                <span class="font-medium">This week has no sprint.</span>
                <span class="text-muted-foreground">Plan it to see your tasks on the board.</span>
            </p>
            <Button variant="outline" onclick={() => (planning = true)}>Plan this week</Button>
        </div>
    {/if}

    <FormDialog
        bind:open={planning}
        title="Plan a sprint"
        description="Pick a Monday-to-Sunday week, a goal, and how many points fit. You choose the tasks next."
        size="lg"
    >

            <Form {...store.form(team)} class="flex flex-col gap-5">
                {#snippet children({ errors, processing })}
                    <fieldset>
                        <legend class="mb-2 text-sm font-medium">
                            Week
                        </legend>
                        <div class="grid grid-cols-2 gap-2">
                            {#each freeWeeks as monday, index (monday)}
                                <label
                                    class={cn(
                                        'flex cursor-pointer flex-col gap-0.5 rounded-md border border-input px-3 py-2.5 text-sm transition-colors',
                                        'hover:border-primary/50 has-[:checked]:border-primary has-[:checked]:bg-accent has-[:checked]:text-accent-foreground',
                                        'has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring/50',
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name="week_start"
                                        value={monday}
                                        checked={index === 0}
                                        class="sr-only"
                                    />
                                    <span class="font-medium">{weekName(monday)}</span>
                                    <span class="text-xs text-muted-foreground tabular-nums"
                                        >{formatWeekRange(monday, sundayOf(monday))}</span
                                    >
                                </label>
                            {/each}
                        </div>
                        <InputError message={errors.week_start} />
                    </fieldset>

                    <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem]">
                        <div class="flex flex-col gap-1.5">
                            <label
                                for="sprint-goal"
                                class="text-sm font-medium"
                                >Sprint goal</label
                            >
                            <Input
                                id="sprint-goal"
                                name="goal"
                                maxlength={200}
                                placeholder="For example: Hand in the lab report"
                                autocomplete="off"
                            />
                            <InputError message={errors.goal} />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label
                                for="sprint-target"
                                class="text-sm font-medium"
                                >Point target</label
                            >
                            <Input
                                id="sprint-target"
                                name="target_points"
                                type="number"
                                min={1}
                                max={999}
                                inputmode="numeric"
                                placeholder="For example 13"
                            />
                            <InputError message={errors.target_points} />
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button type="button" variant="ghost" onclick={() => (planning = false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Create sprint
                        </Button>
                    </div>
                {/snippet}
            </Form>
    </FormDialog>

    {#each groups as group (group.timing)}
        <section aria-labelledby={`group-${group.timing}`}>
            <h2
                id={`group-${group.timing}`}
                class="mb-2 text-xs font-medium tracking-[0.06em] text-muted-foreground uppercase"
            >
                {group.title}
            </h2>
            <ul class="overflow-hidden rounded-lg border border-border bg-card">
                {#each group.items as sprint (sprint.id)}
                    <li class="border-t border-border first:border-t-0">
                        <Link
                            href={show({ current_team: team, sprint: sprint.id })}
                            class="group grid gap-3 px-4 py-3.5 transition-colors hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:outline-none sm:grid-cols-[11rem_minmax(0,1fr)_14rem_1rem] sm:items-center sm:gap-6"
                        >
                            <span class="flex flex-col">
                                <span class="text-sm font-medium"
                                    >{weekName(sprint.weekStart)}</span
                                >
                                <span class="text-xs text-muted-foreground tabular-nums"
                                    >{formatWeekRange(sprint.weekStart, sprint.weekEnd)}</span
                                >
                            </span>
                            <span class="flex min-w-0 flex-col">
                                <span
                                    class={cn(
                                        'wrap-break-word text-sm',
                                        !sprint.goal && 'text-muted-foreground',
                                    )}>{sprint.goal ?? 'No goal yet'}</span
                                >
                                <span class="text-xs text-muted-foreground tabular-nums"
                                    >{sprint.taskCount}
                                    {sprint.taskCount === 1 ? 'task' : 'tasks'}</span
                                >
                            </span>
                            <PointsMeter
                                committed={sprint.committedPoints}
                                target={sprint.targetPoints}
                            />
                            <ChevronRight
                                class="hidden size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 sm:block"
                            />
                        </Link>
                    </li>
                {/each}
            </ul>
        </section>
    {/each}
</div>
