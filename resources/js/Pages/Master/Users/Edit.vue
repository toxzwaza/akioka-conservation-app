<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { BADGE_COLOR_OPTIONS, toFormColor } from '@/Constants/badgeColors';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    item: Object,
});

const form = useForm({
    name: props.item.name,
    color: toFormColor(props.item.color),
    email: props.item.email ?? '',
});
</script>

<template>
    <Head title="ユーザー - 編集" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-2">
                <Link :href="route('master.users.index')" class="text-slate-600 hover:text-slate-900 text-sm">← ユーザー一覧</Link>
                <span class="text-slate-400">/</span>
                <Link :href="route('master.users.show', item.id)" class="text-slate-600 hover:text-slate-900 text-sm">詳細</Link>
                <span class="text-slate-400">/</span>
                <h1 class="text-xl font-semibold text-slate-800 tracking-tight">ユーザー - 編集</h1>
            </div>
        </template>

        <div class="max-w-2xl">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
                <form @submit.prevent="form.put(route('master.users.update', item.id))" class="space-y-6">
                    <div>
                        <InputLabel value="氏名" />
                        <TextInput v-model="form.name" type="text" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="表示色" />
                        <select
                            v-model="form.color"
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"
                        >
                            <option v-for="opt in BADGE_COLOR_OPTIONS" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.color" />
                    </div>
                    <div>
                        <InputLabel value="メールアドレス" />
                        <TextInput v-model="form.email" type="email" class="mt-1 block w-full" />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="flex gap-3">
                        <PrimaryButton type="submit" :disabled="form.processing">更新</PrimaryButton>
                        <Link
                            :href="route('master.users.show', item.id)"
                            class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            キャンセル
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
