<script lang="ts">
	import { currentAppearance } from "@/lib/theme.svelte";
	import { Toaster, type ToasterProps as SonnerProps } from "svelte-sonner";
	import Loader2Icon from 'carbon-icons-svelte/lib/CircleDash.svelte';
	import CircleCheckIcon from 'carbon-icons-svelte/lib/CheckmarkOutline.svelte';
	import OctagonXIcon from 'carbon-icons-svelte/lib/Misuse.svelte';
	import InfoIcon from 'carbon-icons-svelte/lib/Information.svelte';
	import TriangleAlertIcon from 'carbon-icons-svelte/lib/WarningAlt.svelte';

	let { ...restProps }: SonnerProps = $props();
</script>

<Toaster
	theme={currentAppearance()}
	class="toaster group"
	style="--normal-bg: var(--color-popover); --normal-text: var(--color-popover-foreground); --normal-border: var(--color-border);"
	toastOptions={{
		classes: {
			toast: "cn-toast",
		},
	}}
	{...restProps}
>
	{#snippet loadingIcon()}
		<Loader2Icon class="size-4 animate-spin" />
	{/snippet}
	{#snippet successIcon()}
		<CircleCheckIcon class="size-4" />
	{/snippet}
	{#snippet errorIcon()}
		<OctagonXIcon class="size-4" />
	{/snippet}
	{#snippet infoIcon()}
		<InfoIcon class="size-4" />
	{/snippet}
	{#snippet warningIcon()}
		<TriangleAlertIcon class="size-4" />
	{/snippet}
</Toaster>
