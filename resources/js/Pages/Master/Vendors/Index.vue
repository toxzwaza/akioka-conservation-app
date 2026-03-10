<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PlusIcon from '@/Components/Icons/PlusIcon.vue';
import draggable from 'vuedraggable';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    vendors: { type: Array, default: () => [] },
});

const vendorsList = ref([]);
watch(() => props.vendors, (val) => { vendorsList.value = val ? [...val] : []; }, { immediate: true });

function onReorder() {
    const order = vendorsList.value.map((v) => v.id);
    router.put(route('master.vendors.reorder'), { order }, { preserveScroll: true });
}

function destroy(id, event) {
    event?.stopPropagation();
    if (!confirm('この業者を削除してもよろしいですか？')) return;
    router.delete(route('master.vendors.destroy', id));
}
</script>

<template>
    <Head title="業者" />

    <AuthenticatedLayout>
        <div class="max-w-full space-y-4">
            <div class="flex justify-end">
                <Link
                    :href="route('master.vendors.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-3 py-2 text-center text-sm font-medium text-white hover:bg-slate-700"
                >
                    <PlusIcon />
                    追加
                </Link>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div v-if="!vendors.length" class="p-8 text-center text-slate-500">
                    データがありません。
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-10"></th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-16">ID</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">業者名</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-24">並び順</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-20">有効</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">操作</th>
                            </tr>
                        </thead>
                        <draggable
                            v-model="vendorsList"
                            tag="tbody"
                            item-key="id"
                            handle=".drag-handle"
                            class="bg-white divide-y divide-slate-200"
                            @end="onReorder"
                        >
                            <template #item="{ element: v }">
                                <tr class="hover:bg-slate-50/50">
                                    <td class="px-2 py-3 w-10">
                                        <div class="drag-handle shrink-0 w-6 h-6 flex items-center justify-center rounded cursor-move text-slate-400 hover:text-slate-600 hover:bg-slate-200/80" title="ドラッグで並び替え">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 6h2v2H8V6zm0 5h2v2H8v-2zm0 5h2v2H8v-2zm5-10h2v2h-2V6zm0 5h2v2h-2v-2zm0 5h2v2h-2v-2z"/></svg>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">{{ v.id }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ v.name }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600">{{ vendorsList.indexOf(v) + 1 }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span v-if="v.is_active" class="text-emerald-600">有効</span>
                                        <span v-else class="text-slate-400">無効</span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        <Link :href="route('master.vendors.edit', v.id)" class="text-indigo-600 hover:text-indigo-800 font-medium mr-3">編集</Link>
                                        <button type="button" class="text-red-600 hover:text-red-800 font-medium" @click="destroy(v.id, $event)">削除</button>
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
