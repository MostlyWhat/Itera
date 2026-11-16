<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';

    // A one-field form for the name of an Area or a Project. It creates a
    // new record (empty `value`) or renames one (`value` is the current name).
    let {
        action,
        value = '',
        label,
        placeholder = '',
        submitLabel,
        autofocus = false,
        onDone,
        onCancel,
    }: {
        action: { action: string; method: 'get' | 'post' };
        value?: string;
        label: string;
        placeholder?: string;
        submitLabel: string;
        autofocus?: boolean;
        onDone?: () => void;
        onCancel?: () => void;
    } = $props();

    const isCreate = $derived(value === '');
</script>

<Form
    {...action}
    class="flex flex-col gap-1.5"
    options={{ preserveScroll: true }}
    resetOnSuccess={isCreate}
    onSuccess={() => onDone?.()}
>
    {#snippet children({ errors, processing })}
        <div class="flex items-center gap-2">
            <label class="sr-only" for={`name-${label}`}>{label}</label>
            <!-- svelte-ignore a11y_autofocus -->
            <Input
                id={`name-${label}`}
                name="name"
                {value}
                {placeholder}
                {autofocus}
                maxlength={100}
                required
                aria-invalid={errors.name ? 'true' : undefined}
                class="h-9 max-w-sm"
                onkeydown={(event: KeyboardEvent) => {
                    if (event.key === 'Escape') {
                        onCancel?.();
                    }
                }}
            />
            <Button type="submit" size="sm" disabled={processing}>
                {submitLabel}
            </Button>
            {#if onCancel}
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onclick={() => onCancel?.()}
                >
                    Cancel
                </Button>
            {/if}
        </div>
        <InputError message={errors.name} />
    {/snippet}
</Form>
