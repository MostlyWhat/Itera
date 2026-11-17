<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import FormDialog from '@/components/FormDialog.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';

    // A dialog with one name field: create or rename an Area or a Project.
    let {
        open = $bindable(false),
        title,
        description,
        action,
        label,
        value = '',
        placeholder = '',
        submitLabel,
    }: {
        open?: boolean;
        title: string;
        description?: string;
        action: { action: string; method: 'get' | 'post' };
        label: string;
        value?: string;
        placeholder?: string;
        submitLabel: string;
    } = $props();

    const id = $props.id();
</script>

<FormDialog bind:open {title} {description} size="sm">
    <Form
        {...action}
        class="flex flex-col gap-5"
        options={{ preserveScroll: true }}
        resetOnSuccess
        onSuccess={() => (open = false)}
    >
        {#snippet children({ errors, processing })}
            <div class="flex flex-col gap-2">
                <label for={id} class="text-sm font-medium">{label}</label>
                <Input
                    {id}
                    name="name"
                    {value}
                    {placeholder}
                    maxlength={100}
                    required
                    autocomplete="off"
                    aria-invalid={errors.name ? 'true' : undefined}
                />
                <InputError message={errors.name} />
            </div>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Button type="button" variant="ghost" onclick={() => (open = false)}>Cancel</Button>
                <Button type="submit" disabled={processing}>{submitLabel}</Button>
            </div>
        {/snippet}
    </Form>
</FormDialog>
