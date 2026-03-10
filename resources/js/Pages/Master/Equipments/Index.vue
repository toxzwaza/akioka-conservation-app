<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EquipmentTreeRow from '@/Components/EquipmentTreeRow.vue';
import PlusIcon from '@/Components/Icons/PlusIcon.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    tree: { type: Array, default: () => [] },
});

const expandedIds = ref(new Set());

function toggle(id) {
    const next = new Set(expandedIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    expandedIds.value = next;
}

function destroy(id, event) {
    event?.stopPropagation();
    if (!confirm('この設備を削除してもよろしいですか？')) return;
    router.delete(route('master.equipments.destroy', id));
}
</script>

<template>
    <Head title="設備" />

    <AuthenticatedLayout>
        <div class="max-w-full space-y-4">
            <div class="flex justify-end">
                <Link
                    :href="route('master.equipments.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-3 py-2 text-center text-sm font-medium text-white hover:bg-slate-700"
                >
                    <PlusIcon />
                    追加
                </Link>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div v-if="tree.length === 0" class="p-8 text-center text-slate-500">
                    データがありません。
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-10"></th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-16">画像</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">設備名</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">親設備</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">状態</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">操作</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <EquipmentTreeRow
                                v-for="node in tree"
                                :key="node.id"
                                :node="node"
                                :expanded-ids="expandedIds"
                                :depth="0"
                                @toggle="toggle"
                                @destroy="destroy"
                            />
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
