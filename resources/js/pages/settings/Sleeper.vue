<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem, type User } from '@/types';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Sleeper settings',
        href: '/settings/sleeper',
    },
];

const page = usePage();
const user = page.props.auth.user as User & { sleeper_username?: string | null; sleeper_user_id?: string | null };

const form = useForm({
    sleeper_username: user.sleeper_username ?? '',
});

const submit = () => {
    form.patch(route('sleeper.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Sleeper settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Sleeper account" description="Add your Sleeper username and user ID to enable integrations" />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="sleeper_username">Sleeper username</Label>
                        <Input id="sleeper_username" v-model="form.sleeper_username" class="mt-1 block w-full" placeholder="e.g. fantasygoat" />
                        <InputError :message="form.errors.sleeper_username" />
                    </div>

                    <div v-if="user.sleeper_user_id" class="grid gap-2">
                        <Label for="sleeper_user_id">Sleeper user ID</Label>
                        <Input id="sleeper_user_id" :model-value="user.sleeper_user_id" class="mt-1 block w-full" readonly />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Save</Button>

                        <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                            <p v-show="form.recentlySuccessful" class="text-sm text-neutral-600">Saved.</p>
                        </Transition>
                        <Link href="/leagues" class="text-sm underline">View leagues</Link>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>

</template>
