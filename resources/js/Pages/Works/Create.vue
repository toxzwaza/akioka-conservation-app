<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    equipmentOptions: Array,
    workStatuses: Array,
    workPriorities: Array,
    workPurposes: Array,
    workContentTags: Array,
    repairTypes: Array,
    parts: Array,
    workCostCategories: Array,
    users: Array,
});

const emptyWorkContent = () => ({
    work_content_tag_id: '',
    repair_type_id: '',
    content: '',
    started_at: '',
    ended_at: '',
});

const emptyWorkCost = () => ({
    work_cost_category_id: '',
    amount: '',
    vendor_name: '',
    occurred_at: '',
    note: '',
    _file: null,
});

const form = useForm({
    title: '',
    equipment_id: '',
    work_status_id: '',
    work_priority_id: '',
    work_purpose_id: '',
    assigned_user_id: '',
    additional_user_id: '',
    production_stop_minutes: '',
    occurred_at: '',
    started_at: '',
    completed_at: '',
    note: '',
    work_contents: [emptyWorkContent()],
    work_used_parts: [],
    work_costs: [emptyWorkCost()],
});

const partSearchQuery = ref('');
const partSearchResults = ref([]);
const partSearchLoading = ref(false);
const partAddQty = ref(1);

async function searchParts() {
    partSearchLoading.value = true;
    partSearchResults.value = [];
    try {
        const { data } = await axios.get(route('work.parts.search'), {
            params: { q: partSearchQuery.value },
        });
        partSearchResults.value = data.items ?? [];
    } catch {
        partSearchResults.value = [];
    } finally {
        partSearchLoading.value = false;
    }
}

function addUsedPartFromSearch(part) {
    const qty = Math.max(1, parseInt(partAddQty.value, 10) || 1);
    form.work_used_parts.push({
        part_id: part.id,
        qty,
        note: '',
        _part_no: part.part_no,
        _part_name: part.display_name ?? part.name,
        _part_s_name: part.s_name ?? part.part_no ?? '—',
    });
}

function addWorkContent() {
    form.work_contents.push(emptyWorkContent());
}

function removeWorkContent(index) {
    form.work_contents.splice(index, 1);
}

function removeUsedPart(index) {
    form.work_used_parts.splice(index, 1);
}

function addWorkCost() {
    form.work_costs.push(emptyWorkCost());
}

function removeWorkCost(index) {
    form.work_costs.splice(index, 1);
}

function setCostFile(index, event) {
    const file = event.target.files?.[0];
    if (form.work_costs[index]) form.work_costs[index]._file = file || null;
}

function buildFormData() {
    const fd = new FormData();
    const data = form.data();
    for (const [key, value] of Object.entries(data)) {
        if (key === 'work_contents') {
            (value || []).forEach((row, i) => {
                Object.entries(row).forEach(([k, v]) => {
                    if (v != null && v !== '') fd.append(`work_contents[${i}][${k}]`, String(v));
                });
            });
        } else if (key === 'work_used_parts') {
            (value || []).forEach((row, i) => {
                if (row.part_id == null || row.part_id === '') return;
                const n = Math.max(1, parseInt(String(row.qty), 10) || 1);
                fd.append(`work_used_parts[${i}][part_id]`, String(row.part_id));
                fd.append(`work_used_parts[${i}][qty]`, String(n));
                if (row.note != null && row.note !== '') fd.append(`work_used_parts[${i}][note]`, String(row.note));
            });
        } else if (key === 'work_costs') {
            (value || []).forEach((row, i) => {
                Object.entries(row).forEach(([k, v]) => {
                    if (k === '_file') return;
                    if (v != null && v !== '') fd.append(`work_costs[${i}][${k}]`, String(v));
                });
                if (row._file instanceof File) fd.append(`work_costs[${i}][file]`, row._file);
            });
        } else if (value != null && value !== '') {
            fd.append(key, String(value));
        }
    }
    return fd;
}

