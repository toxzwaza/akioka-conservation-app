<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Badge from '@/Components/Badge.vue';
import PencilIcon from '@/Components/Icons/PencilIcon.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    masterKey: String,
    title: String,
    item: Object,
});
</script>

<template>
    <Head :title="`${title} - 詳細`" />

    <AuthenticatedLayout>
        <div class="max-w-2xl space-y-4">
            <div class="flex justify-end">
                <Link
                    :href="route('master.edit', { masterKey, id: item.id })"
                    class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-3 py-2 text-center text-sm font-medium text-white hover:bg-slate-700"
                >
                    <PencilIcon />
                    編集
                </Link>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <dl class="divide-y divide-slate-200">
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">ID</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.id }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">表示名</dt>
                        <dd class="mt-1 text-sm sm:mt-0 sm:col-span-2">
                            <Badge :label="item.name ?? '—'" :color="item.color" />
                        </dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">並び順</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.sort_order }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">有効</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">
                            <span
                                class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full"
                                :class="item.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                            >
                                {{ item.is_active ? '有効' : '無効' }}
                            </span>
                        </dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">登録日時</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.created_at }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">更新日時</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.updated_at }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
