<script lang="ts">
    import type { Snippet } from 'svelte';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { cn } from '@/lib/utils';

    // A dialog for one data-entry form. The page under it does not move.
    // Focus goes to the first field, Escape closes, and focus returns to the
    // button that opened it. The form inside closes it on success.
    let {
        open = $bindable(false),
        title,
        description,
        size = 'md',
        children,
    }: {
        open?: boolean;
        title: string;
        description?: string;
        size?: 'sm' | 'md' | 'lg';
        children: Snippet;
    } = $props();
</script>

<Dialog bind:open>
    <DialogContent
        class={cn(
            'max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain',
            { sm: 'sm:max-w-sm', md: 'sm:max-w-md', lg: 'sm:max-w-lg' }[size],
        )}
    >
        <DialogHeader>
            <DialogTitle>{title}</DialogTitle>
            {#if description}
                <DialogDescription>{description}</DialogDescription>
            {/if}
        </DialogHeader>
        {@render children()}
    </DialogContent>
</Dialog>
