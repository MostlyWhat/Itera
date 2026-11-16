<script module lang="ts">
    export type TaskFields = {
        title: string;
        description?: string | null;
        storyPoints: number | null;
        dueDate: string | null;
        status?: string;
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import { tick } from 'svelte';
    import DatePicker from '@/components/DatePicker.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
    } from '@/components/ui/select';
    import { Textarea } from '@/components/ui/textarea';
    import { cn } from '@/lib/utils';

    // Create a Task (no `task`) or edit one. Story points are a radio group
    // with the allowed values. The create form shows only the title until
    // it gets focus. The description is optional and opens on request.
    let {
        action,
        task,
        pointOptions,
        statusOptions = [],
        submitLabel,
        focus = false,
        onDone,
        onCancel,
    }: {
        action: { action: string; method: 'get' | 'post' };
        task?: TaskFields;
        pointOptions: number[];
        statusOptions?: { value: string; label: string }[];
        submitLabel: string;
        focus?: boolean;
        onDone?: () => void;
        onCancel?: () => void;
    } = $props();

    const id = $props.id();
    let titleInput = $state<HTMLInputElement | null>(null);
    let descriptionInput = $state<HTMLTextAreaElement | null>(null);

    const openDescription = async () => {
        showDescription = true;
        await tick();
        descriptionInput?.focus();
    };

    $effect(() => {
        if (focus && titleInput) {
            const frame = requestAnimationFrame(() => titleInput?.focus());

            return () => cancelAnimationFrame(frame);
        }
    });

    const isEdit = $derived(task !== undefined);
    // svelte-ignore state_referenced_locally
    let expanded = $state(task !== undefined);

    // Close the details of the create form, or cancel the edit.
    const cancel = () => {
        if (onCancel) {
            onCancel();
        } else {
            expanded = false;
            titleInput?.blur();
        }
    };
    // svelte-ignore state_referenced_locally
    let status = $state(task?.status ?? 'todo');
    const statusLabel = $derived(
        statusOptions.find((option) => option.value === status)?.label ?? '',
    );
    // Read once: the form opens with the field shown or hidden.
    // svelte-ignore state_referenced_locally
    let showDescription = $state(task !== undefined);
    const pointChoices = $derived<(number | null)[]>([null, ...pointOptions]);
</script>

<Form
    {...action}
    class="flex flex-col gap-3"
    options={{ preserveScroll: true }}
    resetOnSuccess={!isEdit}
    onSuccess={() => {
        onDone?.();
        titleInput?.focus();
    }}
>
    {#snippet children({ errors, processing })}
        <div class="flex flex-col gap-1.5">
            <label for={`${id}-title`} class="sr-only">Task title</label>
            <Input
                id={`${id}-title`}
                bind:ref={titleInput}
                name="title"
                value={task?.title ?? ''}
                placeholder={isEdit ? 'Task title' : 'Add a task'}
                maxlength={200}
                required
                autocomplete="off"
                aria-invalid={errors.title ? 'true' : undefined}
                class="h-9"
                onfocus={() => (expanded = true)}
                onkeydown={(event: KeyboardEvent) => {
                    if (event.key === 'Escape') {
                        cancel();
                    }
                }}
            />
            <InputError message={errors.title} />
        </div>

        {#if expanded}
        {#if showDescription}
            <div class="flex flex-col gap-1.5">
                <label
                    for={`${id}-description`}
                    class="text-xs font-medium text-muted-foreground"
                    >Description</label
                >
                <Textarea
                    id={`${id}-description`}
                    bind:ref={descriptionInput}
                    name="description"
                    value={task?.description ?? ''}
                    placeholder="Notes, links, or what done looks like"
                    maxlength={2000}
                    rows={2}
                    aria-invalid={errors.description ? 'true' : undefined}
                    class="max-h-60 min-h-16"
                />
                <InputError message={errors.description} />
            </div>
        {:else}
            <button
                type="button"
                class="w-fit rounded-sm text-xs font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                onclick={openDescription}
            >
                Add a description
            </button>
        {/if}

        <div class="flex flex-wrap items-end gap-x-6 gap-y-3">
            <fieldset class="flex flex-col gap-1.5">
                <legend class="mb-1.5 text-xs font-medium text-muted-foreground">
                    Story points
                </legend>
                <div
                    class="inline-flex h-9 overflow-hidden rounded-md border border-input shadow-xs"
                >
                    {#each pointChoices as points (points ?? 'none')}
                        <label
                            class={cn(
                                'relative flex min-w-9 cursor-pointer items-center justify-center border-l border-input px-2.5 text-sm tabular-nums first:border-l-0',
                                'text-muted-foreground hover:bg-muted hover:text-foreground',
                                'has-[:checked]:bg-primary has-[:checked]:text-primary-foreground',
                                'has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring/50',
                            )}
                        >
                            <input
                                type="radio"
                                name="story_points"
                                value={points ?? ''}
                                checked={(task?.storyPoints ?? null) === points}
                                class="sr-only"
                            />
                            {#if points === null}
                                <span aria-hidden="true">–</span>
                                <span class="sr-only">No points</span>
                            {:else}
                                {points}
                            {/if}
                        </label>
                    {/each}
                </div>
                <InputError message={errors.story_points} />
            </fieldset>

            <div class="flex flex-col gap-1.5">
                <label
                    for={`${id}-due`}
                    class="text-xs font-medium text-muted-foreground"
                    >Deadline</label
                >
                <DatePicker
                    id={`${id}-due`}
                    name="due_date"
                    value={task?.dueDate ?? null}
                    placeholder="No deadline"
                    clearLabel="Remove the deadline"
                    invalid={Boolean(errors.due_date)}
                    class="w-40"
                />
                <InputError message={errors.due_date} />
            </div>

            {#if isEdit && statusOptions.length > 0}
                <div class="flex flex-col gap-1.5">
                    <label
                        for={`${id}-status`}
                        class="text-xs font-medium text-muted-foreground"
                        >Status</label
                    >
                    <Select type="single" name="status" bind:value={status}>
                        <SelectTrigger id={`${id}-status`} class="w-36">
                            {statusLabel}
                        </SelectTrigger>
                        <SelectContent>
                            {#each statusOptions as option (option.value)}
                                <SelectItem value={option.value} label={option.label} />
                            {/each}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.status} />
                </div>
            {/if}

            <div class="ml-auto flex items-center gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-9"
                    onclick={cancel}
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    size="sm"
                    class="h-9"
                    disabled={processing}
                >
                    {submitLabel}
                </Button>
            </div>
        </div>
        {/if}
    {/snippet}
</Form>
