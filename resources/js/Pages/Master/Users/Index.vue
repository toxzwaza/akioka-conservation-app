<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Badge from '@/Components/Badge.vue';
import draggable from 'vuedraggable';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    items: Array,
});

const itemsList = ref([]);
watch(() => props.items, (val) => { itemsList.value = val ? [...val] : []; }, { immediate: true });

function onReorder() {
    const order = itemsList.value.map((item) => item.id);
    router.put(route('master.users.reorder'), { order }, { preserveScroll: true });
}

/** 表示用：外部IDがあればAPI情報、なければDBの値を表示 */
const displayItem = (item) => ({
    name: item.api_name ?? item.name ?? '—',
    email: item.api_email ?? item.email ?? '—',
});

function destroy(id) {
    if (!confirm('このユーザーを削除してもよろしいですか？')) return;
    router.delete(route('master.users.destroy', id));
}
</script>

<template>
    <Head title="ユーザー" />

    <AuthenticatedLayout>
        <div class="max-w-full space-y-4">
            <div class="flex justify-end">
                <Link
                    :href="route('master.users.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-3 py-2 text-center text-sm font-medium text-white hover:bg-slate-700"
                >
                    <PlusIcon />
                    APIから追加
                </Link>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div v-if="items.length === 0" class="p-8 text-center text-slate-500">
                    データがありません。「APIから追加」で別システムから登録できます。
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-10"></th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">並び順</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">氏名</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">メールアドレス</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">操作</th>
                            </tr>
                        </thead>
                        <draggable
                            v-model="itemsList"
                            tag="tbody"
                            item-key="id"
                            handle=".drag-handle"
                            class="bg-white divide-y divide-slate-200"
                            @end="onReorder"
                        >
                            <template #item="{ element: item }">
                                <tr class="hover:bg-slate-50">
                                    <td class="px-2 py-3 w-10">
                                        <div class="drag-handle shrink-0 w-6 h-6 flex items-center justify-center rounded cursor-move text-slate-400 hover:text-slate-600 hover:bg-slate-200/80" title="ドラッグで並び替え">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 6h2v2H8V6zm0 5h2v2H8v-2zm0 5h2v2H8v-2zm5-10h2v2h-2V6zm0 5h2v2h-2v-2zm0 5h2v2h-2v-2z"/></svg>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600">{{ item.id }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600">{{ itemsList.indexOf(item) + 1 }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <Badge :label="displayItem(item).name" :color="item.color" />
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600">{{ displayItem(item).email }}</td>
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
                            </template>
                        </draggable>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
