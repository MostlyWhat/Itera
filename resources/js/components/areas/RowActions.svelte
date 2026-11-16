<script lang="ts">
    import Archive from 'carbon-icons-svelte/lib/Archive.svelte';
    import Edit from 'carbon-icons-svelte/lib/Edit.svelte';
    import OverflowMenuVertical from 'carbon-icons-svelte/lib/OverflowMenuVertical.svelte';
    import { Button } from '@/components/ui/button';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';

    // The actions menu of one Area or Project row.
    let {
        label,
        onRename,
        onArchive,
    }: {
        label: string;
        onRename: () => void;
        onArchive: () => void;
    } = $props();
</script>

<DropdownMenu>
    <DropdownMenuTrigger>
        {#snippet child({ props })}
            <Button
                {...props}
                variant="ghost"
                size="icon"
                class="size-8 text-muted-foreground hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-accent-foreground"
                aria-label={`Actions for ${label}`}
            >
                <OverflowMenuVertical class="size-4" />
            </Button>
        {/snippet}
    </DropdownMenuTrigger>
    <DropdownMenuContent
        align="end"
        class="w-40"
        onCloseAutoFocus={(event: Event) => event.preventDefault()}
    >
        <DropdownMenuItem onSelect={onRename}>
            <Edit class="size-4" />
            Rename
        </DropdownMenuItem>
        <DropdownMenuItem onSelect={onArchive}>
            <Archive class="size-4" />
            Archive
        </DropdownMenuItem>
    </DropdownMenuContent>
</DropdownMenu>
