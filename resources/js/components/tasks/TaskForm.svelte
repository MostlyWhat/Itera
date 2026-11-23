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

    // The fields of a Task, for the task dialog: create a Task (no `task`)
    // or edit one. Story points are a radio group with the allowed values.
    let {
        action,
        task,
        pointOptions,
        statusOptions = [],
        submitLabel,
        onDone,
        onCancel,
    }: {
        action: { action: string; method: 'get' | 'post' };
        task?: TaskFields;
        pointOptions: number[];
        statusOptions?: { value: string; label: string }[];
        submitLabel: string;
        onDone?: () => void;
        onCancel?: () => void;
    } = $props();

    const id = $props.id();
    const isEdit = $derived(task !== undefined);
    const pointChoices = $derived<(number | null)[]>([null, ...pointOptions]);

    // svelte-ignore state_referenced_locally
    let status = $state(task?.status ?? 'todo');
    const statusLabel = $derived(
        statusOptions.find((option) => option.value === status)?.label ?? '',
    );

    const label = 'text-sm font-medium';

    // A new Task can repeat each week on one weekday (ITERA-09). Then the
    // weekday is its deadline, so the deadline field hides.
    const weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    let repeat = $state('');
    const repeatLabel = $derived(repeat === '' ? 'Does not repeat' : `Every ${weekdays[Number(repeat)]}`);
</script>

<Form
    {...action}
    class="flex flex-col gap-5"
    options={{ preserveScroll: true }}
    resetOnSuccess={!isEdit}
    onSuccess={() => onDone?.()}
>
    {#snippet children({ errors, processing })}
        <div class="flex flex-col gap-2">
            <label for={`${id}-title`} class={label}>Title</label>
            <Input
                id={`${id}-title`}
                name="title"
                value={task?.title ?? ''}
                placeholder="For example: Write the method section"
                maxlength={200}
                required
                autocomplete="off"
                aria-invalid={errors.title ? 'true' : undefined}
            />
            <InputError message={errors.title} />
        </div>

        <div class="flex flex-col gap-2">
            <label for={`${id}-description`} class={label}>
                Description <span class="font-normal text-muted-foreground">(optional)</span>
            </label>
            <Textarea
                id={`${id}-description`}
                name="description"
                value={task?.description ?? ''}
                placeholder="Notes, links, or what done looks like"
                maxlength={2000}
                rows={3}
                aria-invalid={errors.description ? 'true' : undefined}
                class="max-h-48"
            />
            <InputError message={errors.description} />
        </div>

        <fieldset class="flex flex-col">
            <legend class={cn(label, 'mb-2')}>Story points</legend>
            <div class="inline-flex h-9 w-fit overflow-hidden rounded-md border border-input shadow-xs">
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

        <div class="grid gap-5 sm:grid-cols-2">
            {#if !isEdit}
                <div class="flex flex-col gap-2">
                    <label for={`${id}-repeat`} class={label}>Repeat</label>
                    <Select type="single" name="repeat_weekday" bind:value={repeat}>
                        <SelectTrigger id={`${id}-repeat`} class="w-full">{repeatLabel}</SelectTrigger>
                        <SelectContent>
                            <SelectItem value="" label="Does not repeat" />
                            {#each weekdays as day, index (day)}
                                <SelectItem value={String(index)} label={`Every ${day}`} />
                            {/each}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.repeat_weekday} />
                </div>
            {/if}

            {#if repeat === ''}
            <div class="flex flex-col gap-2">
                <label for={`${id}-due`} class={label}>
                    Deadline <span class="font-normal text-muted-foreground">(optional)</span>
                </label>
                <DatePicker
                    id={`${id}-due`}
                    name="due_date"
                    value={task?.dueDate ?? null}
                    placeholder="No deadline"
                    clearLabel="Remove the deadline"
                    invalid={Boolean(errors.due_date)}
                    class="w-full"
                />
                <InputError message={errors.due_date} />
            </div>
            {:else}
                <p class="self-end text-xs text-pretty text-muted-foreground sm:pb-2">
                    Itera adds this task every {weekdays[Number(repeat)]}, with that day as the deadline. Each week has its own status.
                </p>
            {/if}

            {#if isEdit && statusOptions.length > 0}
                <div class="flex flex-col gap-2">
                    <label for={`${id}-status`} class={label}>Status</label>
                    <Select type="single" name="status" bind:value={status}>
                        <SelectTrigger id={`${id}-status`} class="w-full">
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
        </div>

        <div class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
            <Button type="button" variant="ghost" onclick={() => onCancel?.()}>Cancel</Button>
            <Button type="submit" disabled={processing}>{submitLabel}</Button>
        </div>
    {/snippet}
</Form>
