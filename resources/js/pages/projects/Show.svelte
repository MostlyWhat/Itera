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
    import { page, router } from '@inertiajs/svelte';
    import { SvelteSet } from 'svelte/reactivity';
    import Add from 'carbon-icons-svelte/lib/Add.svelte';
    import Archive from 'carbon-icons-svelte/lib/Archive.svelte';
    import Calendar from 'carbon-icons-svelte/lib/Calendar.svelte';
    import Checkmark from 'carbon-icons-svelte/lib/Checkmark.svelte';
    import ChevronRight from 'carbon-icons-svelte/lib/ChevronRight.svelte';
    import Edit from 'carbon-icons-svelte/lib/Edit.svelte';
    import ListChecked from 'carbon-icons-svelte/lib/ListChecked.svelte';
    import OverflowMenuVertical from 'carbon-icons-svelte/lib/OverflowMenuVertical.svelte';
    import TrashCan from 'carbon-icons-svelte/lib/TrashCan.svelte';
    import Undo from 'carbon-icons-svelte/lib/Undo.svelte';
    import WarningAltFilled from 'carbon-icons-svelte/lib/WarningAltFilled.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import FormDialog from '@/components/FormDialog.svelte';
    import ProjectForm from '@/components/projects/ProjectForm.svelte';
    import type { ProjectNavigation } from '@/components/projects/ProjectRail.svelte';
    import ProjectsShell from '@/components/projects/ProjectsShell.svelte';
    import SubtaskList from '@/components/tasks/SubtaskList.svelte';
    import type { SubtaskItem } from '@/components/tasks/SubtaskList.svelte';
    import SubtaskProgress from '@/components/tasks/SubtaskProgress.svelte';
    import type { Progress } from '@/components/tasks/SubtaskProgress.svelte';
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
    import { formatShortDate } from '@/lib/dates';
    import { cn } from '@/lib/utils';
    import {
        archive as archiveProject,
        unarchive as unarchiveProject,
        update as updateProject,
    } from '@/routes/projects';
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
        progress: Progress | null;
        subtasks: SubtaskItem[];
    };

    let {
        navigation,
        project,
        tasks,
        storyPointOptions,
        statusOptions,
    }: {
        navigation: ProjectNavigation;
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

    // Data entry happens in dialogs, so the list never moves under the user.
    // `taskDialog` is "new", a Task to edit, or null when closed.
    let taskDialog = $state<'new' | TaskItem | null>(null);
    let taskDialogOpen = $state(false);
    let projectDialogOpen = $state(false);

    const openTask = (value: 'new' | TaskItem) => {
        taskDialog = value;
        taskDialogOpen = true;
    };
    const closeTask = () => (taskDialogOpen = false);
    const editedTask = $derived(taskDialog !== null && taskDialog !== 'new' ? taskDialog : null);

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

    // The details and the subtask checklist of one Task, in a dialog. The Task comes from
    // the props by id, so the list updates after each change.
    let detailId = $state<number | null>(null);
    let detailOpen = $state(false);
    const detailTask = $derived(tasks.find((task) => task.id === detailId) ?? null);
    const openDetail = (task: TaskItem) => {
        detailId = task.id;
        detailOpen = true;
    };

    const openTasks = $derived(tasks.filter((task) => task.status !== 'done'));
    const doneTasks = $derived(tasks.filter((task) => task.status === 'done'));
    const openPoints = $derived(
        openTasks.reduce((sum, task) => sum + (task.storyPoints ?? 0), 0),
    );
    const overdueCount = $derived(
        openTasks.filter((task) => task.isOverdue).length,
    );

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
    <!-- The title button is the keyboard path to the details. -->
    <!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_noninteractive_element_interactions -->
    <li
        class="group/row cursor-pointer border-t border-border transition-colors first:border-t-0 hover:bg-muted/40"
        onclick={(event: MouseEvent) => {
            // The checkbox, the description, and the menu do their own work.
            if (!(event.target as HTMLElement).closest('button, a, [role="menu"]')) {
                openDetail(task);
            }
        }}
    >
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
                        <button
                            type="button"
                            onclick={() => openDetail(task)}
                            class={cn(
                                'min-w-0 rounded-sm text-start text-sm wrap-break-word focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                                task.status === 'done' &&
                                    'text-muted-foreground line-through decoration-muted-foreground/60',
                            )}
                        >
                            {task.title}
                            <span class="sr-only">, show details</span>
                        </button>
                        {#if task.status === 'in_progress'}
                            <span
                                class="w-fit shrink-0 rounded-sm bg-accent px-1.5 py-0.5 text-xs font-medium text-accent-foreground"
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
                    {#if task.progress}
                        <button
                            type="button"
                            onclick={() => openDetail(task)}
                            class="rounded-sm px-1 py-0.5 hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                            aria-label={`Subtasks of ${task.title}: ${task.progress.done} of ${task.progress.total} done`}
                        >
                            <SubtaskProgress progress={task.progress} />
                        </button>
                    {/if}
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
                            {formatShortDate(task.dueDate)}
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
                        <DropdownMenuItem onSelect={() => openTask(task)}>
                            <Edit class="size-4" />
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={() => openDetail(task)}>
                            <ListChecked class="size-4" />
                            Subtasks
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
    </li>
{/snippet}

<ProjectsShell {navigation} {team} currentId={project.id}>
    <header class="mb-8">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 space-y-1">
                    <h1 class="type-title wrap-break-word">{project.name}</h1>
                    <p
                        class="flex flex-wrap items-center gap-x-2 text-sm text-muted-foreground tabular-nums"
                    >
                        <span>{project.areaName}</span>
                        <span aria-hidden="true">·</span>
                        <span>{openTasks.length} open, {openPoints} points</span>
                        {#if overdueCount > 0}
                            <span aria-hidden="true">·</span>
                            <span class="flex items-center gap-1 font-medium text-destructive">
                                <WarningAltFilled class="size-4" />
                                {overdueCount} overdue
                            </span>
                        {/if}
                    </p>
                </div>
                {#if !project.archived}
                    <div class="flex shrink-0 items-center gap-1">
                        <Button onclick={() => openTask('new')}>
                            <Add class="size-4" />
                            Add task
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger>
                                {#snippet child({ props })}
                                    <Button
                                        {...props}
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 text-muted-foreground hover:text-foreground data-[state=open]:bg-accent"
                                        aria-label={`Actions for ${project.name}`}
                                    >
                                        <OverflowMenuVertical class="size-4" />
                                    </Button>
                                {/snippet}
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                class="w-44"
                                onCloseAutoFocus={(event: Event) => event.preventDefault()}
                            >
                                <DropdownMenuItem onSelect={() => (projectDialogOpen = true)}>
                                    <Edit class="size-4" />
                                    Edit project
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() =>
                                        router.post(
                                            archiveProject({ current_team: team, project: project.id }).url,
                                            {},
                                            { preserveScroll: true },
                                        )}
                                >
                                    <Archive class="size-4" />
                                    Archive project
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                {/if}
            </div>
            {#if project.description}
                <p class="mt-3 max-w-prose text-sm whitespace-pre-line text-muted-foreground">{project.description}</p>
            {/if}
    </header>

    {#if project.archived}
        <div
            class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
        >
            <span>This project is archived. Restore it to add or change tasks.</span>
            <Button
                variant="outline"
                size="sm"
                class="gap-1.5"
                onclick={() =>
                    router.delete(
                        unarchiveProject({ current_team: team, project: project.id }).url,
                        { preserveScroll: true },
                    )}
            >
                <Undo class="size-4" />
                Restore project
            </Button>
        </div>
    {/if}

    <section aria-labelledby="open-tasks" class="overflow-hidden rounded-lg border border-border bg-card">
        <h2 id="open-tasks" class="sr-only">Open tasks</h2>
        {#if openTasks.length > 0}
            <ul>
                {#each openTasks as task (task.id)}
                    {@render taskRow(task)}
                {/each}
            </ul>
        {:else}
            <p class="px-4 py-8 text-center text-sm text-muted-foreground">
                {tasks.length === 0
                    ? 'No tasks yet. Use Add task to write the first one.'
                    : 'All tasks are done.'}
            </p>
        {/if}
    </section>

    {#if doneTasks.length > 0}
        <details class="group mt-6">
            <summary
                class="flex w-fit cursor-pointer list-none items-center gap-1.5 rounded-md text-sm font-medium text-muted-foreground hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none [&::-webkit-details-marker]:hidden"
            >
                <ChevronRight class="size-4 group-open:rotate-90 motion-safe:transition-transform" />
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
</ProjectsShell>

<FormDialog
    bind:open={taskDialogOpen}
    title={editedTask ? 'Edit task' : 'New task'}
    description={editedTask ? undefined : `In ${project.name}.`}
    size="lg"
>
    {#key editedTask?.id ?? 'new'}
        {#if editedTask}
            <TaskForm
                action={updateTask.form({ current_team: team, task: editedTask.id })}
                task={editedTask}
                pointOptions={storyPointOptions}
                {statusOptions}
                submitLabel="Save task"
                onDone={closeTask}
                onCancel={closeTask}
            />
        {:else}
            <TaskForm
                action={storeTask.form({ current_team: team, project: project.id })}
                pointOptions={storyPointOptions}
                submitLabel="Add task"
                onDone={closeTask}
                onCancel={closeTask}
            />
        {/if}
    {/key}
</FormDialog>

<FormDialog
    bind:open={detailOpen}
    title={detailTask?.title ?? 'Task'}
    size="lg"
>
    {#if detailTask}
        <dl class="grid grid-cols-[7rem_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
            <dt class="text-muted-foreground">Status</dt>
            <dd>{detailTask.statusLabel}</dd>
            <dt class="text-muted-foreground">Story points</dt>
            <dd class="tabular-nums">{detailTask.storyPoints ?? 'None'}</dd>
            <dt class="text-muted-foreground">Deadline</dt>
            <dd class={cn('tabular-nums', detailTask.isOverdue && 'font-medium text-destructive')}>
                {#if detailTask.dueDate}
                    {formatShortDate(detailTask.dueDate)}{detailTask.isOverdue ? ', overdue' : ''}
                {:else}
                    None
                {/if}
            </dd>
        </dl>
        {#if detailTask.description}
            <div class="flex flex-col gap-2">
                <h3 class="text-sm font-medium">Description</h3>
                <p class="max-h-60 overflow-y-auto text-sm whitespace-pre-line wrap-break-word text-muted-foreground">{detailTask.description}</p>
            </div>
        {/if}
        <SubtaskList
            {team}
            taskId={detailTask.id}
            subtasks={detailTask.subtasks}
            progress={detailTask.progress}
            disabled={project.archived}
        />
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button variant="ghost" onclick={() => (detailOpen = false)}>Close</Button>
            {#if !project.archived}
                <Button
                    variant="outline"
                    class="gap-1.5"
                    onclick={() => {
                        const task = detailTask;
                        detailOpen = false;
                        if (task) {
                            openTask(task);
                        }
                    }}
                >
                    <Edit class="size-4" />
                    Edit task
                </Button>
            {/if}
        </div>
    {/if}
</FormDialog>

<FormDialog bind:open={projectDialogOpen} title="Edit project">
    <ProjectForm
        action={updateProject.form({ current_team: team, project: project.id })}
        name={project.name}
        description={project.description}
        onDone={() => (projectDialogOpen = false)}
        onCancel={() => (projectDialogOpen = false)}
    />
</FormDialog>

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
