<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    items: Array,
});

function destroy(id) {
    if (!confirm('このユーザーを削除してもよろしいですか？')) return;
    router.delete(route('master.users.destroy', id));
}
</script>

<template>
    <Head title="ユーザー" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-xl font-semibold text-slate-800 tracking-tight">ユーザー</h1>
                <Link
                    :href="route('master.users.create')"
                    class="inline-flex items-center rounded-lg bg-slate-800 px-3 py-2 text-center text-sm font-medium text-white hover:bg-slate-700"
                >
                    APIから追加
                </Link>
            </div>
        </template>

        <div class="max-w-full">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div v-if="items.length === 0" class="p-8 text-center text-slate-500">
                    データがありません。「APIから追加」で別システムから登録できます。
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">氏名</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">メールアドレス</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">操作</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <tr v-for="item in items" :key="item.id" class="hover:bg-slate-50">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600">{{ item.id }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <Badge :label="item.name ?? '—'" :color="item.color" />
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600">{{ item.email ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                                    <Link
                                        :href="route('master.users.show', item.id)"
                                        class="text-slate-600 hover:text-slate-900 mr-3"
                                    >
                                        詳細
                                    </Link>
                                    <Link
                                        :href="route('master.users.edit', item.id)"
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
