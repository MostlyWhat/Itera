<script module lang="ts">
    import { index as projectsIndex, show } from '@/routes/projects';
    import type { Team } from '@/types';

    export const layout = (props: {
        currentTeam?: Team | null;
        project: { id: number; name: string };
    }) => ({
        breadcrumbs: [
            {
                title: 'Projects',
                href: projectsIndex(props.currentTeam?.slug ?? ''),
            },
            {
                title: props.project.name,
                href: show({
                    current_team: props.currentTeam?.slug ?? '',
                    project: props.project.id,
                }),
            },
        ],
    });
</script>

<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import { SvelteSet } from 'svelte/reactivity';
    import Add from 'carbon-icons-svelte/lib/Add.svelte';
    import Calendar from 'carbon-icons-svelte/lib/Calendar.svelte';
    import Checkmark from 'carbon-icons-svelte/lib/Checkmark.svelte';
    import ChevronRight from 'carbon-icons-svelte/lib/ChevronRight.svelte';
    import Edit from 'carbon-icons-svelte/lib/Edit.svelte';
    import OverflowMenuVertical from 'carbon-icons-svelte/lib/OverflowMenuVertical.svelte';
    import TaskIcon from 'carbon-icons-svelte/lib/Task.svelte';
    import TrashCan from 'carbon-icons-svelte/lib/TrashCan.svelte';
    import WarningAltFilled from 'carbon-icons-svelte/lib/WarningAltFilled.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ProjectForm from '@/components/projects/ProjectForm.svelte';
    import ProjectSelector, {
        type NavigationArea,
    } from '@/components/tasks/ProjectSelector.svelte';
    import TaskForm from '@/components/tasks/TaskForm.svelte';
    import {
        AlertDialog,
        AlertDialogAction,
        AlertDialogCancel,
        AlertDialogContent,
        AlertDialogDescription,
        AlertDialogFooter,
        AlertDialogHeader,
        AlertDialogTitle,
    } from '@/components/ui/alert-dialog';
    import { Button } from '@/components/ui/button';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import { cn } from '@/lib/utils';
    import { index as areasIndex } from '@/routes/areas';
    import { update as updateProject } from '@/routes/projects';
    import {
        destroy as destroyTask,
        store as storeTask,
        update as updateTask,
    } from '@/routes/tasks';
    import { update as updateStatus } from '@/routes/tasks/status';

    type TaskItem = {
        id: number;
        title: string;
        description: string | null;
        status: 'todo' | 'in_progress' | 'done';
        statusLabel: string;
        storyPoints: number | null;
        dueDate: string | null;
        isOverdue: boolean;
    };

    let {
        navigation,
        project,
        tasks,
        storyPointOptions,
        statusOptions,
    }: {
        navigation: NavigationArea[];
        project: {
            id: number;
            name: string;
            description: string | null;
            areaName: string;
            archived: boolean;
        };
        tasks: TaskItem[];
        storyPointOptions: number[];
        statusOptions: { value: string; label: string }[];
    } = $props();

    const team = $derived((page.props.currentTeam as Team).slug);

    let editing = $state<number | null>(null);
    let editingProject = $state(false);

    // Descriptions show two lines. A click on the text shows all of it.
    const expanded = new SvelteSet<number>();
    const toggleDescription = (id: number) => {
        if (expanded.has(id)) {
            expanded.delete(id);
        } else {
            expanded.add(id);
        }
    };
    let deleting = $state<TaskItem | null>(null);

    const openTasks = $derived(tasks.filter((task) => task.status !== 'done'));
    const doneTasks = $derived(tasks.filter((task) => task.status === 'done'));
    const openPoints = $derived(
        openTasks.reduce((sum, task) => sum + (task.storyPoints ?? 0), 0),
    );
    const overdueCount = $derived(
        openTasks.filter((task) => task.isOverdue).length,
    );

    // A deadline is a calendar date. Read it as local midnight, so the day
    // does not shift in time zones west of UTC.
    const formatDate = (date: string) =>
        new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
        });

    // Mark a Task done, or open it again. Only the status changes.
    const toggleDone = (task: TaskItem) => {
        router.patch(
            updateStatus({ current_team: team, task: task.id }).url,
            { status: task.status === 'done' ? 'todo' : 'done' },
            { preserveScroll: true },
        );
    };

    const confirmDelete = () => {
        if (!deleting) {
            return;
        }

        router.delete(destroyTask({ current_team: team, task: deleting.id }).url, {
            preserveScroll: true,
            onFinish: () => (deleting = null),
        });
    };
