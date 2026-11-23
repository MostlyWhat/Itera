<script module lang="ts">
    export type TaskFields = {
        title: string;
        description?: string | null;
        storyPoints: number | null;
        dueDate: string | null;
        status?: string;
        repeatsOn?: string | null;
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import Repeat from 'carbon-icons-svelte/lib/Repeat.svelte';
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

    // The fields of a Task, in two groups: what the task is, and how it fits
    // the plan. The panel around it holds the buttons, so the form has an id
    // that the Save button points to.
    let {
        id,
        action,
        task,
        pointOptions,
        statusOptions = [],
        disabled = false,
        processing = $bindable(false),
        onStopRepeat,
        stopping = false,
        onDone,
    }: {
        id: string;
        action: { action: string; method: 'get' | 'post' };
        task?: TaskFields;
        pointOptions: number[];
        statusOptions?: { value: string; label: string }[];
        disabled?: boolean;
        processing?: boolean;
        onStopRepeat?: () => void;
        stopping?: boolean;
        onDone?: () => void;
    } = $props();

    const isEdit = $derived(task !== undefined);
    const isWeekly = $derived(Boolean(task?.repeatsOn));
    const pointChoices = $derived<(number | null)[]>([null, ...pointOptions]);

    // svelte-ignore state_referenced_locally
    let status = $state(task?.status ?? 'todo');
    const statusLabel = $derived(
        statusOptions.find((option) => option.value === status)?.label ?? '',
    );

    // A one-off Task can repeat each week on one weekday (ITERA-09). Then the
    // weekday sets its deadline, so the deadline field hides.
    const weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    let repeat = $state('');
    const repeatDay = $derived(repeat === '' ? null : weekdays[Number(repeat)]);
    const repeatLabel = $derived(repeatDay === null ? 'Does not repeat' : `Every ${repeatDay}`);

    const label = 'text-sm font-medium';
    const heading = 'text-xs font-medium tracking-wide text-muted-foreground uppercase';
</script>

<Form
    {...action}
    {id}
    options={{ preserveScroll: true }}
    resetOnSuccess={!isEdit}
    onStart={() => (processing = true)}
    onFinish={() => (processing = false)}
    onSuccess={() => onDone?.()}
>
    {#snippet children({ errors })}
        <fieldset {disabled} class="flex flex-col gap-8 disabled:opacity-70">
            <section class="flex flex-col gap-5" aria-labelledby={`${id}-about`}>
                <h3 id={`${id}-about`} class={heading}>About the task</h3>
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
            </section>

            <section class="flex flex-col gap-5" aria-labelledby={`${id}-plan`}>
                <h3 id={`${id}-plan`} class={heading}>Plan</h3>

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
                    {#if isEdit && statusOptions.length > 0}
                        <div class="flex flex-col gap-2">
                            <label for={`${id}-status`} class={label}>Status</label>
                            <Select type="single" name="status" bind:value={status} {disabled}>
                                <SelectTrigger id={`${id}-status`} class="w-full">{statusLabel}</SelectTrigger>
                                <SelectContent>
                                    {#each statusOptions as option (option.value)}
                                        <SelectItem value={option.value} label={option.label} />
                                    {/each}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.status} />
                        </div>
                    {/if}

                    {#if repeatDay === null}
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
                    {/if}

                    {#if !isWeekly}
                        <div class="flex flex-col gap-2">
                            <label for={`${id}-repeat`} class={label}>Repeat</label>
                            <Select type="single" name="repeat_weekday" bind:value={repeat} {disabled}>
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
                </div>

                {#if repeatDay !== null}
                    <p class="text-sm text-pretty text-muted-foreground">
                        {isEdit ? 'This task moves to the next' : 'Itera adds this task every'}
                        {repeatDay}, with that day as the deadline.
                        {isEdit ? 'A new one follows each week.' : ''} Each week has its own status.
                    </p>
                {/if}

                {#if isWeekly}
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border border-border bg-muted/40 px-4 py-3">
                        <Repeat class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <div class="flex min-w-0 flex-1 flex-col">
                            <span class="text-sm font-medium">Repeats every {task?.repeatsOn}</span>
                            <span class="text-xs text-pretty text-muted-foreground">
                                A new title, description, or points also go to later open weeks.
                            </span>
                        </div>
                        {#if onStopRepeat && !disabled}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={stopping}
                                onclick={onStopRepeat}
                            >
                                Stop repeating
                            </Button>
                        {/if}
                    </div>
                {/if}
            </section>
        </fieldset>
    {/snippet}
</Form>
