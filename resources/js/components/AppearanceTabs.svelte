<script lang="ts">
    import Screen from 'carbon-icons-svelte/lib/Screen.svelte';
    import Moon from 'carbon-icons-svelte/lib/Moon.svelte';
    import Sun from 'carbon-icons-svelte/lib/Sun.svelte';
    import type { Component, SvelteComponent } from 'svelte';
    import { themeState } from '@/lib/theme.svelte';
    import type { Appearance } from '@/types';

    const { appearance, updateAppearance } = themeState();

    type IconComponent =
        | Component<{ class?: string }>
        | (new (...args: any[]) => SvelteComponent<{ class?: string }>);

    const tabs: { value: Appearance; Icon: IconComponent; label: string }[] = [
        { value: 'light', Icon: Sun, label: 'Light' },
        { value: 'dark', Icon: Moon, label: 'Dark' },
        { value: 'system', Icon: Screen, label: 'System' },
    ];

    function handleAppearanceChange(value: Appearance) {
        updateAppearance(value);
    }
</script>

<div
    class="inline-flex gap-1 rounded-lg bg-muted p-1"
>
    {#each tabs as { value, Icon, label } (value)}
        <button
            onclick={() => handleAppearanceChange(value)}
            class="flex items-center rounded-md px-3.5 py-1.5 transition-colors {appearance.value ===
            value
                ? 'bg-background text-foreground shadow-xs'
                : 'text-muted-foreground hover:bg-background/60 hover:text-foreground'}"
        >
            <Icon class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{label}</span>
        </button>
    {/each}
</div>