</script>

<AppHead title={project.name} />

{#snippet taskRow(task: TaskItem)}
    <li
        class={cn(
            'group/row border-t border-border first:border-t-0',
            editing !== task.id && 'transition-colors hover:bg-muted/40',
        )}
    >
        {#if editing === task.id}
            <div class="bg-muted/30 px-4 py-4">
                <TaskForm
                    action={updateTask.form({
                        current_team: team,
                        task: task.id,
                    })}
                    task={task}
                    pointOptions={storyPointOptions}
                    {statusOptions}
                    submitLabel="Save task"
                    focus
                    onDone={() => (editing = null)}
                    onCancel={() => (editing = null)}
                />
            </div>
        {:else}
            <div class="flex min-h-12 items-center gap-3 py-2 pr-2 pl-4">
                <button
                    type="button"
                    role="checkbox"
                    aria-checked={task.status === 'done'}
                    aria-label={task.status === 'done'
                        ? `Open ${task.title} again`
                        : `Mark ${task.title} as done`}
                    disabled={project.archived}
                    onclick={() => toggleDone(task)}
                    class={cn(
                        'flex size-5 shrink-0 items-center justify-center rounded-full border transition-colors focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none disabled:opacity-50',
                        task.status === 'done'
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-input hover:border-primary',
                    )}
                >
                    {#if task.status === 'done'}
                        <Checkmark class="size-3.5" />
                    {/if}
                </button>

                <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                    <div class="flex min-w-0 flex-col gap-0.5 sm:flex-row sm:items-center sm:gap-3">
                        <span
                            class={cn(
                                'min-w-0 text-sm wrap-break-word',
                                task.status === 'done' &&
                                    'text-muted-foreground line-through decoration-muted-foreground/60',
                            )}
                        >
                            {task.title}
                        </span>
                        {#if task.status === 'in_progress'}
                            <span
                                class="w-fit shrink-0 rounded-sm bg-accent px-1.5 py-0.5 text-[11px] font-medium text-accent-foreground"
                            >
                                In progress
                            </span>
                        {/if}
                    </div>
                    {#if task.description}
                        <button
                            type="button"
                            aria-expanded={expanded.has(task.id)}
                            onclick={() => toggleDescription(task.id)}
                            class={cn(
                                'rounded-sm text-start text-xs whitespace-pre-line text-muted-foreground wrap-break-word hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                                !expanded.has(task.id) && 'line-clamp-2',
                            )}
                        >{task.description}</button>
                    {/if}
                </div>

                <div class="flex shrink-0 items-center gap-3 text-xs tabular-nums">
                    {#if task.dueDate}
                        <span
                            class={cn(
                                'flex items-center gap-1',
                                task.isOverdue
                                    ? 'font-medium text-destructive'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {#if task.isOverdue}
                                <WarningAltFilled class="size-3.5" />
                                <span class="sr-only">Overdue,</span>
                            {:else}
                                <Calendar class="size-3.5" />
                            {/if}
                            {formatDate(task.dueDate)}
                        </span>
                    {/if}
                    <span
                        class={cn(
                            'flex h-6 min-w-12 items-center justify-center rounded-md border px-1.5',
                            task.storyPoints === null
                                ? 'border-dashed border-border text-muted-foreground'
                                : 'border-border text-foreground',
                        )}
                        title="Story points"
                    >
                        {task.storyPoints === null ? '– pts' : `${task.storyPoints} pts`}
                    </span>
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger>
                        {#snippet child({ props })}
                            <Button
                                {...props}
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0 text-muted-foreground hover:text-foreground data-[state=open]:bg-accent"
                                aria-label={`Actions for ${task.title}`}
                            >
                                <OverflowMenuVertical class="size-4" />
                            </Button>
                        {/snippet}
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        class="w-40"
                        onCloseAutoFocus={(event: Event) => event.preventDefault()}
                    >
                        <DropdownMenuItem onSelect={() => (editing = task.id)}>
                            <Edit class="size-4" />
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => (deleting = task)}
                        >
                            <TrashCan class="size-4" />
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        {/if}
    </li>
{/snippet}

<div
    class="grid gap-6 px-4 py-8 sm:py-10 lg:grid-cols-[14rem_minmax(0,1fr)] lg:gap-10"
>
    <aside class="lg:sticky lg:top-6 lg:self-start">
        <ProjectSelector {navigation} {team} currentId={project.id} />
    </aside>

    <div class="flex min-w-0 flex-col">
    <header class="mb-6 space-y-2">
        <p class="text-xs font-medium text-muted-foreground">
            {project.areaName}
        </p>
        {#if editingProject}
            <div class="rounded-lg border border-border bg-card p-4">
                <ProjectForm
                    action={updateProject.form({ current_team: team, project: project.id })}
                    name={project.name}
                    description={project.description}
                    onDone={() => (editingProject = false)}
                    onCancel={() => (editingProject = false)}
                />
            </div>
        {:else}
            <div class="flex items-start justify-between gap-4">
                <h1 class="type-title min-w-0 break-words">{project.name}</h1>
                {#if !project.archived}
                    <Button
                        variant="ghost"
                        size="sm"
                        class="shrink-0 gap-1.5 text-muted-foreground hover:text-foreground"
                        onclick={() => (editingProject = true)}
                    >
                        <Edit class="size-4" />
                        Edit project
                    </Button>
                {/if}
            </div>
            {#if project.description}
                <p class="max-w-prose text-sm whitespace-pre-line text-foreground/80">{project.description}</p>
            {/if}
        {/if}
        <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground tabular-nums">
            <span>{openTasks.length} open {openTasks.length === 1 ? 'task' : 'tasks'}</span>
            <span>{openPoints} open points</span>
            {#if overdueCount > 0}
                <span class="flex items-center gap-1 font-medium text-destructive">
                    <WarningAltFilled class="size-4" />
                    {overdueCount} overdue
                </span>
            {/if}
        </p>
    </header>

    {#if project.archived}
        <div
            class="mb-6 rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
        >
            This project is archived. Restore it on the
            <Link href={areasIndex(team)} class="font-medium text-foreground underline underline-offset-4"
                >Areas page</Link
            > to add or change tasks.
        </div>
    {:else}
        <section
            class="mb-6 rounded-lg border border-border bg-card p-4"
            aria-label="Add a task"
        >
            <TaskForm
                action={storeTask.form({ current_team: team, project: project.id })}
                pointOptions={storyPointOptions}
                submitLabel="Add task"
            />
        </section>
    {/if}

    {#if tasks.length === 0}
        <div
            class="flex flex-col items-start gap-3 rounded-lg border border-dashed border-border px-6 py-10 sm:items-center sm:text-center"
        >
            <span
                class="flex size-10 items-center justify-center rounded-md bg-accent text-accent-foreground"
                aria-hidden="true"
            >
                <TaskIcon class="size-5" />
            </span>
            <div class="space-y-1">
                <h2 class="type-section">No tasks yet</h2>
                <p class="max-w-sm text-sm text-muted-foreground">
                    Add the first task above. Give it story points for the
                    effort, and a deadline if it has one.
                </p>
            </div>
        </div>
    {:else}
        <section aria-labelledby="open-tasks">
            <h2 id="open-tasks" class="sr-only">Open tasks</h2>
            {#if openTasks.length > 0}
                <ul class="overflow-hidden rounded-lg border border-border bg-card">
                    {#each openTasks as task (task.id)}
                        {@render taskRow(task)}
                    {/each}
                </ul>
            {:else}
                <p class="rounded-lg border border-border bg-card px-4 py-6 text-sm text-muted-foreground">
                    All tasks are done.
                </p>
            {/if}
        </section>

        {#if doneTasks.length > 0}
            <details class="group mt-8">
                <summary
                    class="flex w-fit cursor-pointer list-none items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground [&::-webkit-details-marker]:hidden"
                >
                    <ChevronRight
                        class="size-4 group-open:rotate-90 motion-safe:transition-transform"
                    />
                    Done
                    <span class="tabular-nums">({doneTasks.length})</span>
                </summary>
                <ul class="mt-3 overflow-hidden rounded-lg border border-border bg-card">
                    {#each doneTasks as task (task.id)}
                        {@render taskRow(task)}
                    {/each}
                </ul>
            </details>
        {/if}
    {/if}
    </div>
</div>

<AlertDialog
    open={deleting !== null}
    onOpenChange={(open: boolean) => {
        if (!open) {
            deleting = null;
        }
    }}
>
    <AlertDialogContent>
        <AlertDialogHeader>
            <AlertDialogTitle>Delete this task?</AlertDialogTitle>
            <AlertDialogDescription>
                "{deleting?.title}" is deleted for good. You cannot undo this.
            </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
                class="bg-destructive-solid text-white hover:brightness-95"
                onclick={confirmDelete}
            >
                Delete task
            </AlertDialogAction>
        </AlertDialogFooter>
    </AlertDialogContent>
</AlertDialog>
