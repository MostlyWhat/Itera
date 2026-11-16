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
    import { Link, page, router } from '@inertiajs/svelte';
    import ArrowLeft from 'carbon-icons-svelte/lib/ArrowLeft.svelte';
    import ArrowRight from 'carbon-icons-svelte/lib/ArrowRight.svelte';
    import Calendar from 'carbon-icons-svelte/lib/Calendar.svelte';
    import Edit from 'carbon-icons-svelte/lib/Edit.svelte';
    import WarningAltFilled from 'carbon-icons-svelte/lib/WarningAltFilled.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import PendingInvitationsModal from '@/components/PendingInvitationsModal.svelte';
    import PointsMeter from '@/components/sprints/PointsMeter.svelte';
    import WeekStrip from '@/components/sprints/WeekStrip.svelte';
    import { Button, buttonVariants } from '@/components/ui/button';
    import { formatShortDate, formatWeekRange } from '@/lib/dates';
    import { cn } from '@/lib/utils';
    import { update as updateStatus } from '@/routes/tasks/status';
    import { index as sprintsIndex, show as showSprint } from '@/routes/sprints';
    import type { DashboardInvitation } from '@/types';

    type Status = 'todo' | 'in_progress' | 'done';

    type Card = {
        taskId: number;
        title: string;
        points: number | null;
        status: Status;
        dueDate: string | null;
        isOverdue: boolean;
        projectName: string;
        areaName: string;
    };

    type Board = {
        sprint: {
            id: number;
            weekStart: string;
            weekEnd: string;
            goal: string | null;
            targetPoints: number | null;
            editable: boolean;
        };
        cards: Card[];
        columns: { status: Status; label: string }[];
    };

    let {
        pendingInvitations = [],
        board = null,
    }: {
        pendingInvitations?: DashboardInvitation[];
        board?: Board | null;
    } = $props();

    const team = $derived((page.props.currentTeam as Team).slug);

    // The cards on screen. A move changes them at once, then saves. The next
    // page props from the server replace them, so a failed save shows the
    // saved state again.
    let cards = $state<Card[]>([]);

    $effect(() => {
        cards = board ? board.cards.map((card) => ({ ...card })) : [];
    });

    let dragging = $state<number | null>(null);
    let dropTarget = $state<Status | null>(null);
    let announcement = $state('');

    const order: Status[] = ['todo', 'in_progress', 'done'];

    const columnCards = (status: Status) =>
        cards.filter((card) => card.status === status);
    const columnPoints = (status: Status) =>
        columnCards(status).reduce((sum, card) => sum + (card.points ?? 0), 0);
    const totalPoints = $derived(
        cards.reduce((sum, card) => sum + (card.points ?? 0), 0),
    );
    const donePoints = $derived(columnPoints('done'));

    const labelOf = (status: Status) =>
        board?.columns.find((column) => column.status === status)?.label ?? status;

    const move = (card: Card, status: Status) => {
        if (card.status === status) {
            return;
        }

        cards = cards.map((item) =>
            item.taskId === card.taskId ? { ...item, status } : item,
        );
        announcement = `${card.title} moved to ${labelOf(status)}.`;

        router.patch(
            updateStatus({ current_team: team, task: card.taskId }).url,
            { status },
            { preserveScroll: true, preserveState: true },
        );
    };

    const step = (card: Card, direction: -1 | 1) => {
        const next = order[order.indexOf(card.status) + direction];

        if (next) {
            move(card, next);
        }
    };

    const onDrop = (status: Status) => {
        const card = cards.find((item) => item.taskId === dragging);

        if (card) {
            move(card, status);
        }

        dragging = null;
        dropTarget = null;
    };
</script>

<AppHead title="Dashboard" />

