<script module lang="ts">
    import { dashboard } from '@/routes';
    import type { Team } from '@/types';

    export const layout = (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
        ],
    });
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import PendingInvitationsModal from '@/components/PendingInvitationsModal.svelte';
    import { buttonVariants } from '@/components/ui/button';
    import { cn, toUrl } from '@/lib/utils';
    import { edit as editProfile } from '@/routes/profile';
    import { index as teamsIndex } from '@/routes/teams';
    import type { DashboardInvitation } from '@/types';

    let {
        pendingInvitations = [],
    }: {
        pendingInvitations?: DashboardInvitation[];
    } = $props();

    // The current Monday-to-Sunday week, from the browser's local date.
    const today = new Date();
    const mondayOffset = (today.getDay() + 6) % 7;
    const monday = new Date(today);
    monday.setDate(today.getDate() - mondayOffset);

    const week = Array.from({ length: 7 }, (_, index) => {
        const date = new Date(monday);
        date.setDate(monday.getDate() + index);

        return {
            key: index,
            day: date.toLocaleDateString(undefined, { weekday: 'short' }),
            date: date.getDate(),
            isToday: index === mondayOffset,
        };
    });

    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);

    const shortDate = (date: Date) =>
        date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    const range = `${shortDate(monday)} to ${shortDate(sunday)}`;
</script>

<AppHead title="Dashboard" />

{#if pendingInvitations.length > 0}
    <PendingInvitationsModal invitations={pendingInvitations} />
{/if}

<div class="flex flex-1 flex-col px-4 py-8 sm:py-10">
    <div class="mb-6 flex flex-wrap items-baseline justify-between gap-2">
        <h1 class="type-title">This week</h1>
        <p class="text-sm text-muted-foreground tabular-nums">{range}</p>
    </div>

    <!-- The week grid: seven day columns, today marked in cobalt. -->
    <div
        class="relative grid flex-1 grid-cols-1 border-y border-border sm:min-h-[28rem] sm:grid-cols-7 sm:border-x"
    >
        {#each week as day (day.key)}
            <div
                class={cn(
                    'relative flex items-baseline gap-2 border-b border-border px-3 py-3 sm:block sm:border-r sm:border-b-0 sm:last:border-r-0',
                )}
                aria-current={day.isToday ? 'date' : undefined}
            >
                {#if day.isToday}
                    <span
                        aria-hidden="true"
                        class="absolute inset-x-0 top-0 h-0.5 bg-primary"
                    ></span>
                {/if}
                <span
                    class={cn(
                        'text-[11px] font-medium tracking-[0.08em] uppercase',
                        day.isToday ? 'text-primary' : 'text-muted-foreground',
                    )}
                >
                    {day.day}
                </span>
                <span
                    class={cn(
                        'text-sm tabular-nums sm:mt-1 sm:block sm:text-lg sm:font-semibold',
                        day.isToday ? 'text-foreground' : 'text-muted-foreground',
                    )}
                >
                    {day.date}
                </span>
                {#if day.isToday}
                    <span class="ml-auto text-xs text-primary sm:ml-0"
                        >Today</span
                    >
                {/if}
            </div>
        {/each}

        <!-- Empty state across the week. -->
        <div
            class="col-span-full flex items-center px-3 py-10 sm:pointer-events-none sm:absolute sm:inset-0 sm:justify-center sm:px-6"
        >
            <div
                class="max-w-md bg-background sm:pointer-events-auto sm:px-6 sm:py-5 sm:text-center"
            >
                <h2 class="type-section">No sprint yet</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Itera is in open beta. Areas, Projects, Tasks, and sprint
                    planning come in the next beta versions. For now, you can
                    set up your profile and your team.
                </p>
                <div class="mt-4 flex flex-wrap gap-2 sm:justify-center">
                    <Link
                        href={toUrl(editProfile())}
                        class={buttonVariants({ variant: 'outline' })}
                    >
                        Profile settings
                    </Link>
                    <Link
                        href={toUrl(teamsIndex())}
                        class={buttonVariants({ variant: 'ghost' })}
                    >
                        Teams
                    </Link>
                </div>
            </div>
        </div>
    </div>
</div>
