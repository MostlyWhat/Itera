<script module lang="ts">
    import { guide } from '@/routes';
    import type { Team } from '@/types';

    export const layout = (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [{ title: 'Guide', href: guide(props.currentTeam?.slug ?? '') }],
    });
</script>

<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { dashboard } from '@/routes';
    import { index as projectsIndex } from '@/routes/projects';
    import { index as sprintsIndex } from '@/routes/sprints';

    // How Itera works: the words it uses, the weekly rhythm, and the first
    // steps. A page to read, so the text keeps a short line length.
    const team = $derived((page.props.currentTeam as Team).slug);

    const concepts = [
        ['Area', 'One part of your life, for example a class, a job, or home. Areas hold projects.'],
        ['Project', 'One piece of work with an end, for example a lab report or a midterm. Projects hold tasks.'],
        ['Task', 'One step you can finish, for example "Write the method section". A task can have a deadline and a description.'],
        ['Story points', 'How big a task is, compared with your other tasks: 1, 2, 3, 5, 8, 13, or 21. Points are not hours. A 5 is about twice a 3.'],
        ['Sprint', 'One week of work. You choose the tasks that fit, give the week a goal, and set a point target.'],
        ['Backlog', 'All open tasks that are not in a sprint. You choose sprint tasks from it.'],
        ['Board', 'This week as three columns: To Do, In Progress, and Done. You move each task as you work.'],
    ];
</script>

<AppHead title="Guide" />

<article class="flex max-w-[65ch] flex-col gap-10 px-4 py-8 sm:py-10">
    <header class="space-y-2">
        <h1 class="type-title">How Itera works</h1>
        <p class="text-sm text-muted-foreground">
            Itera helps you decide how much work fits in one week, then finish it. You
            sort your work, size it, plan a week, and move tasks across a board.
        </p>
    </header>

    <section aria-labelledby="words" class="space-y-4">
        <h2 id="words" class="type-section">The words Itera uses</h2>
        <dl class="divide-y divide-border rounded-lg border border-border bg-card">
            {#each concepts as [term, meaning] (term)}
                <div class="grid gap-1 px-4 py-3 sm:grid-cols-[8rem_minmax(0,1fr)] sm:gap-4">
                    <dt class="text-sm font-medium">{term}</dt>
                    <dd class="text-sm text-muted-foreground">{meaning}</dd>
                </div>
            {/each}
        </dl>
    </section>

    <section aria-labelledby="week" class="space-y-4">
        <h2 id="week" class="type-section">Your week</h2>
        <ol class="space-y-3 text-sm">
            <li>
                <span class="font-medium">Plan at the start.</span>
                <span class="text-muted-foreground">
                    Choose the tasks that fit, and stop at your point target. A smaller
                    week that you finish is better than a full week that you do not.
                </span>
            </li>
            <li>
                <span class="font-medium">Work through the week.</span>
                <span class="text-muted-foreground">
                    Move a task to In Progress when you start it, and to Done when you
                    finish. Keep few tasks in progress at the same time.
                </span>
            </li>
            <li>
                <span class="font-medium">Look back at the end.</span>
                <span class="text-muted-foreground">
                    Compare the points you finished with your target. Use that number
                    for the next target.
                </span>
            </li>
        </ol>
    </section>

    <section aria-labelledby="start" class="space-y-4">
        <h2 id="start" class="type-section">Get started</h2>
        <ol class="list-decimal space-y-3 pl-5 text-sm marker:text-muted-foreground">
            <li>
                Open <Link href={projectsIndex(team)} class="font-medium text-primary underline-offset-4 hover:underline">Projects</Link>.
                Create an area with the area filter, then add a project to it.
            </li>
            <li>
                Use <span class="font-medium">Add task</span> on the project. Give each task story
                points, and a deadline if it has one.
            </li>
            <li>
                Open <Link href={sprintsIndex(team)} class="font-medium text-primary underline-offset-4 hover:underline">Sprints</Link>
                and use <span class="font-medium">Plan a sprint</span>. Add tasks from the backlog
                until you reach your target.
            </li>
            <li>
                Work from <Link href={dashboard(team)} class="font-medium text-primary underline-offset-4 hover:underline">This week</Link>.
                Drag a card to the next column, or use its arrow buttons.
            </li>
        </ol>
    </section>

    <section aria-labelledby="points" class="space-y-3">
        <h2 id="points" class="type-section">Choosing story points</h2>
        <p class="text-sm text-muted-foreground">
            Pick one small task that you know well and call it a 2. Size the other tasks
            against it. If a task feels bigger than 13, split it into smaller tasks.
        </p>
    </section>
</article>
