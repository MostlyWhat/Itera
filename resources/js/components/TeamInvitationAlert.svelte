<script lang="ts">
    import Information from 'carbon-icons-svelte/lib/Information.svelte';
    import { Alert, AlertDescription } from '@/components/ui/alert';
    import type { TeamInvitationContext } from '@/types';

    let {
        invitation,
        action,
    }: {
        invitation: TeamInvitationContext;
        action: 'Log in' | 'Register';
    } = $props();
</script>

<div data-test="team-invitation-alert">
    <Alert
        class="border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/50 dark:text-blue-100 [&>svg]:text-blue-600 dark:[&>svg]:text-blue-400"
    >
        <Information class="size-4" />
        <AlertDescription class="text-blue-900 dark:text-blue-100">
            {action} to join the "{invitation.teamName}" team.
        </AlertDescription>
    </Alert>
</div>
