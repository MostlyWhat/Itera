<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { cn } from '@/lib/utils';

    // A one-field form for the name of an Area or a Project. It creates a
    // new record (empty `value`) or renames one (`value` is the current name).
    let {
        action,
        value = '',
        label,
        placeholder = '',
        submitLabel,
        focus = false,
        class: className = '',
        onDone,
        onCancel,
    }: {
        action: { action: string; method: 'get' | 'post' };
        value?: string;
        label: string;
        placeholder?: string;
        submitLabel: string;
        focus?: boolean;
        class?: string;
        onDone?: () => void;
        onCancel?: () => void;
    } = $props();

    const id = $props.id();
    let input = $state<HTMLInputElement | null>(null);

    // Focus after the next frame, so a closing menu does not take the focus back.
    $effect(() => {
        if (focus && input) {
            const frame = requestAnimationFrame(() => {
                input?.focus();
                input?.select();
            });

            return () => cancelAnimationFrame(frame);
        }
    });
</script>

<Form
    {...action}
    class={cn('flex w-full flex-col gap-1.5', className)}
    options={{ preserveScroll: true }}
    resetOnSuccess={value === ''}
    onSuccess={() => onDone?.()}
>
    {#snippet children({ errors, processing })}
        <div class="flex w-full flex-wrap items-center gap-2">
            <label class="sr-only" for={id}>{label}</label>
            <Input
                {id}
                bind:ref={input}
                name="name"
                {value}
                {placeholder}
                maxlength={100}
                required
                autocomplete="off"
                aria-invalid={errors.name ? 'true' : undefined}
                aria-describedby={errors.name ? `${id}-error` : undefined}
                class="h-9 min-w-0 flex-1 sm:max-w-sm"
                onkeydown={(event: KeyboardEvent) => {
                    if (event.key === 'Escape') {
                        onCancel?.();
                    }
                }}
            />
            <Button type="submit" size="sm" class="h-9" disabled={processing}>
                {submitLabel}
            </Button>
            {#if onCancel}
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    class="h-9"
                    onclick={() => onCancel?.()}
                >
                    Cancel
                </Button>
            {/if}
        </div>
        <div id={`${id}-error`}>
            <InputError message={errors.name} />
        </div>
    {/snippet}
</Form>
