<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Badge from '@/Components/Badge.vue';
import PlusIcon from '@/Components/Icons/PlusIcon.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    works: Object,
    equipments: Array,
    workStatuses: Array,
    workPurposes: Array,
    users: Array,
    sort_key: { type: String, default: 'created_at' },
    sort_order: { type: String, default: 'desc' },
});

const sortKeys = {
    id: 'id',
    title: 'title',
    equipment: null,
    work_status_id: 'work_status_id',
    work_priority_id: 'work_priority_id',
    assigned_user_id: 'assigned_user_id',
    created_at: 'created_at',
};

function sortBy(key) {
    const sortKey = sortKeys[key];
    if (!sortKey) return;
    const nextOrder = props.sort_key === sortKey && props.sort_order === 'asc' ? 'desc' : 'asc';
    const params = { ...filterForm.data(), sort_key: sortKey, sort_order: nextOrder };
    router.get(route('work.works.index'), params, { preserveState: true });
}

function buildEquipmentPath(eq) {
    if (!eq) return [];
    const path = [];
    let cur = eq;
    while (cur) {
        path.unshift(cur);
        cur = cur.parent ?? null;
    }
    return path;
}

function equipmentPathDisplay(work) {
    const eqs = work.equipments ?? [];
    const first = eqs[0];
    if (!first) return '—';
    const path = buildEquipmentPath(first);
    return path.map((p) => p.name).join(' › ');
}

const filterForm = useForm({
    equipment_id: '',
    work_status_id: '',
    work_purpose_id: '',
    assigned_user_id: '',
    occurred_from: '',
    occurred_to: '',
});

function applyFilter() {
    filterForm.get(route('work.works.index'), {
        preserveState: true,
        onSuccess: () => {},
    });
}

function resetFilter() {
    filterForm.reset();
    router.get(route('work.works.index'));
}
</script>

