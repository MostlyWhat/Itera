<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
        DialogTrigger,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { store } from '@/routes/teams';

    // Props from the dialog trigger. Spread them on the trigger element.
    type TriggerProps = Record<string, unknown>;

    // `open` is bindable, so a menu item can open the dialog without being
    // its trigger. A trigger inside a menu would close with the menu.
    let {
        children: trigger,
        open = $bindable(false),
    }: {
        children?: Snippet<[TriggerProps]>;
        open?: boolean;
    } = $props();
    let formKey = $state(0);

    function handleOpenChange(value: boolean) {
        open = value;

        if (!value) {
            formKey++;
        }
    }
</script>

<Dialog {open} onOpenChange={handleOpenChange}>
    {#if trigger}
        <DialogTrigger>
            {#snippet child({ props })}
                {@render trigger(props)}
            {/snippet}
        </DialogTrigger>
    {/if}
    <DialogContent>
        {#key formKey}
            <Form
                {...store.form()}
                class="space-y-6"
                onSuccess={() => (open = false)}
            >
                {#snippet children({ errors, processing })}
                    <div class="space-y-3">
                        <DialogTitle>Create a new team</DialogTitle>
                        <DialogDescription>
                            Create a new team to collaborate with others.
                        </DialogDescription>
                    </div>

                    <div class="grid gap-2">
                        <Label for="name">Team name</Label>
                        <Input
                            id="name"
                            name="name"
                            placeholder="My team"
                            required
                            data-test="create-team-name"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <DialogFooter class="gap-2">
                        <DialogClose>
                            {#snippet child({ props })}
                                <Button
                                    {...props}
                                    variant="secondary"
                                >
                                    Cancel
                                </Button>
                            {/snippet}
                        </DialogClose>

                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="create-team-submit">Create team</Button
                        >
                    </DialogFooter>
                {/snippet}
            </Form>
        {/key}
    </DialogContent>
</Dialog>
