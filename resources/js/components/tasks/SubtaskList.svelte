<script module lang="ts">
    export type SubtaskItem = { id: number; title: string; completed: boolean };
</script>

<script lang="ts">
    import { Form, router } from '@inertiajs/svelte';
    import Add from 'carbon-icons-svelte/lib/Add.svelte';
    import TrashCan from 'carbon-icons-svelte/lib/TrashCan.svelte';
    import InputError from '@/components/InputError.svelte';
    import type { Progress } from '@/components/tasks/SubtaskProgress.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Progress as Bar } from '@/components/ui/progress';
    import { cn } from '@/lib/utils';
    import { destroy, store, update } from '@/routes/subtasks';

    // The checklist of one Task (ITERA-06). Subtasks show how much of the
    // Task is finished. They never change its story points or its status.
    let {
        team,
        taskId,
        subtasks,
        progress,
        disabled = false,
    }: {
        team: string;
        taskId: number;
        subtasks: SubtaskItem[];
        progress: Progress | null;
        disabled?: boolean;
    } = $props();

    const id = $props.id();

    // Keep the dialog open and the list in place while the page reloads.
    const options = { preserveScroll: true, preserveState: true } as const;

    const toggle = (subtask: SubtaskItem, completed: boolean) =>
        router.patch(update({ current_team: team, subtask: subtask.id }).url, { completed }, options);

    const remove = (subtask: SubtaskItem) =>
        router.delete(destroy({ current_team: team, subtask: subtask.id }).url, options);
</script>

<section aria-labelledby={`${id}-title`} class="flex flex-col gap-3">
    <div class="flex items-center justify-between gap-3">
        <h3 id={`${id}-title`} class="text-sm font-medium">Subtasks</h3>
        {#if progress}
            <span class="text-xs text-muted-foreground tabular-nums">
                {progress.done} of {progress.total} done, {progress.percent}%
            </span>
        {/if}
    </div>

    {#if progress}
        <Bar value={progress.percent} aria-label="Subtasks done" />
    {/if}

    {#if subtasks.length > 0}
        <ul class="flex flex-col">
            {#each subtasks as subtask (subtask.id)}
                <li class="group/subtask flex min-h-9 items-center gap-3 rounded-md px-1 hover:bg-muted/50">
                    <Checkbox
                        id={`${id}-${subtask.id}`}
                        checked={subtask.completed}
                        {disabled}
                        onCheckedChange={(checked: boolean) => toggle(subtask, checked)}
                    />
                    <label
                        for={`${id}-${subtask.id}`}
                        class={cn(
                            'min-w-0 flex-1 py-1.5 text-sm wrap-break-word',
                            subtask.completed && 'text-muted-foreground line-through decoration-muted-foreground/60',
                        )}
                    >
                        {subtask.title}
                    </label>
                    {#if !disabled}
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7 shrink-0 text-muted-foreground hover:text-destructive focus-visible:opacity-100 [@media(hover:hover)]:opacity-0 group-hover/subtask:opacity-100"
                            aria-label={`Delete the subtask ${subtask.title}`}
                            onclick={() => remove(subtask)}
                        >
                            <TrashCan class="size-4" />
                        </Button>
                    {/if}
                </li>
            {/each}
        </ul>
    {:else}
        <p class="text-sm text-muted-foreground">
            Split a big task into small steps. Each step you finish moves the progress bar.
        </p>
    {/if}

    {#if !disabled}
        <Form
            {...store.form({ current_team: team, task: taskId })}
            options={{ preserveScroll: true, preserveState: true }}
            resetOnSuccess
            class="flex flex-col gap-1"
        >
            {#snippet children({ errors, processing })}
                <label for={`${id}-new`} class="sr-only">New subtask</label>
                <div class="flex gap-2">
                    <Input
                        id={`${id}-new`}
                        name="title"
                        placeholder="For example Find sources"
                        maxlength={200}
                        autocomplete="off"
                        required
                        aria-invalid={errors.title ? 'true' : undefined}
                    />
                    <Button type="submit" variant="outline" class="shrink-0 gap-1.5" disabled={processing}>
                        <Add class="size-4" />
                        Add
                    </Button>
                </div>
                <InputError message={errors.title} />
            {/snippet}
        </Form>
    {/if}

    <p class="text-xs text-muted-foreground">
        Subtasks give no story points. The points of the task count when the task is done.
    </p>
</section>