<template>
    <Head title="作業一覧" />

    <AuthenticatedLayout>
        <div class="max-w-full space-y-4">
            <div class="flex justify-end">
                <Link :href="route('work.works.create')">
                    <PrimaryButton>
                        <PlusIcon />
                        作業新規登録
                    </PrimaryButton>
                </Link>
            </div>

            <!-- 絞り込み検索 -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden p-4">
                <h2 class="text-sm font-semibold text-slate-800 mb-3">絞り込み</h2>
                <form @submit.prevent="applyFilter" class="flex flex-wrap gap-3 items-end">
                    <div class="min-w-[140px]">
                        <label class="block text-xs text-slate-500 mb-1">設備</label>
                        <select v-model="filterForm.equipment_id" class="block w-full rounded-md border-slate-300 text-sm">
                            <option value="">すべて</option>
                            <option v-for="e in (equipments || [])" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>
                    <div class="min-w-[120px]">
                        <label class="block text-xs text-slate-500 mb-1">ステータス</label>
                        <select v-model="filterForm.work_status_id" class="block w-full rounded-md border-slate-300 text-sm">
                            <option value="">すべて</option>
                            <option v-for="s in (workStatuses || [])" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div class="min-w-[120px]">
                        <label class="block text-xs text-slate-500 mb-1">作業目的</label>
                        <select v-model="filterForm.work_purpose_id" class="block w-full rounded-md border-slate-300 text-sm">
                            <option value="">すべて</option>
                            <option v-for="p in (workPurposes || [])" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="min-w-[120px]">
                        <label class="block text-xs text-slate-500 mb-1">主担当</label>
                        <select v-model="filterForm.assigned_user_id" class="block w-full rounded-md border-slate-300 text-sm">
                            <option value="">すべて</option>
                            <option v-for="u in (users || [])" :key="u.id" :value="u.id">{{ u.api_name ?? u.name }}</option>
                        </select>
                    </div>
                    <div class="min-w-[140px]">
                        <label class="block text-xs text-slate-500 mb-1">発生日（から）</label>
                        <input v-model="filterForm.occurred_from" type="date" class="block w-full rounded-md border-slate-300 text-sm" />
                    </div>
                    <div class="min-w-[140px]">
                        <label class="block text-xs text-slate-500 mb-1">発生日（まで）</label>
                        <input v-model="filterForm.occurred_to" type="date" class="block w-full rounded-md border-slate-300 text-sm" />
                    </div>
                    <SecondaryButton type="submit">検索</SecondaryButton>
                    <button type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="resetFilter">クリア</button>
                </form>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase tracking-wider cursor-pointer hover:bg-slate-100 select-none"
                                    @click="sortBy('id')"
                                >
                                    <span class="inline-flex items-center gap-1">ID
                                        <template v-if="sort_key === 'id'">
                                            <span v-if="sort_order === 'asc'" class="text-indigo-600">↑</span>
                                            <span v-else class="text-indigo-600">↓</span>
                                        </template>
                                    </span>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase tracking-wider cursor-pointer hover:bg-slate-100 select-none"
                                    @click="sortBy('title')"
                                >
                                    <span class="inline-flex items-center gap-1">作業名
                                        <template v-if="sort_key === 'title'">
                                            <span v-if="sort_order === 'asc'" class="text-indigo-600">↑</span>
                                            <span v-else class="text-indigo-600">↓</span>
                                        </template>
                                    </span>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase tracking-wider">設備</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase tracking-wider cursor-pointer hover:bg-slate-100 select-none"
                                    @click="sortBy('work_status_id')"
                                >
                                    <span class="inline-flex items-center gap-1">ステータス
                                        <template v-if="sort_key === 'work_status_id'">
                                            <span v-if="sort_order === 'asc'" class="text-indigo-600">↑</span>
                                            <span v-else class="text-indigo-600">↓</span>
                                        </template>
                                    </span>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase tracking-wider cursor-pointer hover:bg-slate-100 select-none"
                                    @click="sortBy('work_priority_id')"
                                >
                                    <span class="inline-flex items-center gap-1">優先度
                                        <template v-if="sort_key === 'work_priority_id'">
                                            <span v-if="sort_order === 'asc'" class="text-indigo-600">↑</span>
                                            <span v-else class="text-indigo-600">↓</span>
                                        </template>
                                    </span>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase tracking-wider">作業目的</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase tracking-wider cursor-pointer hover:bg-slate-100 select-none"
                                    @click="sortBy('assigned_user_id')"
                                >
                                    <span class="inline-flex items-center gap-1">主担当
                                        <template v-if="sort_key === 'assigned_user_id'">
                                            <span v-if="sort_order === 'asc'" class="text-indigo-600">↑</span>
                                            <span v-else class="text-indigo-600">↓</span>
                                        </template>
                                    </span>
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-slate-600 uppercase tracking-wider cursor-pointer hover:bg-slate-100 select-none"
                                    @click="sortBy('created_at')"
                                >
                                    <span class="inline-flex items-center gap-1">登録日
                                        <template v-if="sort_key === 'created_at'">
                                            <span v-if="sort_order === 'asc'" class="text-indigo-600">↑</span>
                                            <span v-else class="text-indigo-600">↓</span>
                                        </template>
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <tr
                                v-for="work in works.data"
                                :key="work.id"
                                class="hover:bg-slate-50 cursor-pointer"
                                @click="router.visit(route('work.works.show', work.id))"
                            >
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    {{ work.id }}
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-800">
                                    <span class="text-indigo-600 hover:text-indigo-800">{{ work.title }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    <template v-if="(work.equipments || []).length">
                                        <template v-for="(eq, ei) in work.equipments" :key="eq.id">
                                            <span v-if="ei > 0" class="text-slate-300"> / </span>
                                            <template v-for="(p, pi) in buildEquipmentPath(eq)" :key="p.id">
                                                <span v-if="pi < buildEquipmentPath(eq).length - 1" class="text-slate-400">{{ p.name }} › </span>
                                                <span v-else class="font-medium text-slate-700">{{ p.name }}</span>
                                            </template>
                                        </template>
                                    </template>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <Badge :label="work.work_status?.name ?? '—'" :color="work.work_status?.color" />
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <Badge :label="work.work_priority?.name ?? '—'" :color="work.work_priority?.color" />
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span v-if="(work.work_purposes || work.workPurposes || []).length" class="flex flex-wrap gap-1">
                                        <Badge v-for="p in (work.work_purposes || work.workPurposes)" :key="p.id" :label="p.name" :color="p.color" />
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <Badge :label="work.assigned_user?.name ?? '—'" :color="work.assigned_user?.color" />
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    {{ work.created_at ? new Date(work.created_at).toLocaleDateString('ja-JP') : '—' }}
                                </td>
                            </tr>
                            <tr v-if="!works.data?.length">
                                <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
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
