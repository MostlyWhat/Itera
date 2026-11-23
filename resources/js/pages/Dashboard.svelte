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
    import { flip } from 'svelte/animate';
    import { cubicOut } from 'svelte/easing';
    import { prefersReducedMotion } from 'svelte/motion';
    import { crossfade, fade } from 'svelte/transition';
    import ArrowLeft from 'carbon-icons-svelte/lib/ArrowLeft.svelte';
    import ArrowRight from 'carbon-icons-svelte/lib/ArrowRight.svelte';
    import Calendar from 'carbon-icons-svelte/lib/Calendar.svelte';
    import CheckmarkFilled from 'carbon-icons-svelte/lib/CheckmarkFilled.svelte';
    import Edit from 'carbon-icons-svelte/lib/Edit.svelte';
    import WarningAltFilled from 'carbon-icons-svelte/lib/WarningAltFilled.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Repeat from 'carbon-icons-svelte/lib/Repeat.svelte';
    import FormDialog from '@/components/FormDialog.svelte';
    import SubtaskList from '@/components/tasks/SubtaskList.svelte';
    import type { SubtaskItem } from '@/components/tasks/SubtaskList.svelte';
    import SubtaskProgress from '@/components/tasks/SubtaskProgress.svelte';
    import type { Progress } from '@/components/tasks/SubtaskProgress.svelte';
    import PendingInvitationsModal from '@/components/PendingInvitationsModal.svelte';
    import PointsMeter from '@/components/sprints/PointsMeter.svelte';
    import { Button, buttonVariants } from '@/components/ui/button';
    import { formatShortDate, formatWeekRange } from '@/lib/dates';
    import { cn } from '@/lib/utils';
    import { update as updateStatus } from '@/routes/tasks/status';
    import { index as projectsIndex, show as showProject } from '@/routes/projects';
    import { index as sprintsIndex, show as showSprint } from '@/routes/sprints';
    import type { DashboardInvitation } from '@/types';

    type Status = 'todo' | 'in_progress' | 'done';

    type Card = {
        taskId: number;
        title: string;
        description: string | null;
        points: number | null;
        status: Status;
        dueDate: string | null;
        isOverdue: boolean;
        projectName: string;
        projectId: number;
        areaName: string;
        isWeekly: boolean;
        progress: Progress | null;
        subtasks: SubtaskItem[];
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
        setup = null,
    }: {
        pendingInvitations?: DashboardInvitation[];
        board?: Board | null;
        setup?: { hasProject: boolean; hasTask: boolean } | null;
    } = $props();

    const team = $derived((page.props.currentTeam as Team).slug);

    // The cards on screen. A move changes them at once, then saves. The next
    // page props from the server replace them, so a failed save shows the
    // saved state again.
    let cards = $state<Card[]>([]);

    $effect(() => {
        cards = board ? board.cards.map((card) => ({ ...card })) : [];
    });

    // A moved card travels from its old column to the new one, so the eye
    // can follow it. Under reduced motion it fades out and in instead.
    const [crossSend, crossReceive] = crossfade({
        duration: 200,
        easing: cubicOut,
        fallback: (node) => fade(node, { duration: 150 }),
    });
    const send = (node: Element, params: { key: number }) =>
        prefersReducedMotion.current
            ? () => fade(node, { duration: 150 })
            : crossSend(node, params);
    const receive = (node: Element, params: { key: number }) =>
        prefersReducedMotion.current
            ? () => fade(node, { duration: 150 })
            : crossReceive(node, params);
    const flipDuration = () => (prefersReducedMotion.current ? 0 : 200);

    // A click on a card shows the whole task, description included.
    let detailId = $state<number | null>(null);
    let detailOpen = $state(false);
    const detail = $derived(cards.find((card) => card.taskId === detailId) ?? null);
    const openDetail = (card: Card) => {
        detailId = card.taskId;
        detailOpen = true;
    };

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
                    Edit sprint
                </Link>
            </div>
        </header>

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
                            'flex flex-col rounded-xl border bg-muted/30 transition-colors md:min-h-48',
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
                            class="flex items-baseline justify-between gap-2 border-b border-border px-4.5 py-2.5"
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

                        <ul class="flex flex-col gap-1.5 p-1.5">
                            {#each items as card (card.taskId)}
                                <!-- The title button is the keyboard path to the details. -->
                                <!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_noninteractive_element_interactions -->
                                <li
                                    in:receive={{ key: card.taskId }}
                                    out:send={{ key: card.taskId }}
                                    animate:flip={{ duration: flipDuration }}
                                    draggable="true"
                                    ondragstart={(event: DragEvent) => {
                                        dragging = card.taskId;
                                        event.dataTransfer?.setData('text/plain', String(card.taskId));
                                    }}
                                    onclick={(event: MouseEvent) => {
                                        // The arrows and the title button do their own work.
                                        if (!(event.target as HTMLElement).closest('button')) {
                                            openDetail(card);
                                        }
                                    }}
                                    ondragend={() => {
                                        dragging = null;
                                        dropTarget = null;
                                    }}
                                    class={cn(
                                        'group/card relative flex cursor-grab flex-col gap-2 rounded-md border border-border bg-card p-3 shadow-xs transition-[opacity,box-shadow] has-[button:focus-visible]:ring-3 has-[button:focus-visible]:ring-ring/50 hover:shadow-sm active:cursor-grabbing',
                                        dragging === card.taskId && 'opacity-40',
                                    )}
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <button
                                            type="button"
                                            onclick={() => openDetail(card)}
                                            class={cn(
                                                'text-start text-sm leading-snug outline-none',
                                                card.status === 'done' &&
                                                    'text-muted-foreground line-through decoration-muted-foreground/60',
                                            )}
                                        >
                                            {card.title}
                                            <span class="sr-only">, show details</span>
                                        </button>
                                        <span
                                            class="shrink-0 rounded-sm border border-border px-1.5 text-xs text-muted-foreground tabular-nums"
                                            >{card.points === null ? '–' : card.points}</span
                                        >
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                                            <span class="wrap-break-word">{card.projectName}</span>
                                            {#if card.isWeekly}
                                                <Repeat class="size-3.5 shrink-0" aria-label="Weekly task" />
                                            {/if}
                                            {#if card.progress}
                                                <SubtaskProgress progress={card.progress} />
                                            {/if}
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
                                        <span
                                            class="relative z-10 flex shrink-0 items-center gap-0.5 motion-safe:transition-opacity [@media(hover:hover)]:opacity-0 group-hover/card:opacity-100 group-focus-within/card:opacity-100"
                                        >
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
                                    No tasks
                                </li>
                            {/each}
                        </ul>
                    </section>
                {/each}
            </div>
        {/if}
    {:else}
        {@const steps = [
            {
                title: 'Create a project',
                text: 'Group your work by class or job, then add a project such as a lab report.',
                done: setup?.hasProject ?? false,
                href: projectsIndex(team),
                action: 'Open Projects',
            },
            {
                title: 'Add tasks with story points',
                text: 'Write down what the project needs. Points show how big each task is.',
                done: setup?.hasTask ?? false,
                href: projectsIndex(team),
                action: 'Add tasks',
            },
            {
                title: 'Plan this week',
                text: 'Choose the tasks that fit this week. They show here as a board.',
                done: false,
                href: sprintsIndex(team),
                action: 'Plan this week',
            },
        ]}
        {@const next = steps.findIndex((step) => !step.done)}
        <header class="space-y-1">
            <h1 class="type-title">This week</h1>
            <p class="text-sm text-muted-foreground">
                No sprint yet. Three steps get you to your board.
            </p>
        </header>
        <ol class="max-w-xl overflow-hidden rounded-lg border border-border bg-card">
            {#each steps as step, index (step.title)}
                <li
                    class={cn(
                        'flex gap-4 border-t border-border px-4 py-4 first:border-t-0',
                        index === next && 'bg-accent/40',
                    )}
                >
                    <span
                        class={cn(
                            'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-medium tabular-nums',
                            step.done
                                ? 'text-primary'
                                : index === next
                                  ? 'bg-primary text-primary-foreground'
                                  : 'border border-border text-muted-foreground',
                        )}
                    >
                        {#if step.done}
                            <CheckmarkFilled class="size-6" />
                            <span class="sr-only">Done:</span>
                        {:else}
                            {index + 1}
                        {/if}
                    </span>
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <h2
                            class={cn(
                                'text-sm font-medium',
                                step.done && 'text-muted-foreground line-through decoration-muted-foreground/60',
                            )}
                        >
                            {step.title}
                        </h2>
                        {#if index === next}
                            <p class="text-sm text-muted-foreground">{step.text}</p>
                            <Link href={step.href} class={cn(buttonVariants({ size: 'sm' }), 'mt-2 w-fit')}>
                                {step.action}
                            </Link>
                        {/if}
                    </div>
                </li>
            {/each}
        </ol>
    {/if}
</div>

<FormDialog bind:open={detailOpen} title={detail?.title ?? 'Task'} size="lg">
    {#if detail}
        <dl class="grid grid-cols-[7rem_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
            <dt class="text-muted-foreground">Status</dt>
            <dd>{labelOf(detail.status)}</dd>
            <dt class="text-muted-foreground">Project</dt>
            <dd class="wrap-break-word">{detail.projectName} · {detail.areaName}</dd>
            <dt class="text-muted-foreground">Story points</dt>
            <dd class="tabular-nums">{detail.points ?? 'None'}</dd>
            <dt class="text-muted-foreground">Deadline</dt>
            <dd class={cn('tabular-nums', detail.isOverdue && 'font-medium text-destructive')}>
                {#if detail.dueDate}
                    {formatShortDate(detail.dueDate)}{detail.isOverdue ? ', overdue' : ''}
                {:else}
                    None
                {/if}
            </dd>
        </dl>
        <div class="flex flex-col gap-2">
            <h3 class="text-sm font-medium">Description</h3>
            {#if detail.description}
                <p class="max-h-72 overflow-y-auto text-sm whitespace-pre-line wrap-break-word text-muted-foreground">{detail.description}</p>
            {:else}
                <p class="text-sm text-muted-foreground">No description.</p>
            {/if}
        </div>
        <SubtaskList
            {team}
            taskId={detail.taskId}
            subtasks={detail.subtasks}
            progress={detail.progress}
        />
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button variant="ghost" onclick={() => (detailOpen = false)}>Close</Button>
            <Link
                href={showProject({ current_team: team, project: detail.projectId })}
                class={buttonVariants({ variant: 'outline' })}
            >
                Open the project
            </Link>
        </div>
    {/if}
</FormDialog>