{#if pendingInvitations.length > 0}
    <PendingInvitationsModal invitations={pendingInvitations} />
{/if}

<div class="flex flex-1 flex-col gap-6 px-4 py-8 sm:py-10">
    {#if board}
        <header class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
            <div class="min-w-0 space-y-1.5">
                <h1 class="type-title">This week</h1>
                <p class="text-sm text-muted-foreground tabular-nums">
                    {formatWeekRange(board.sprint.weekStart, board.sprint.weekEnd)}
                    {#if board.sprint.goal}
                        · <span class="text-foreground">{board.sprint.goal}</span>
                    {/if}
                </p>
            </div>
            <div class="flex w-full flex-wrap items-end gap-4 sm:w-auto">
                <div class="w-full sm:w-64">
                    <p class="mb-1 text-xs font-medium text-muted-foreground">
                        Done this week
                    </p>
                    <PointsMeter committed={donePoints} target={totalPoints === 0 ? null : totalPoints} />
                </div>
                <Link
                    href={showSprint({ current_team: team, sprint: board.sprint.id })}
                    class={cn(buttonVariants({ variant: 'outline' }), 'gap-1.5')}
                >
                    <Edit class="size-4" />
                    Plan sprint
                </Link>
            </div>
        </header>

        <WeekStrip weekStart={board.sprint.weekStart} />

        <p class="sr-only" aria-live="polite">{announcement}</p>

        {#if cards.length === 0}
            <div
                class="rounded-lg border border-dashed border-border px-6 py-10 text-center"
            >
                <h2 class="type-section">No tasks in this sprint</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Add tasks to the sprint, then move them across the board as
                    you work.
                </p>
                <Link
                    href={showSprint({ current_team: team, sprint: board.sprint.id })}
                    class={cn(buttonVariants(), 'mt-4')}
                >
                    Add tasks
                </Link>
            </div>
        {:else}
            <div class="grid gap-4 md:grid-cols-3 md:items-start">
                {#each board.columns as column, columnIndex (column.status)}
                    {@const items = columnCards(column.status)}
                    <section
                        aria-labelledby={`column-${column.status}`}
                        class={cn(
                            'flex min-h-48 flex-col rounded-lg border bg-muted/30 transition-colors',
                            dropTarget === column.status
                                ? 'border-primary bg-accent/60'
                                : 'border-border',
                        )}
                        ondragover={(event: DragEvent) => {
                            event.preventDefault();
                            dropTarget = column.status;
                        }}
                        ondragleave={(event: DragEvent) => {
                            if (!(event.currentTarget as HTMLElement).contains(event.relatedTarget as Node)) {
                                dropTarget = null;
                            }
                        }}
                        ondrop={(event: DragEvent) => {
                            event.preventDefault();
                            onDrop(column.status);
                        }}
                    >
                        <header
                            class="flex items-baseline justify-between gap-2 border-b border-border px-3 py-2.5"
                        >
                            <h2 id={`column-${column.status}`} class="text-sm font-semibold">
                                {column.label}
                                <span class="font-normal text-muted-foreground tabular-nums"
                                    >{items.length}</span
                                >
                            </h2>
                            <span class="text-xs text-muted-foreground tabular-nums"
                                >{columnPoints(column.status)} pts</span
                            >
                        </header>

                        <ul class="flex flex-col gap-2 p-2">
                            {#each items as card (card.taskId)}
                                <li
                                    draggable="true"
                                    ondragstart={(event: DragEvent) => {
                                        dragging = card.taskId;
                                        event.dataTransfer?.setData('text/plain', String(card.taskId));
                                    }}
                                    ondragend={() => {
                                        dragging = null;
                                        dropTarget = null;
                                    }}
                                    class={cn(
                                        'group/card flex cursor-grab flex-col gap-2 rounded-md border border-border bg-card p-3 shadow-xs transition-[opacity,box-shadow] hover:shadow-sm active:cursor-grabbing',
                                        dragging === card.taskId && 'opacity-40',
                                    )}
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <span
                                            class={cn(
                                                'text-sm leading-snug',
                                                card.status === 'done' &&
                                                    'text-muted-foreground line-through decoration-muted-foreground/60',
                                            )}>{card.title}</span
                                        >
                                        <span
                                            class="shrink-0 rounded-sm border border-border px-1.5 text-[11px] text-muted-foreground tabular-nums"
                                            >{card.points === null ? '–' : card.points}</span
                                        >
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="flex min-w-0 items-center gap-2 text-xs text-muted-foreground">
                                            <span class="truncate">{card.projectName}</span>
                                            {#if card.dueDate && card.status !== 'done'}
                                                <span
                                                    class={cn(
                                                        'flex shrink-0 items-center gap-1 tabular-nums',
                                                        card.isOverdue && 'font-medium text-destructive',
                                                    )}
                                                >
                                                    {#if card.isOverdue}
                                                        <WarningAltFilled class="size-3.5" />
                                                        <span class="sr-only">Overdue,</span>
                                                    {:else}
                                                        <Calendar class="size-3.5" />
                                                    {/if}
                                                    {formatShortDate(card.dueDate)}
                                                </span>
                                            {/if}
                                        </span>
                                        <!-- Keyboard and touch alternative to drag and drop. -->
                                        <span class="flex shrink-0 items-center gap-0.5">
                                            {#if columnIndex > 0}
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    class="size-7 text-muted-foreground hover:text-foreground"
                                                    aria-label={`Move ${card.title} to ${labelOf(order[columnIndex - 1])}`}
                                                    onclick={() => step(card, -1)}
                                                >
                                                    <ArrowLeft class="size-4" />
                                                </Button>
                                            {/if}
                                            {#if columnIndex < order.length - 1}
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    class="size-7 text-muted-foreground hover:text-foreground"
                                                    aria-label={`Move ${card.title} to ${labelOf(order[columnIndex + 1])}`}
                                                    onclick={() => step(card, 1)}
                                                >
                                                    <ArrowRight class="size-4" />
                                                </Button>
                                            {/if}
                                        </span>
                                    </div>
                                </li>
                            {:else}
                                <li
                                    class="rounded-md border border-dashed border-border px-3 py-6 text-center text-xs text-muted-foreground"
                                >
                                    Drop a task here
                                </li>
                            {/each}
                        </ul>
                    </section>
                {/each}
            </div>
        {/if}
    {:else}
        <header class="space-y-1">
            <h1 class="type-title">This week</h1>
            <p class="text-sm text-muted-foreground">
                Your sprint board shows here when this week has a sprint.
            </p>
        </header>
        <div
            class="flex flex-col items-start gap-4 rounded-lg border border-dashed border-border px-6 py-10 sm:items-center sm:text-center"
        >
            <span
                class="flex size-10 items-center justify-center rounded-md bg-accent text-accent-foreground"
                aria-hidden="true"
            >
                <Calendar class="size-5" />
            </span>
            <div class="space-y-1">
                <h2 class="type-section">No sprint this week</h2>
                <p class="max-w-sm text-sm text-muted-foreground">
                    Plan a sprint with a goal and the tasks that fit. Then
                    track them here in To Do, In Progress, and Done.
                </p>
            </div>
            <Link href={sprintsIndex(team)} class={buttonVariants()}>
                Plan this week
            </Link>
        </div>
    {/if}
</div>
