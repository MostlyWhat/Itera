<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Logout from 'carbon-icons-svelte/lib/Logout.svelte';
    import Settings from 'carbon-icons-svelte/lib/Settings.svelte';
    import { mergeProps } from 'bits-ui';
    import {
        DropdownMenuGroup,
        DropdownMenuItem,
        DropdownMenuLabel,
        DropdownMenuSeparator,
    } from '@/components/ui/dropdown-menu';
    import UserInfo from '@/components/UserInfo.svelte';
    import { toUrl } from '@/lib/utils';
    import { logout } from '@/routes';
    import { edit } from '@/routes/profile';
    import type { User } from '@/types';

    let {
        user,
    }: {
        user: User;
    } = $props();

    function handleLogout() {
        router.flushAll();
    }
</script>

<DropdownMenuLabel class="p-0 font-normal">
    <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
        <UserInfo {user} showEmail={true} />
    </div>
</DropdownMenuLabel>
<DropdownMenuSeparator />
<DropdownMenuGroup>
    <DropdownMenuItem>
        {#snippet child({ props })}
            <Link
                {...props}
                href={toUrl(edit())}
                prefetch
            >
                <Settings />
                Settings
            </Link>
        {/snippet}
    </DropdownMenuItem>
</DropdownMenuGroup>
<DropdownMenuSeparator />
<DropdownMenuItem>
    {#snippet child({ props })}
        <Link
            {...mergeProps(props, { onclick: handleLogout, class: 'w-full' })}
            href={logout()}
            as="button"
            data-test="logout-button"
        >
            <Logout />
            Log out
        </Link>
    {/snippet}
</DropdownMenuItem>
