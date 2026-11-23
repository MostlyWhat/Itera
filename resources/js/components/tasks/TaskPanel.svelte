<script module lang="ts">
    import type { SubtaskItem } from '@/components/tasks/SubtaskList.svelte';
    import type { Progress } from '@/components/tasks/SubtaskProgress.svelte';

    export type PanelTask = {
        id: number;
        title: string;
        description: string | null;
        status: string;
        storyPoints: number | null;
        dueDate: string | null;
        isOverdue: boolean;
        weeklyId: number | null;
        repeatsOn: string | null;
        progress: Progress | null;
        subtasks: SubtaskItem[];
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Repeat from 'carbon-icons-svelte/lib/Repeat.svelte';
    import TrashCan from 'carbon-icons-svelte/lib/TrashCan.svelte';
    import WarningAltFilled from 'carbon-icons-svelte/lib/WarningAltFilled.svelte';
    import SubtaskList from '@/components/tasks/SubtaskList.svelte';
    import TaskForm from '@/components/tasks/TaskForm.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import {
        Sheet,
        SheetContent,
        SheetDescription,
        SheetFooter,
        SheetHeader,
        SheetTitle,
    } from '@/components/ui/sheet';
    import { formatShortDate } from '@/lib/dates';
    import { store as storeTask, destroy as destroyTask, update as updateTask } from '@/routes/tasks';
    import { destroy as stopWeekly } from '@/routes/weekly';

    // One side panel for a Task: its fields, its weekly repeat, and its
    // subtasks, with Save and Delete in a fixed footer. IBM Carbon puts a
    // form that people use again and again, with more than five inputs, in
    // a side panel. Nothing opens on top of it: Delete asks in the footer.
    let {
        open = $bindable(false),
        team,
        project,
        task,
        pointOptions,
        statusOptions,
    }: {
        open?: boolean;
        team: string;
        project: { id: number; name: string; areaName: string; archived: boolean };
        task: PanelTask | null;
        pointOptions: number[];
        statusOptions: { value: string; label: string }[];
    } = $props();

    const formId = $props.id();
    let saving = $state(false);
    let confirming = $state(false);
    let busy = $state(false);

    const readOnly = $derived(project.archived);
    const close = () => (open = false);

    // A closed panel forgets a Delete that the user did not confirm.
    $effect(() => {
        if (!open) {
            confirming = false;
        }
    });

    const remove = () => {
        if (!task) {
            return;
        }

        busy = true;
        router.delete(destroyTask({ current_team: team, task: task.id }).url, {
            preserveScroll: true,
            onSuccess: close,
            onFinish: () => (busy = false),
        });
    };

    const stopRepeat = () => {
        if (!task?.weeklyId) {
            return;
        }

        busy = true;
        router.delete(stopWeekly({ current_team: team, recurrenceTemplate: task.weeklyId }).url, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (busy = false),
        });
    };
</script>

<Sheet bind:open>
    <SheetContent side="right" class="gap-0 p-0 data-[side=right]:w-full data-[side=right]:sm:max-w-xl">
        <SheetHeader class="gap-2 border-b border-border px-6 pt-5 pr-14 pb-4">
            <SheetTitle class="text-base">{task ? (readOnly ? 'Task' : 'Edit task') : 'New task'}</SheetTitle>
            <SheetDescription>{project.areaName} · {project.name}</SheetDescription>
            {#if task && (task.repeatsOn || task.isOverdue)}
                <div class="flex flex-wrap items-center gap-2">
                    {#if task.repeatsOn}
                        <Badge variant="secondary">
                            <Repeat aria-hidden="true" />
                            Weekly · {task.repeatsOn}
                        </Badge>
                    {/if}
                    {#if task.isOverdue && task.dueDate}
                        <Badge variant="destructive" class="tabular-nums">
                            <WarningAltFilled aria-hidden="true" />
                            Overdue since {formatShortDate(task.dueDate)}
                        </Badge>
                    {/if}
                </div>
            {/if}
        </SheetHeader>

        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-6 py-6">
            {#if readOnly}
                <p class="mb-6 rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
                    This project is archived. Restore it to change this task.
                </p>
            {/if}

            {#key task?.id ?? 'new'}
                <TaskForm
                    id={formId}
                    action={task
                        ? updateTask.form({ current_team: team, task: task.id })
                        : storeTask.form({ current_team: team, project: project.id })}
                    task={task ?? undefined}
                    {pointOptions}
                    statusOptions={task ? statusOptions : []}
                    disabled={readOnly}
                    bind:processing={saving}
                    onStopRepeat={task?.weeklyId ? stopRepeat : undefined}
                    stopping={busy}
                    onDone={close}
                />
            {/key}

            {#if task}
                <div class="mt-8 border-t border-border pt-6">
                    <SubtaskList
                        {team}
                        taskId={task.id}
                        subtasks={task.subtasks}
                        progress={task.progress}
                        disabled={readOnly}
                    />
                </div>
            {/if}
        </div>

        <SheetFooter class="flex-row flex-wrap items-center gap-2 border-t border-border px-6 py-3">
            {#if readOnly}
                <Button variant="outline" class="ms-auto" onclick={close}>Close</Button>
            {:else if confirming}
                <p class="me-auto min-w-0 text-sm text-pretty" role="status">
                    Delete this task for good? You cannot undo this.
                </p>
                <Button variant="ghost" onclick={() => (confirming = false)}>Keep task</Button>
                <Button
                    class="bg-destructive-solid text-white hover:brightness-95"
                    disabled={busy}
                    onclick={remove}
                >
                    Delete task
                </Button>
            {:else}
                {#if task}
                    <Button
                        variant="ghost"
                        class="gap-1.5 text-muted-foreground hover:text-destructive"
                        onclick={() => (confirming = true)}
                    >
                        <TrashCan class="size-4" />
                        Delete
                    </Button>
                {/if}
                <Button variant="ghost" class="ms-auto" onclick={close}>Cancel</Button>
                <Button type="submit" form={formId} disabled={saving}>
                    {task ? 'Save task' : 'Add task'}
                </Button>
            {/if}
        </SheetFooter>
    </SheetContent>
</Sheet>