function normalizeUsedPartsQty() {
    form.work_used_parts = form.work_used_parts.map((row) => ({
        ...row,
        qty: Math.max(1, parseInt(String(row.qty), 10) || 1),
    }));
}

function submit() {
    normalizeUsedPartsQty();
    const hasFile = form.work_costs.some((row) => row._file);
    if (hasFile) {
        form.processing = true;
        router.post(route('work.works.store'), buildFormData(), {
            forceFormData: true,
            onFinish: () => { form.processing = false; },
            onError: (errors) => { form.errors = errors; },
        });
    } else {
        form.post(route('work.works.store'));
    }
}
</script>

<template>
    <Head title="作業登録" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-slate-800 tracking-tight">作業登録</h1>
        </template>

        <div class="max-w-3xl">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden p-6">
                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <InputLabel value="作業名" />
                            <TextInput
                                v-model="form.title"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="form.errors.title" />
                        </div>

                        <div>
                            <InputLabel value="設備" />
                            <select
                                v-model="form.equipment_id"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                required
                            >
                                <option value="">選択してください</option>
                                <option
                                    v-for="opt in equipmentOptions"
                                    :key="opt.id"
                                    :value="opt.id"
                                >
                                    {{ opt.display_label ?? opt.name }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">階層構造で表示されています。</p>
                            <InputError :message="form.errors.equipment_id" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <InputLabel value="ステータス" />
                                <select
                                    v-model="form.work_status_id"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required
                                >
                                    <option value="">選択</option>
                                    <option
                                        v-for="s in workStatuses"
                                        :key="s.id"
                                        :value="s.id"
                                    >
                                        {{ s.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.work_status_id" />
                            </div>
                            <div>
                                <InputLabel value="優先度" />
                                <select
                                    v-model="form.work_priority_id"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required
                                >
                                    <option value="">選択</option>
                                    <option
                                        v-for="p in workPriorities"
                                        :key="p.id"
                                        :value="p.id"
                                    >
                                        {{ p.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.work_priority_id" />
                            </div>
                            <div>
                                <InputLabel value="作業目的" />
                                <select
                                    v-model="form.work_purpose_id"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required
                                >
                                    <option value="">選択</option>
                                    <option
                                        v-for="p in workPurposes"
                                        :key="p.id"
                                        :value="p.id"
                                    >
                                        {{ p.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.work_purpose_id" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="主担当" />
                                <select
                                    v-model="form.assigned_user_id"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required
                                >
                                    <option value="">選択</option>
                                    <option
                                        v-for="u in users"
                                        :key="u.id"
                                        :value="u.id"
                                    >
                                        {{ u.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.assigned_user_id" />
                            </div>
                            <div>
                                <InputLabel value="追加担当" />
                                <select
                                    v-model="form.additional_user_id"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                >
                                    <option value="">なし</option>
                                    <option
                                        v-for="u in users"
                                        :key="u.id"
                                        :value="u.id"
                                    >
                                        {{ u.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.additional_user_id" />
                            </div>
                        </div>

                        <div>
                            <InputLabel value="停止時間(分)" />
                            <TextInput
                                v-model="form.production_stop_minutes"
                                type="number"
                                min="0"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.production_stop_minutes" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <InputLabel value="発生日" />
                                <TextInput
                                    v-model="form.occurred_at"
                                    type="datetime-local"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.occurred_at" />
                            </div>
                            <div>
                                <InputLabel value="開始日時" />
                                <TextInput
                                    v-model="form.started_at"
                                    type="datetime-local"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.started_at" />
                            </div>
                            <div>
                                <InputLabel value="完了日時" />
                                <TextInput
                                    v-model="form.completed_at"
                                    type="datetime-local"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.completed_at" />
                            </div>
                        </div>

                        <div>
                            <InputLabel value="備考" />
                            <textarea
                                v-model="form.note"
                                rows="3"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            />
                            <InputError :message="form.errors.note" />
                        </div>

                        <!-- 作業内容登録 -->
                        <div class="border-t border-slate-200 pt-6">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-sm font-semibold text-slate-800">作業内容</h2>
                                <SecondaryButton type="button" @click="addWorkContent">
                                    行を追加
                                </SecondaryButton>
                            </div>
                            <div
                                v-for="(row, index) in form.work_contents"
                                :key="index"
                                class="mb-6 p-4 rounded-lg border border-slate-200 bg-slate-50/50 space-y-4"
                            >
                                <div class="flex justify-end">
                                    <button
                                        type="button"
                                        @click="removeWorkContent(index)"
                                        class="text-xs text-slate-500 hover:text-red-600"
                                    >
                                        削除
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel value="作業タグ" />
                                        <select
                                            v-model="row.work_content_tag_id"
                                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        >
                                            <option value="">選択</option>
                                            <option
                                                v-for="t in workContentTags"
                                                :key="t.id"
                                                :value="t.id"
                                            >
                                                {{ t.name }}
                                            </option>
                                        </select>
                                        <InputError :message="form.errors[`work_contents.${index}.work_content_tag_id`]" />
                                    </div>
                                    <div>
                                        <InputLabel value="修理内容" />
                                        <select
                                            v-model="row.repair_type_id"
                                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        >
                                            <option value="">選択</option>
                                            <option
                                                v-for="r in repairTypes"
                                                :key="r.id"
                                                :value="r.id"
                                            >
                                                {{ r.name }}
                                            </option>
                                        </select>
                                        <InputError :message="form.errors[`work_contents.${index}.repair_type_id`]" />
                                    </div>
                                </div>
                                <div>
                                    <InputLabel value="内容" />
                                    <textarea
                                        v-model="row.content"
                                        rows="3"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        placeholder="作業内容を入力"
                                    />
                                    <InputError :message="form.errors[`work_contents.${index}.content`]" />
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel value="開始日時" />
                                        <TextInput
                                            v-model="row.started_at"
                                            type="datetime-local"
                                            class="mt-1 block w-full"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel value="終了日時" />
                                        <TextInput
                                            v-model="row.ended_at"
                                            type="datetime-local"
                                            class="mt-1 block w-full"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 使用部品登録（検索して追加） -->
                        <div class="border-t border-slate-200 pt-6">
                            <h2 class="text-sm font-semibold text-slate-800 mb-4">使用部品</h2>
                            <div class="mb-4 flex flex-wrap items-end gap-3">
                                <div class="flex-1 min-w-[200px]">
                                    <InputLabel value="部品を検索（部品名・型番）" />
                                    <TextInput
                                        v-model="partSearchQuery"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="例: サンプル または P001"
                                        @keydown.enter.prevent="searchParts"
                                    />
                                </div>
                                <div class="w-20">
                                    <InputLabel value="数量" />
                                    <TextInput
                                        v-model.number="partAddQty"
                                        type="number"
                                        min="1"
                                        step="1"
                                        class="mt-1 block w-full"
                                    />
                                </div>
                                <SecondaryButton type="button" :disabled="partSearchLoading" @click="searchParts">
                                    {{ partSearchLoading ? '検索中...' : '検索' }}
                                </SecondaryButton>
                            </div>
                            <div v-if="partSearchResults.length > 0" class="mb-4 overflow-x-auto rounded-lg border border-slate-200">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">部品番号</th>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">部品名</th>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">型番</th>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">格納先</th>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">アドレス</th>
                                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">現在の数量</th>
                                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 bg-white">
                                        <tr v-for="p in partSearchResults" :key="p.id" class="hover:bg-slate-50">
                                            <td class="px-4 py-2 text-slate-800">{{ p.part_no }}</td>
                                            <td class="px-4 py-2 text-slate-800">{{ p.display_name ?? p.name }}</td>
                                            <td class="px-4 py-2 text-slate-800">{{ p.s_name ?? '—' }}</td>
                                            <td class="px-4 py-2 text-slate-600">{{ (p.stock_storages && p.stock_storages[0]) ? p.stock_storages[0].location_name : '—' }}</td>
                                            <td class="px-4 py-2 text-slate-600">{{ (p.stock_storages && p.stock_storages[0]) ? p.stock_storages[0].address : '—' }}</td>
                                            <td class="px-4 py-2 text-right text-slate-800">{{ p.total_quantity != null ? Number(p.total_quantity).toLocaleString() : '—' }}</td>
                                            <td class="px-4 py-2 text-right">
                                                <button
                                                    type="button"
                                                    class="text-slate-600 hover:text-slate-900 font-medium"
                                                    @click="addUsedPartFromSearch(p)"
                                                >
                                                    追加
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else-if="partSearchQuery && !partSearchLoading" class="mb-4 text-sm text-slate-500">検索結果はありません。</p>
                            <div v-if="form.work_used_parts.length > 0" class="rounded-lg border border-slate-200 overflow-hidden">
                                <p class="bg-slate-50 px-4 py-2 text-xs font-medium text-slate-600">追加した部品</p>
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500">部品番号</th>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500">部品名</th>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500">型番</th>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 w-24">数量</th>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500">備考</th>
                                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-slate-500 w-16">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        <tr v-for="(row, index) in form.work_used_parts" :key="index">
                                            <td class="px-4 py-2 text-slate-800">{{ row._part_no }}</td>
                                            <td class="px-4 py-2 text-slate-800">{{ row._part_name }}</td>
                                            <td class="px-4 py-2 text-slate-800">{{ row._part_s_name ?? '—' }}</td>
                                            <td class="px-4 py-2">
                                                <TextInput
                                                    v-model.number="row.qty"
                                                    type="number"
                                                    min="1"
                                                    step="1"
                                                    class="block w-full text-sm"
                                                />
                                            </td>
                                            <td class="px-4 py-2">
                                                <TextInput v-model="row.note" type="text" class="block w-full text-sm" />
                                            </td>
                                            <td class="px-4 py-2 text-right">
                                                <button type="button" class="text-slate-500 hover:text-red-600" @click="removeUsedPart(index)">削除</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="text-sm text-slate-500">部品を検索して「追加」で使用部品に登録します。</p>
                        </div>

                        <!-- 費用登録 -->
                        <div class="border-t border-slate-200 pt-6">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-sm font-semibold text-slate-800">費用</h2>
                                <SecondaryButton type="button" @click="addWorkCost">
                                    行を追加
                                </SecondaryButton>
                            </div>
                            <div
                                v-for="(row, index) in form.work_costs"
                                :key="index"
                                class="mb-6 p-4 rounded-lg border border-slate-200 bg-slate-50/50 space-y-4"
                            >
                                <div class="flex justify-end">
                                    <button
                                        type="button"
                                        @click="removeWorkCost(index)"
                                        class="text-xs text-slate-500 hover:text-red-600"
                                    >
                                        削除
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel value="費用カテゴリ" />
                                        <select
                                            v-model="row.work_cost_category_id"
                                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        >
                                            <option value="">選択</option>
                                            <option
                                                v-for="c in workCostCategories"
                                                :key="c.id"
                                                :value="c.id"
                                            >
                                                {{ c.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <InputLabel value="金額（円）" />
                                        <TextInput
                                            v-model="row.amount"
                                            type="number"
                                            min="0"
                                            class="mt-1 block w-full"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <InputLabel value="業者名" />
                                    <TextInput
                                        v-model="row.vendor_name"
                                        type="text"
                                        class="mt-1 block w-full"
                                    />
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel value="発生日" />
                                        <TextInput
                                            v-model="row.occurred_at"
                                            type="date"
                                            class="mt-1 block w-full"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel value="備考" />
                                        <TextInput
                                            v-model="row.note"
                                            type="text"
                                            class="mt-1 block w-full"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <InputLabel value="添付ファイル（証憑など）" />
                                    <input
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png,.gif"
                                        class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-md file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                                        @change="setCostFile(index, $event)"
                                    />
                                    <p v-if="row._file" class="mt-1 text-xs text-slate-500">{{ row._file.name }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing">登録</PrimaryButton>
                            <span v-if="form.processing" class="text-sm text-gray-600">送信中...</span>
                        </div>
                    </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
