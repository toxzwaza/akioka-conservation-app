<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    parentEquipmentOptions: Array,
    equipmentChildrenByParentId: Object,
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
    work_content_tag_ids: [],
    repair_type_ids: [],
    content: '',
    started_at: '',
    ended_at: '',
    _images: [],
});

const emptyWorkCost = () => ({
    work_cost_category_id: '',
    amount: '',
    vendor_name: '',
    occurred_at: '',
    note: '',
    _file: null,
});

const emptySummaryDocument = () => ({
    display_name: '',
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
    summary_documents: [emptySummaryDocument()],
});

const parentEquipmentId = ref('');
const equipmentOptionsForSelect = computed(() => {
    if (!parentEquipmentId.value) return [];
    const map = props.equipmentChildrenByParentId ?? {};
    return map[parentEquipmentId.value] ?? [];
});

watch(parentEquipmentId, () => {
    form.equipment_id = '';
});

/** 完了ステータスのIDを取得 */
const completedStatusId = computed(() => {
    const s = props.workStatuses?.find((x) => x.name === '完了');
    return s?.id ?? null;
});

/** 現在日時を datetime-local 形式で返す */
function nowAsDatetimeLocal() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const h = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `${y}-${m}-${day}T${h}:${min}`;
}

/** ステータスが「完了」に変更されたら完了日時を自動入力 */
watch(
    () => form.work_status_id,
    (newVal) => {
        if (completedStatusId.value && String(newVal) === String(completedStatusId.value)) {
            form.completed_at = nowAsDatetimeLocal();
        }
    }
);

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

function addSummaryDocument() {
    form.summary_documents.push(emptySummaryDocument());
}

function removeSummaryDocument(index) {
    form.summary_documents.splice(index, 1);
}

function setSummaryDocFile(index, event) {
    const file = event.target.files?.[0];
    if (form.summary_documents[index]) form.summary_documents[index]._file = file || null;
}

function setCostFile(index, event) {
    const file = event.target.files?.[0];
    if (form.work_costs[index]) form.work_costs[index]._file = file || null;
}

function setContentImages(contentIndex, event) {
    const files = event.target.files;
    if (!files?.length || !form.work_contents[contentIndex]) return;
    const current = form.work_contents[contentIndex]._images || [];
    form.work_contents[contentIndex]._images = [...current, ...Array.from(files)];
    event.target.value = '';
}

function removeContentImage(contentIndex, imageIndex) {
    const imgs = form.work_contents[contentIndex]?._images || [];
    imgs.splice(imageIndex, 1);
    form.work_contents[contentIndex]._images = [...imgs];
}

