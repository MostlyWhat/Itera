<script module lang="ts">
    import { edit as editPlanning } from '@/routes/planning';

    export const layout = {
        breadcrumbs: [{ title: 'Planning settings', href: editPlanning() }],
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Select, SelectContent, SelectItem, SelectTrigger } from '@/components/ui/select';
    import { update } from '@/routes/planning';

    // The first day of the week. New sprints start on it. Sprints that exist
    // keep their dates.
    let { weekStart }: { weekStart: number } = $props();

    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    // svelte-ignore state_referenced_locally
    let value = $state(String(weekStart));
</script>

<AppHead title="Planning settings" />

<h1 class="sr-only">Planning settings</h1>

<div class="space-y-6">
    <Heading
        variant="small"
        title="Planning settings"
        description="Choose the day your week starts. New sprints start on this day."
    />

    <Form {...update.form()} options={{ preserveScroll: true }} class="flex max-w-sm flex-col gap-5">
        {#snippet children({ errors, processing })}
            <div class="flex flex-col gap-2">
                <label for="week-start" class="text-sm font-medium">First day of the week</label>
                <Select type="single" name="week_start" bind:value>
                    <SelectTrigger id="week-start" class="w-56">{days[Number(value)]}</SelectTrigger>
                    <SelectContent>
                        {#each days as day, index (day)}
                            <SelectItem value={String(index)} label={day} />
                        {/each}
                    </SelectContent>
                </Select>
                <p class="text-sm text-muted-foreground">
                    Sprints you already planned keep their dates.
                </p>
                <InputError message={errors.week_start} />
            </div>
            <Button type="submit" class="w-fit" disabled={processing}>Save</Button>
        {/snippet}
    </Form>
</div>
