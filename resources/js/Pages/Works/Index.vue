<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    works: Object,
});
</script>

<template>
    <Head title="作業一覧" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h1 class="text-xl font-semibold text-slate-800 tracking-tight">作業一覧</h1>
                <Link :href="route('work.works.create')">
                    <PrimaryButton>作業新規登録</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="max-w-6xl">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase tracking-wider">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase tracking-wider">作業名</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase tracking-wider">設備</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase tracking-wider">ステータス</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase tracking-wider">優先度</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase tracking-wider">主担当</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase tracking-wider">登録日</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <tr
                                v-for="work in works.data"
                                :key="work.id"
                                class="hover:bg-slate-50"
                            >
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    {{ work.id }}
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-800">
                                    <Link :href="route('work.works.show', work.id)" class="text-indigo-600 hover:text-indigo-800 hover:underline">
                                        {{ work.title }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    {{ work.equipment?.name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    {{ work.work_status?.name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    {{ work.work_priority?.name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    {{ work.assigned_user?.name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    {{ work.created_at ? new Date(work.created_at).toLocaleDateString('ja-JP') : '—' }}
                                </td>
                            </tr>
                            <tr v-if="!works.data?.length">
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">
                                    登録された作業はありません。
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-if="works.data?.length && (works.prev_page_url || works.next_page_url)"
                    class="border-t border-slate-200 px-4 py-3 flex items-center justify-between"
                >
                    <p class="text-sm text-slate-600">
                        {{ works.from }} ～ {{ works.to }} 件目 / 全 {{ works.total }} 件
                    </p>
                    <div class="flex gap-2">
                        <Link
                            v-if="works.prev_page_url"
                            :href="works.prev_page_url"
                            class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                        >
                            前へ
                        </Link>
                        <Link
                            v-if="works.next_page_url"
                            :href="works.next_page_url"
                            class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                        >
                            次へ
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
