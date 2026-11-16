<script module lang="ts">
    export type TaskFields = {
        title: string;
        storyPoints: number | null;
        dueDate: string | null;
        status?: string;
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import {
        NativeSelect,
        NativeSelectOption,
    } from '@/components/ui/native-select';
    import { cn } from '@/lib/utils';

    // Create a Task (no `task`) or edit one. Story points are a radio group
    // with the allowed values. The deadline is a native date field.
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

    $effect(() => {
        if (focus && titleInput) {
            const frame = requestAnimationFrame(() => titleInput?.focus());

            return () => cancelAnimationFrame(frame);
        }
    });

    const isEdit = $derived(task !== undefined);
    const pointChoices = $derived<(number | null)[]>([null, ...pointOptions]);
</script>

<Form
    {...action}
    class="flex flex-col gap-3"
    options={{ preserveScroll: true }}
    resetOnSuccess={!isEdit}
    onSuccess={() => onDone?.()}
>
    {#snippet children({ errors, processing })}
        <div class="flex flex-col gap-1.5">
            <label for={`${id}-title`} class="sr-only">Task title</label>
            <Input
                id={`${id}-title`}
                bind:ref={titleInput}
                name="title"
                value={task?.title ?? ''}
                placeholder="What needs to be done?"
                maxlength={200}
                required
                autocomplete="off"
                aria-invalid={errors.title ? 'true' : undefined}
                class="h-9"
                onkeydown={(event: KeyboardEvent) => {
                    if (event.key === 'Escape') {
                        onCancel?.();
                    }
                }}
            />
            <InputError message={errors.title} />
        </div>

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
                <Input
                    id={`${id}-due`}
                    type="date"
                    name="due_date"
                    value={task?.dueDate ?? ''}
                    aria-invalid={errors.due_date ? 'true' : undefined}
                    class="h-9 w-40"
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
                    <NativeSelect
                        id={`${id}-status`}
                        name="status"
                        value={task?.status}
                        class="w-36"
                    >
                        {#each statusOptions as option (option.value)}
                            <NativeSelectOption value={option.value}
                                >{option.label}</NativeSelectOption
                            >
                        {/each}
                    </NativeSelect>
                    <InputError message={errors.status} />
                </div>
            {/if}

            <div class="ml-auto flex items-center gap-2">
                {#if onCancel}
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-9"
                        onclick={() => onCancel?.()}
                    >
                        Cancel
                    </Button>
                {/if}
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
    {/snippet}
</Form>
