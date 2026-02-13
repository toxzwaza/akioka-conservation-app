<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const masterMenus = [
    { key: 'api-test', label: 'APIテスト（Conservation API）', href: () => route('api-test.index') },
    { key: 'work-statuses', label: '作業ステータス', href: () => route('master.index', { masterKey: 'work-statuses' }) },
    { key: 'work-priorities', label: '優先度', href: () => route('master.index', { masterKey: 'work-priorities' }) },
    { key: 'work-purposes', label: '作業目的', href: () => route('master.index', { masterKey: 'work-purposes' }) },
    { key: 'work-content-tags', label: '作業タグ', href: () => route('master.index', { masterKey: 'work-content-tags' }) },
    { key: 'repair-types', label: '修理内容', href: () => route('master.index', { masterKey: 'repair-types' }) },
    { key: 'attachment-types', label: '添付種別', href: () => route('master.index', { masterKey: 'attachment-types' }) },
    { key: 'work-activity-types', label: '操作履歴種別', href: () => route('master.index', { masterKey: 'work-activity-types' }) },
    { key: 'work-cost-categories', label: '費用カテゴリ', href: () => route('master.index', { masterKey: 'work-cost-categories' }) },
    { key: 'users', label: 'ユーザー', href: () => route('master.users.index') },
    { key: 'parts', label: '部品', href: () => route('master.parts.index') },
    { key: 'equipments', label: '設備', href: () => route('master.equipments.index') },
];
</script>

<template>
    <Head title="マスタ" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-slate-800 tracking-tight">マスタ</h1>
        </template>

        <div class="max-w-4xl">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <ul class="divide-y divide-slate-200">
                    <li v-for="menu in masterMenus" :key="menu.key">
                        <Link
                            :href="menu.href()"
                            class="block px-6 py-4 text-slate-700 hover:bg-slate-50 hover:text-slate-900"
                        >
                            {{ menu.label }}
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
