<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Textarea } from '@/components/ui/textarea';

    // Edit the name and the description of a Project on its own page.
    let {
        action,
        name,
        description,
        onDone,
        onCancel,
    }: {
        action: { action: string; method: 'get' | 'post' };
        name: string;
        description: string | null;
        onDone: () => void;
        onCancel: () => void;
    } = $props();

    const id = $props.id();
    let nameInput = $state<HTMLInputElement | null>(null);

    $effect(() => {
        if (nameInput) {
            const frame = requestAnimationFrame(() => nameInput?.focus());

            return () => cancelAnimationFrame(frame);
        }
    });
</script>

<Form
    {...action}
    class="flex flex-col gap-4"
    options={{ preserveScroll: true }}
    onSuccess={() => onDone()}
>
    {#snippet children({ errors, processing })}
        <div class="flex flex-col gap-2">
            <label for={`${id}-name`} class="text-sm font-medium"
                >Project name</label
            >
            <Input
                id={`${id}-name`}
                bind:ref={nameInput}
                name="name"
                value={name}
                maxlength={100}
                required
                autocomplete="off"
                aria-invalid={errors.name ? 'true' : undefined}
                onkeydown={(event: KeyboardEvent) => {
                    if (event.key === 'Escape') {
                        onCancel();
                    }
                }}
            />
            <InputError message={errors.name} />
        </div>

        <div class="flex flex-col gap-2">
            <label
                for={`${id}-description`}
                class="text-sm font-medium">Description</label
            >
            <Textarea
                id={`${id}-description`}
                name="description"
                value={description ?? ''}
                placeholder="The course, the goal, or what the project needs to deliver"
                maxlength={2000}
                rows={3}
                aria-invalid={errors.description ? 'true' : undefined}
                class="max-h-72"
            />
            <InputError message={errors.description} />
        </div>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button type="button" variant="ghost" onclick={onCancel}>Cancel</Button>
            <Button type="submit" disabled={processing}>Save project</Button>
        </div>
    {/snippet}
</Form>
