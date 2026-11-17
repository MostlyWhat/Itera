<script module lang="ts">
    import { guide } from '@/routes';
    import type { Team } from '@/types';

    export const layout = (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [{ title: 'Guide', href: guide(props.currentTeam?.slug ?? '') }],
    });
</script>

<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ArrowLeft from 'carbon-icons-svelte/lib/ArrowLeft.svelte';
    import ArrowRight from 'carbon-icons-svelte/lib/ArrowRight.svelte';
    import { onMount, tick } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import {
        Select,
        SelectContent,
        SelectGroup,
        SelectGroupHeading,
        SelectItem,
        SelectTrigger,
    } from '@/components/ui/select';
    import { cn } from '@/lib/utils';
    import { archive, dashboard } from '@/routes';
    import { edit as editPlanning } from '@/routes/planning';
    import { index as projectsIndex } from '@/routes/projects';
    import { index as sprintsIndex } from '@/routes/sprints';

    // How Itera works, as a small documentation site. The sidebar lists the
    // topics, and the page shows one short topic at a time, so the reader
    // jumps to what they need and does not scroll. The URL hash keeps the
    // topic, so a link can open one topic directly.
    const team = $derived((page.props.currentTeam as Team).slug);

    const groups = [
        {
            title: 'Start here',
            topics: [
                { id: 'overview', title: 'How Itera works' },
                { id: 'words', title: 'The words Itera uses' },
                { id: 'first-steps', title: 'Your first week' },
            ],
        },
        {
            title: 'Plan and work',
            topics: [
                { id: 'story-points', title: 'Choose story points' },
                { id: 'plan', title: 'Plan a sprint' },
                { id: 'board', title: 'Work from the board' },
                { id: 'rhythm', title: 'The weekly rhythm' },
            ],
        },
        {
            title: 'Organize',
            topics: [
                { id: 'areas', title: 'Areas and projects' },
                { id: 'archive', title: 'Archive and restore' },
                { id: 'week-start', title: 'The first day of the week' },
            ],
        },
    ] as const;

    type TopicId = (typeof groups)[number]['topics'][number]['id'];
    const topics = groups.flatMap((group) => [...group.topics]);
    const isTopic = (value: string): value is TopicId => topics.some((topic) => topic.id === value);

    let current = $state<TopicId>('overview');
    let heading = $state<HTMLHeadingElement>();

    const index = $derived(topics.findIndex((topic) => topic.id === current));
    const previous = $derived(topics[index - 1]);
    const next = $derived(topics[index + 1]);

    // Show a topic, keep it in the URL, and move focus to its heading so a
    // screen reader reads the new topic. Replace the history entry: the hash
    // is a bookmark, not a new page.
    const show = async (id: TopicId) => {
        current = id;
        history.replaceState(history.state, '', `#${id}`);
        window.scrollTo({ top: 0 });
        await tick();
        heading?.focus({ preventScroll: true });
    };

    onMount(() => {
        const fromHash = () => {
            const id = window.location.hash.slice(1);

            if (isTopic(id)) {
                current = id;
            }
        };

        fromHash();
        window.addEventListener('hashchange', fromHash);

        return () => window.removeEventListener('hashchange', fromHash);
    });

    const link = 'font-medium text-primary underline-offset-4 hover:underline';
    const concepts = [
        ['Area', 'One part of your life, for example a class, a job, or home. Areas hold projects.'],
        ['Project', 'One piece of work with an end, for example a lab report or a midterm. Projects hold tasks.'],
        ['Task', 'One step you can finish, for example "Write the method section". A task can have a deadline and a description.'],
        ['Story points', 'How big a task is, compared with your other tasks: 1, 2, 3, 5, 8, 13, or 21. Points are not hours.'],
        ['Sprint', 'One week of work. You choose the tasks that fit, give the week a goal, and set a point target.'],
        ['Backlog', 'All open tasks that are not in a sprint. You choose sprint tasks from it.'],
        ['Board', 'This week as three columns: To Do, In Progress, and Done.'],
    ];
    const sizes = [
        ['1', 'Very small. Send an email, or book a room.'],
        ['2', 'Small. Read one article and take notes.'],
        ['3', 'Medium. Write one section of a report.'],
        ['5', 'Large. Prepare the slides for a talk.'],
        ['8', 'Very large. Study for a midterm.'],
        ['13', 'Big for one week. Think about a split.'],
        ['21', 'Too big for one week. Split it into smaller tasks.'],
    ];
</script>

<AppHead title="Guide" />

