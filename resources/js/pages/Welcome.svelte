<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import AppWordmark from '@/components/AppWordmark.svelte';
    import WeekFrame from '@/components/WeekFrame.svelte';
    import { buttonVariants } from '@/components/ui/button';
    import { cn, toUrl } from '@/lib/utils';
    import { dashboard, home, login, register } from '@/routes';
    import type { Team } from '@/types';

    const auth = $derived(page.props.auth);
    const currentTeam = $derived(page.props.currentTeam as Team | null);
    const dashboardUrl = $derived(
        currentTeam ? dashboard(currentTeam.slug) : '/',
    );

    const repositoryUrl = 'https://github.com/MostlyWhat/Itera';
    const issuesUrl = `${repositoryUrl}/issues`;
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    const primary = cn(buttonVariants({ size: 'lg' }), 'h-11 px-5 text-[15px]');
    const secondary = cn(
        buttonVariants({ variant: 'outline', size: 'lg' }),
        'h-11 px-5 text-[15px]',
    );
</script>

<AppHead title="Plan a week you can finish" />

<div class="min-h-screen overflow-x-clip bg-background text-foreground">
    <div class="relative">
        <!-- The week frame: seven day columns behind the whole page. -->
        <WeekFrame />

        <header class="relative border-b border-border bg-background">
            <nav
                class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-10 lg:px-4"
            >
                <Link href={toUrl(home())} class="flex items-center gap-2.5">
                    <span
                        class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-5" />
                    </span>
                    <AppWordmark class="h-3.5" />
                </Link>
                <div class="flex items-center gap-1 sm:gap-2">
                    {#if auth.user}
                        <Link
                            href={toUrl(dashboardUrl)}
                            class={cn(buttonVariants(), 'h-10 px-4')}
                        >
                            Dashboard
                        </Link>
                    {:else}
                        <Link
                            href={toUrl(login())}
                            class={cn(
                                buttonVariants({ variant: 'ghost' }),
                                'h-10 px-3 sm:px-4',
                            )}
                        >
                            Log in
                        </Link>
                        <Link
                            href={toUrl(register())}
                            class={cn(buttonVariants(), 'h-10 px-4')}
                        >
                            <span class="sm:hidden">Sign up</span>
                            <span class="hidden sm:inline"
                                >Sign up for the beta</span
                            >
                        </Link>
                    {/if}
                </div>
            </nav>
        </header>

        <main class="relative">
            <!-- Hero -->
            <section
                class="mx-auto grid max-w-7xl grid-cols-7 sm:px-6 lg:px-0"
                aria-labelledby="hero-title"
            >
                {#each days as day (day)}
                    <span
                        aria-hidden="true"
                        class="px-2 pt-3 text-[11px] font-medium tracking-[0.08em] text-muted-foreground uppercase sm:px-4"
                    >
                        <span class="sm:hidden">{day.charAt(0)}</span>
                        <span class="hidden sm:inline">{day}</span>
                    </span>
                {/each}

                <div
                    class="col-span-7 px-4 pt-20 pb-24 sm:pt-28 lg:col-span-5 lg:pt-28 lg:pb-24"
                >
                    <h1
                        id="hero-title"
                        class="text-[clamp(3rem,7.4vw,5.75rem)] leading-[0.98] font-semibold tracking-[-0.035em] text-balance"
                    >
                        Plan a week you can finish.
                    </h1>
                    <p
                        class="mt-6 max-w-[34rem] text-lg leading-relaxed text-pretty text-muted-foreground sm:text-xl"
                    >
                        Itera is a personal Agile planner, now in open beta.
                        Put your work into Areas, Projects, and Tasks, give
                        each task story points, and plan a Monday-to-Sunday
                        sprint that fits.
                    </p>
                    <div class="mt-9 flex flex-wrap gap-3">
                        {#if auth.user}
                            <Link href={toUrl(dashboardUrl)} class={primary}>
                                Open your dashboard
                            </Link>
                        {:else}
                            <Link href={toUrl(register())} class={primary}>
                                Sign up for the beta
                            </Link>
                            <Link href={toUrl(login())} class={secondary}>
                                Log in
                            </Link>
                        {/if}
                    </div>
                </div>

                <!-- The weekly ritual on its days: plan Monday, work, review Sunday. -->
                <ol
                    class="col-span-7 grid grid-cols-1 pb-16 sm:grid-cols-7 sm:pb-20"
                    aria-label="How a week works in Itera"
                >
                    <li class="ritual sm:col-span-1">
                        <span class="rule rule-plan bg-primary"></span>
                        <span class="font-semibold"
                            >Plan <span class="font-normal text-muted-foreground"
                                >· Monday</span
                            ></span
                        >
                        <span class="text-muted-foreground"
                            >Pick the tasks and set a point target.</span
                        >
                    </li>
                    <li class="ritual sm:col-span-5">
                        <span class="rule rule-work bg-foreground"></span>
                        <span class="font-semibold"
                            >Work <span class="font-normal text-muted-foreground"
                                >· Tuesday to Saturday</span
                            ></span
                        >
                        <span class="text-muted-foreground"
                            >Move tasks across To Do, In Progress, and Done.</span
                        >
                    </li>
                    <li class="ritual sm:col-span-1">
                        <span class="rule rule-review bg-primary"></span>
                        <span class="font-semibold"
                            >Review <span
                                class="font-normal text-muted-foreground"
                                >· Sunday</span
                            ></span
                        >
                        <span class="text-muted-foreground"
                            >Compare done points with the plan.</span
                        >
                    </li>
                </ol>
            </section>

            <!-- How it works -->
            <section class="border-t border-border" aria-labelledby="how-title">
                <div class="mx-auto grid max-w-7xl grid-cols-7 sm:px-6 lg:px-0">
                    <h2
                        id="how-title"
                        class="col-span-7 px-4 pt-20 text-3xl leading-tight font-semibold tracking-[-0.02em] text-balance sm:text-4xl lg:col-span-2 lg:pt-28"
                    >
                        Order it, size it, fit it in a week.
                    </h2>

                    <div
                        class="col-span-7 pt-10 pb-20 lg:col-span-5 lg:pt-28 lg:pb-28"
                    >
                        <article class="step">
                            <h3 class="step-title">
                                Put everything in one stack
                            </h3>
                            <p class="step-body">
                                Group your work into Areas, then Projects,
                                Tasks, and optional Subtasks. Rank Areas and
                                Projects in the order that matters to you.
                            </p>
                            <ul class="example" aria-label="Example stack">
                                <li>
                                    <span class="level">Area</span>Biology 201
                                </li>
                                <li style="--indent: 1.25rem">
                                    <span class="level">Project</span>Lab
                                    report
                                </li>
                                <li style="--indent: 2.5rem">
                                    <span class="level">Task</span>Write the
                                    method section
                                    <span
                                        class="whitespace-nowrap text-muted-foreground tabular-nums"
                                        >3 pts</span
                                    >
                                </li>
                                <li style="--indent: 3.75rem">
                                    <span class="level">Subtask</span>Check
                                    the sample sizes
                                </li>
                            </ul>
                        </article>

                        <article class="step">
                            <h3 class="step-title">Size tasks in story points</h3>
                            <p class="step-body">
                                Story points measure effort, not hours. Points
                                belong to the task. Subtasks show how far along
                                you are, but they do not split the points.
                            </p>
                        </article>

                        <article class="step">
                            <h3 class="step-title">
                                Plan a Monday-to-Sunday sprint
                            </h3>
                            <p class="step-body">
                                Give the sprint a goal and a point target. Work
                                through the week on a Kanban board. On Sunday,
                                review what you finished, write a short
                                retrospective, and move unfinished work to the
                                next sprint.
                            </p>
                            <dl class="example" aria-label="Example sprint">
                                <div>
                                    <dt class="level">Goal</dt>
                                    <dd>Hand in the lab report</dd>
                                </div>
                                <div>
                                    <dt class="level">Target</dt>
                                    <dd class="tabular-nums">15 points</dd>
                                </div>
                                <div>
                                    <dt class="level">Board</dt>
                                    <dd>To Do, In Progress, Done</dd>
                                </div>
                            </dl>
                        </article>
                    </div>
                </div>
            </section>

            <!-- Who it is for -->
            <section class="border-t border-border" aria-labelledby="who-title">
                <div class="mx-auto grid max-w-7xl grid-cols-7 sm:px-6 lg:px-0">
                    <div class="col-span-7 px-4 py-20 lg:col-span-5 lg:py-28">
                        <h2
                            id="who-title"
                            class="text-3xl leading-tight font-semibold tracking-[-0.02em] text-balance sm:text-4xl"
                        >
                            Made for college students. Useful for work and
                            everyday life too.
                        </h2>
                        <p
                            class="mt-5 max-w-[38rem] text-lg leading-relaxed text-muted-foreground"
                        >
                            Classes, a part-time job, and home all compete for
                            the same week. Itera keeps them in one stack, so
                            you plan the week around all of it, without the
                            setup of a tool made for teams.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Beta -->
            <section class="border-t border-border" aria-labelledby="beta-title">
                <div class="mx-auto grid max-w-7xl grid-cols-7 sm:px-6 lg:px-0">
                    <div class="col-span-7 px-4 pt-20 lg:col-span-2 lg:py-28">
                        <h2
                            id="beta-title"
                            class="text-3xl leading-tight font-semibold tracking-[-0.02em] text-balance sm:text-4xl"
                        >
                            Itera is in open beta.
                        </h2>
                        <p
                            class="mt-5 text-lg leading-relaxed text-muted-foreground"
                        >
                            Sign up, plan a real week, and tell us what breaks.
                        </p>
                        {#if !auth.user}
                            <Link
                                href={toUrl(register())}
                                class={cn(primary, 'mt-8')}
                            >
                                Sign up for the beta
                            </Link>
                        {/if}
                    </div>
                    <ul
                        class="col-span-7 pt-10 pb-20 text-[15px] lg:col-span-5 lg:pt-28 lg:pb-28"
                    >
                        <li class="beta-item">Anyone can sign up and test it.</li>
                        <li class="beta-item">
                            We can reset data during the beta. Do not keep your
                            only copy of a plan in Itera.
                        </li>
                        <li class="beta-item">
                            Features can change from one version to the next.
                        </li>
                        <li class="beta-item">
                            Found a problem?&nbsp;<a
                                href={issuesUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-medium text-primary underline underline-offset-4"
                                >Open an issue on GitHub</a
                            >.
                        </li>
                    </ul>
                </div>
            </section>
        </main>

        <footer class="relative border-t border-border">
            <div
                class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-8 text-sm text-muted-foreground sm:px-10 lg:px-4"
            >
                <AppWordmark class="h-3 text-foreground" />
                <span>Open beta. Data can be reset.</span>
                <a
                    href={repositoryUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    class="underline-offset-4 hover:text-foreground hover:underline"
                    >Source on GitHub</a
                >
            </div>
        </footer>
    </div>
</div>

<style>
    .ritual {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        padding: 1.25rem 1rem;
        font-size: 0.875rem;
        line-height: 1.45;
    }

    @media (min-width: 40rem) {
        .ritual {
            padding-bottom: 0;
        }
    }

    .rule {
        position: absolute;
        inset: 0 0 auto 0;
        height: 2px;
        transform-origin: left;
        animation: draw 900ms cubic-bezier(0.16, 1, 0.3, 1) both;
    }

    .rule-work {
        height: 1px;
        animation-delay: 140ms;
    }

    .rule-review {
        animation-delay: 280ms;
    }

    @keyframes draw {
        from {
            transform: scaleX(0);
        }
        to {
            transform: scaleX(1);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .rule {
            animation: none;
        }
    }

    .step {
        display: grid;
        gap: 0.75rem;
        border-top: 1px solid var(--border);
        padding: 2rem 1rem 2.5rem;
    }

    .step:first-child {
        border-top: 0;
        padding-top: 0;
    }

    .step-title {
        font-size: 1.25rem;
        font-weight: 600;
        letter-spacing: -0.01em;
    }

    .step-body {
        max-width: 38rem;
        color: var(--muted-foreground);
        line-height: 1.6;
    }

    /* Example rows: hairline-ruled, edge to edge on the column lines. */
    .example {
        margin: 0.75rem -1rem 0;
        font-size: 15px;
    }

    /* A step that ends with an example closes on the next step's rule. */
    .step:has(> .example) {
        padding-bottom: 0;
    }

    .step:last-child {
        border-bottom: 1px solid var(--border);
    }

    .example > li,
    .example > div {
        display: flex;
        gap: 0.75rem;
        border-top: 1px solid var(--border);
        padding: 0.65rem 1rem 0.65rem calc(1rem + var(--indent, 0rem));
    }

    .level {
        width: 4.5rem;
        flex-shrink: 0;
        color: var(--muted-foreground);
        font-size: 13px;
        line-height: 1.6rem;
    }

    .beta-item {
        border-top: 1px solid var(--border);
        padding: 0.9rem 1rem;
        line-height: 1.5;
    }

    .beta-item:last-child {
        border-bottom: 1px solid var(--border);
    }
</style>
