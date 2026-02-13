<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    masterKey: String,
    title: String,
    items: Array,
});

function destroy(id) {
    if (!confirm('この項目を削除してもよろしいですか？')) return;
    router.delete(route('master.destroy', { masterKey: props.masterKey, id }));
}
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-xl font-semibold text-slate-800 tracking-tight">{{ title }}</h1>
                <Link
                    :href="route('master.create', { masterKey })"
                    class="inline-flex items-center rounded-lg bg-slate-800 px-3 py-2 text-center text-sm font-medium text-white hover:bg-slate-700"
                >
                    追加
                </Link>
            </div>
        </template>

        <div class="max-w-4xl">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div v-if="items.length === 0" class="p-8 text-center text-slate-500">
                    データがありません。追加ボタンから登録してください。
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">表示名</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">並び順</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">有効</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">操作</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <tr v-for="item in items" :key="item.id" class="hover:bg-slate-50">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600">{{ item.id }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-800">{{ item.name }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600">{{ item.sort_order }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span
                                        class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full"
                                        :class="item.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                                    >
                                        {{ item.is_active ? '有効' : '無効' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                                    <Link
                                        :href="route('master.show', { masterKey, id: item.id })"
                                        class="text-slate-600 hover:text-slate-900 mr-3"
                                    >
                                        詳細
                                    </Link>
                                    <Link
                                        :href="route('master.edit', { masterKey, id: item.id })"
                                        class="text-slate-600 hover:text-slate-900 mr-3"
                                    >
                                        編集
                                    </Link>
                                    <button
                                        type="button"
                                        class="text-red-600 hover:text-red-800"
                                        @click="destroy(item.id)"
                                    >
                                        削除
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