{#snippet overview()}
    <p>
        Itera helps you decide how much work fits in one week, and then finish it. You do
        five things, in this order:
    </p>
    <ol class="list-decimal space-y-2 pl-5 marker:text-muted-foreground">
        <li><span class="font-medium">Sort</span> your work into areas and projects.</li>
        <li><span class="font-medium">Split</span> each project into tasks you can finish.</li>
        <li><span class="font-medium">Size</span> each task with story points.</li>
        <li><span class="font-medium">Plan</span> a sprint: the tasks that fit in one week.</li>
        <li><span class="font-medium">Work</span> from the board, and look back at the end of the week.</li>
    </ol>
    <p>New to Itera? Read the next two topics, then plan your first week.</p>
{/snippet}

{#snippet words()}
    <dl class="divide-y divide-border rounded-lg border border-border bg-card">
        {#each concepts as [term, meaning] (term)}
            <div class="grid gap-1 px-4 py-3 sm:grid-cols-[8rem_minmax(0,1fr)] sm:gap-4">
                <dt class="font-medium">{term}</dt>
                <dd class="text-muted-foreground">{meaning}</dd>
            </div>
        {/each}
    </dl>
{/snippet}

{#snippet firstSteps()}
    <ol class="list-decimal space-y-3 pl-5 marker:text-muted-foreground">
        <li>
            Open <Link href={projectsIndex(team)} class={link}>Projects</Link>. Open the area
            filter and use <span class="font-medium">New area</span>.
        </li>
        <li>Use the plus button next to the area name to add a project.</li>
        <li>
            On the project, use <span class="font-medium">Add task</span>. Give each task story
            points, and a deadline if it has one.
        </li>
        <li>
            Open <Link href={sprintsIndex(team)} class={link}>Sprints</Link> and use
            <span class="font-medium">Plan a sprint</span>. Add tasks from the backlog until you
            reach your target.
        </li>
        <li>
            Work from <Link href={dashboard(team)} class={link}>This week</Link>.
        </li>
    </ol>
{/snippet}

{#snippet storyPoints()}
    <p>
        Pick one small task that you know well and call it a 2. Size the other tasks against
        it. A 5 is about twice a 3. Use these examples as a start:
    </p>
    <dl class="divide-y divide-border rounded-lg border border-border bg-card">
        {#each sizes as [points, example] (points)}
            <div class="grid grid-cols-[3rem_minmax(0,1fr)] gap-4 px-4 py-2">
                <dt class="font-medium tabular-nums">{points}</dt>
                <dd class="text-muted-foreground">{example}</dd>
            </div>
        {/each}
    </dl>
{/snippet}

{#snippet plan()}
    <ol class="list-decimal space-y-3 pl-5 marker:text-muted-foreground">
        <li>
            On <Link href={sprintsIndex(team)} class={link}>Sprints</Link>, use
            <span class="font-medium">Plan a sprint</span>. Pick the week, a goal, and a point
            target.
        </li>
        <li>On the sprint page, add tasks from the backlog with their plus buttons.</li>
        <li>Stop at your target. The sprint page shows the points you chose.</li>
    </ol>
    <p>
        For your first sprint, choose a small target, for example 15 points. A smaller week
        that you finish is better than a full week that you do not.
    </p>
    <p class="text-muted-foreground">
        A done task cannot go into a new sprint. A task that you finish during the sprint
        stays in it, so the sprint keeps a record of your work.
    </p>
{/snippet}

{#snippet board()}
    <ul class="list-disc space-y-3 pl-5 marker:text-muted-foreground">
        <li>
            Move a task to <span class="font-medium">In Progress</span> when you start it, and to
            <span class="font-medium">Done</span> when you finish it.
        </li>
        <li>Drag a card to a column, or use the arrow buttons on the card.</li>
        <li>Click a card to see its description, story points, and deadline.</li>
        <li>Keep few tasks in progress at the same time. Finish one before you start the next.</li>
    </ul>
    <p class="text-muted-foreground">
        Did you move a task to Done by mistake? Move it back on the board, or use
        <span class="font-medium">Move to To Do</span> on the sprint page.
    </p>
{/snippet}

{#snippet rhythm()}
    <dl class="space-y-4">
        <div>
            <dt class="font-medium">At the start of the week</dt>
            <dd class="text-muted-foreground">
                Plan the sprint. Choose the tasks that fit, and stop at your target.
            </dd>
        </div>
        <div>
            <dt class="font-medium">During the week</dt>
            <dd class="text-muted-foreground">
                Open This week each day. Move the cards as you work.
            </dd>
        </div>
        <div>
            <dt class="font-medium">At the end of the week</dt>
            <dd class="text-muted-foreground">
                Compare the points you finished with your target. Use that number for the next
                target.
            </dd>
        </div>
    </dl>
{/snippet}

{#snippet areas()}
    <ul class="list-disc space-y-3 pl-5 marker:text-muted-foreground">
        <li>
            The area filter on <Link href={projectsIndex(team)} class={link}>Projects</Link> picks
            which areas the list shows. Search for an area by name, and select it to show or hide it.
        </li>
        <li>Hide the areas you do not use this term. Their projects stay, and you can show them again.</li>
        <li>Use the plus button next to an area to add a project to it.</li>
        <li>Use the menu next to an area to rename or archive it.</li>
        <li>Edit or archive a project from the menu on the project page.</li>
    </ul>
{/snippet}

{#snippet archiveTopic()}
    <p>
        Archive an area or a project when it ends, for example when a class finishes. Archived
        work leaves the project list, and you do not lose it.
    </p>
    <p>
        Open <Link href={archive(team)} class={link}>Archive</Link> at the bottom of the project
        list. Use <span class="font-medium">Restore</span> to put an item back in the list.
    </p>
{/snippet}

{#snippet weekStart()}
    <p>
        A sprint starts on the first day of your week. Monday is the default. To change it, open
        <Link href={editPlanning()} class={link}>Settings &gt; Planning</Link> and choose a day.
    </p>
    <p class="text-muted-foreground">
        New sprints use the new day. Sprints that you already planned keep their dates.
    </p>
{/snippet}

{#snippet topicNav()}
    {#each groups as group (group.title)}
        <div class="flex flex-col gap-1">
            <h2 class="px-2 text-xs font-medium tracking-[0.06em] text-muted-foreground uppercase">
                {group.title}
            </h2>
            <ul class="flex flex-col gap-0.5">
                {#each group.topics as topic (topic.id)}
                    {@const active = topic.id === current}
                    <li>
                        <a
                            href={`#${topic.id}`}
                            aria-current={active ? 'page' : undefined}
                            onclick={(event) => {
                                event.preventDefault();
                                show(topic.id);
                            }}
                            class={cn(
                                'flex min-h-8 items-center rounded-md px-2 py-1 text-sm transition-colors focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                                active
                                    ? 'bg-accent font-medium text-accent-foreground'
                                    : 'text-foreground/80 hover:bg-muted hover:text-foreground',
                            )}
                        >
                            {topic.title}
                        </a>
                    </li>
                {/each}
            </ul>
        </div>
    {/each}
{/snippet}

<div class="flex gap-10 px-4 py-8 sm:py-10">
    <aside class="hidden w-56 shrink-0 lg:block">
        <nav aria-label="Guide topics" class="sticky top-6 flex flex-col gap-6">
            {@render topicNav()}
        </nav>
    </aside>

    <div class="flex min-w-0 max-w-[65ch] flex-1 flex-col gap-6">
        <div class="flex flex-col gap-2 lg:hidden">
            <label for="guide-topic" class="text-sm font-medium">Topic</label>
            <Select type="single" value={current} onValueChange={(value) => isTopic(value) && show(value)}>
                <SelectTrigger id="guide-topic" class="w-full">{topics[index].title}</SelectTrigger>
                <SelectContent>
                    {#each groups as group (group.title)}
                        <SelectGroup>
                            <SelectGroupHeading>{group.title}</SelectGroupHeading>
                            {#each group.topics as topic (topic.id)}
                                <SelectItem value={topic.id} label={topic.title} />
                            {/each}
                        </SelectGroup>
                    {/each}
                </SelectContent>
            </Select>
        </div>

        <article aria-labelledby="guide-topic-title" class="flex flex-col gap-4 text-sm leading-relaxed">
            <header class="space-y-1">
                <p class="text-xs font-medium tracking-[0.06em] text-muted-foreground uppercase">
                    {groups.find((group) => group.topics.some((topic) => topic.id === current))?.title}
                </p>
                <h1 id="guide-topic-title" bind:this={heading} tabindex="-1" class="type-title focus:outline-none">
                    {topics[index].title}
                </h1>
            </header>

            {@render ({
                overview,
                words,
                'first-steps': firstSteps,
                'story-points': storyPoints,
                plan,
                board,
                rhythm,
                areas,
                archive: archiveTopic,
                'week-start': weekStart,
            })[current]()}
        </article>

        <nav aria-label="Previous and next topic" class="grid grid-cols-2 gap-3 border-t border-border pt-6">
            {#if previous}
                <a
                    href={`#${previous.id}`}
                    onclick={(event) => {
                        event.preventDefault();
                        show(previous.id);
                    }}
                    class="flex flex-col gap-0.5 rounded-lg border border-border px-4 py-3 text-sm transition-colors hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <span class="flex items-center gap-1 text-xs text-muted-foreground">
                        <ArrowLeft class="size-3.5" aria-hidden="true" />
                        Previous
                    </span>
                    <span class="font-medium">{previous.title}</span>
                </a>
            {:else}
                <span></span>
            {/if}
            {#if next}
                <a
                    href={`#${next.id}`}
                    onclick={(event) => {
                        event.preventDefault();
                        show(next.id);
                    }}
                    class="flex flex-col items-end gap-0.5 rounded-lg border border-border px-4 py-3 text-end text-sm transition-colors hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <span class="flex items-center gap-1 text-xs text-muted-foreground">
                        Next
                        <ArrowRight class="size-3.5" aria-hidden="true" />
                    </span>
                    <span class="font-medium">{next.title}</span>
                </a>
            {/if}
        </nav>
    </div>
</div>