function buildFormData() {
    const fd = new FormData();
    const data = form.data();
    // work_contents は form を直接参照（data() は File を含まないため）
    const workContents = form.work_contents || [];
    workContents.forEach((row, i) => {
        const { _images, work_content_tag_ids, repair_type_ids, ...rest } = row;
        Object.entries(rest).forEach(([k, v]) => {
            if (v != null && v !== '') fd.append(`work_contents[${i}][${k}]`, String(v));
        });
        (work_content_tag_ids || []).forEach((id) => {
            if (id != null && id !== '') fd.append(`work_contents[${i}][work_content_tag_ids][]`, String(id));
        });
        (repair_type_ids || []).forEach((id) => {
            if (id != null && id !== '') fd.append(`work_contents[${i}][repair_type_ids][]`, String(id));
        });
        (row._images || []).forEach((file) => {
            if (file instanceof File) fd.append(`work_contents[${i}][images][]`, file);
        });
    });
    for (const [key, value] of Object.entries(data)) {
        if (key === 'work_contents') continue; // 上で処理済み
        if (key === 'work_used_parts') {
            (value || []).forEach((row, i) => {
                if (row.part_id == null || row.part_id === '') return;
                const n = Math.max(1, parseInt(String(row.qty), 10) || 1);
                fd.append(`work_used_parts[${i}][part_id]`, String(row.part_id));
                fd.append(`work_used_parts[${i}][qty]`, String(n));
                if (row.note != null && row.note !== '') fd.append(`work_used_parts[${i}][note]`, String(row.note));
            });
        } else if (key === 'work_costs') {
            // _file は form を直接参照（data() は File を含まないため）
            (form.work_costs || []).forEach((row, i) => {
                Object.entries(row).forEach(([k, v]) => {
                    if (k === '_file') return;
                    if (v != null && v !== '') fd.append(`work_costs[${i}][${k}]`, String(v));
                });
                if (row._file instanceof File) fd.append(`work_costs[${i}][file]`, row._file);
            });
        } else if (key === 'summary_documents') {
            (form.summary_documents || [])
                .filter((row) => row._file instanceof File)
                .forEach((row, i) => {
                    fd.append(`summary_documents[${i}][display_name]`, (row.display_name && String(row.display_name).trim()) || row._file.name);
                    fd.append(`summary_documents[${i}][file]`, row._file);
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
    const hasCostFile = form.work_costs.some((row) => row._file);
    const hasContentImages = form.work_contents.some((row) => row._images?.length);
    const hasSummaryDocs = form.summary_documents?.some((row) => row._file instanceof File);
    const hasFile = hasCostFile || hasContentImages || hasSummaryDocs;
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

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="親設備" />
                                <select
                                    v-model="parentEquipmentId"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required
                                >
                                    <option value="">選択してください</option>
                                    <option
                                        v-for="opt in parentEquipmentOptions"
                                        :key="opt.id"
                                        :value="String(opt.id)"
                                    >
                                        {{ opt.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.equipment_id" />
                            </div>
                            <div>
                                <InputLabel value="設備" />
                                <select
                                    v-model="form.equipment_id"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required
                                    :disabled="!parentEquipmentId"
                                >
                                    <option value="">{{ parentEquipmentId ? '選択してください' : '親設備を先に選択' }}</option>
                                    <option
                                        v-for="opt in equipmentOptionsForSelect"
                                        :key="opt.id"
                                        :value="opt.id"
                                    >
                                        {{ opt.display_label ?? opt.name }}
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-slate-500">└ で階層表示</p>
                                <InputError :message="form.errors.equipment_id" />
                            </div>
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
                                        {{ u.api_name ?? u.name }}
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
                                        {{ u.api_name ?? u.name }}
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

                        <!-- 作業添付資料（PDF, Excel, Word, 画像） -->
                        <div class="border-t border-slate-200 pt-6">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-sm font-semibold text-slate-800">作業添付資料</h2>
                                <SecondaryButton type="button" @click="addSummaryDocument">
                                    行を追加
                                </SecondaryButton>
                            </div>
                            <p class="text-xs text-slate-500 mb-4">PDF、Excel、Word、画像を登録できます。名前を入力すると、作業詳細でタグとして表示されます。</p>
                            <div
                                v-for="(row, index) in form.summary_documents"
                                :key="index"
                                class="mb-4 p-4 rounded-lg border border-slate-200 bg-slate-50/50 flex flex-wrap items-end gap-4"
                            >
                                <div class="flex-1 min-w-[180px]">
                                    <InputLabel value="表示名（タグ名）" />
                                    <TextInput
                                        v-model="row.display_name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="例: 取扱マニュアル"
                                    />
                                    <InputError :message="form.errors[`summary_documents.${index}.display_name`]" />
                                </div>
                                <div class="flex-1 min-w-[200px]">
                                    <InputLabel value="ファイル" />
                                    <input
                                        type="file"
                                        :accept="'.pdf,.xlsx,.xls,.docx,.doc,.jpg,.jpeg,.png,.gif,.webp'"
                                        class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-md file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                                        @change="setSummaryDocFile(index, $event)"
                                    />
                                    <p v-if="row._file" class="mt-1 text-xs text-slate-500">{{ row._file.name }}</p>
                                    <InputError :message="form.errors[`summary_documents.${index}.file`]" />
                                </div>
                                <button
                                    type="button"
                                    class="text-xs text-slate-500 hover:text-red-600"
                                    @click="removeSummaryDocument(index)"
                                >
                                    削除
                                </button>
                            </div>
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
                                        <InputLabel value="作業タグ（複数選択可）" />
                                        <div class="mt-1 flex flex-wrap gap-2 p-2 rounded-md border border-gray-300 bg-white min-h-[38px]">
                                            <label
                                                v-for="t in workContentTags"
                                                :key="t.id"
                                                class="inline-flex items-center gap-1.5 cursor-pointer"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :value="t.id"
                                                    v-model="row.work_content_tag_ids"
                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <span class="text-sm">{{ t.name }}</span>
                                            </label>
                                        </div>
                                        <InputError :message="form.errors[`work_contents.${index}.work_content_tag_ids`]" />
                                    </div>
                                    <div>
                                        <InputLabel value="修理内容（複数選択可）" />
                                        <div class="mt-1 flex flex-wrap gap-2 p-2 rounded-md border border-gray-300 bg-white min-h-[38px]">
                                            <label
                                                v-for="r in repairTypes"
                                                :key="r.id"
                                                class="inline-flex items-center gap-1.5 cursor-pointer"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :value="r.id"
                                                    v-model="row.repair_type_ids"
                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <span class="text-sm">{{ r.name }}</span>
                                            </label>
                                        </div>
                                        <InputError :message="form.errors[`work_contents.${index}.repair_type_ids`]" />
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
                                <div>
                                    <InputLabel value="添付画像（複数登録可）" />
                                    <input
                                        type="file"
                                        accept="image/*"
                                        multiple
                                        class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-md file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                                        @change="setContentImages(index, $event)"
                                    />
                                    <div v-if="row._images?.length" class="mt-2 flex flex-wrap gap-2">
                                        <div
                                            v-for="(img, imgIdx) in row._images"
                                            :key="imgIdx"
                                            class="relative inline-block"
                                        >
                                            <img
                                                v-if="img instanceof File"
                                                :src="URL.createObjectURL(img)"
                                                alt="プレビュー"
                                                class="h-20 w-20 object-cover rounded border border-slate-200"
                                            />
                                            <button
                                                type="button"
                                                class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs leading-none flex items-center justify-center hover:bg-red-600"
                                                @click="removeContentImage(index, imgIdx)"
                                            >
                                                ×
                                            </button>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">JPEG, PNG, GIF など（1枚10MBまで）</p>
                                    <InputError :message="form.errors[`work_contents.${index}.images`]" />
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
