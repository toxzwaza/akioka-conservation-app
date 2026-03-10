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
    vendors: Array,
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
    name: '',
    work_cost_category_id: '',
    amount: '',
    vendor_id: '',
    vendor_name: '',
    _vendor_name: '',
    occurred_at: '',
    note: '',
    _file: null,
});

const emptySummaryDocument = () => ({
    display_name: '',
    _file: null,
});

const emptyEquipmentRow = () => ({ parent_id: '', equipment_id: '' });
const emptyAdditionalUserRow = () => ({ user_id: '' });

const draftCommentMessage = ref('');

const form = useForm({
    title: '',
    equipments: [{ parent_id: '', equipment_id: '' }],
    work_status_id: '',
    work_priority_id: '',
    work_purpose_ids: [],
    assigned_user_id: '',
    additional_user_ids: [{ user_id: '' }],
    production_stop_minutes: '',
    occurred_at: '',
    completed_at: '',
    note: '',
    work_contents: [emptyWorkContent()],
    work_used_parts: [],
    work_costs: [emptyWorkCost()],
    summary_documents: [emptySummaryDocument()],
    comments: [],
});

function equipmentOptionsForParent(parentId) {
    if (!parentId) return [];
    const map = props.equipmentChildrenByParentId ?? {};
    return map[String(parentId)] ?? [];
}

function addEquipment() {
    form.equipments.push({ parent_id: '', equipment_id: '' });
}

function removeEquipment(index) {
    form.equipments.splice(index, 1);
}

function addAdditionalUser() {
    form.additional_user_ids.push({ user_id: '' });
}

function removeAdditionalUser(index) {
    form.additional_user_ids.splice(index, 1);
}

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

/** 作業内容の終了日時ボタン: この行の終了日時を現在にセット。作業概要の完了日時は未入力時のみセットし、ステータスは常に完了にする */
function setEndAndComplete(row) {
    const now = nowAsDatetimeLocal();
    row.ended_at = now;
    const completedAtEmpty = form.completed_at == null || String(form.completed_at).trim() === '';
    if (completedAtEmpty) {
        form.completed_at = now;
    }
    if (completedStatusId.value) {
        form.work_status_id = completedStatusId.value;
    }
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

/** 仮登録コメントを追加（作業登録時にまとめて登録される） */
function addComment() {
    const msg = (draftCommentMessage.value || '').trim();
    if (!msg) return;
    form.comments = form.comments || [];
    form.comments.push({ message: msg });
    draftCommentMessage.value = '';
}

function removeComment(index) {
    form.comments.splice(index, 1);
}

/** 業者入力: マスタと一致すれば vendor_id をセット、否则 vendor_id をクリア（送信時に vendor_name として送る） */
function onVendorInput(row) {
    const name = (row._vendor_name || '').trim();
    const found = (props.vendors || []).find((v) => v.name === name);
    row.vendor_id = found ? found.id : '';
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

function isFile(obj) {
    return obj != null && typeof File !== 'undefined' && obj instanceof File;
}

function getObjectUrl(file) {
    return isFile(file) && typeof URL !== 'undefined' ? URL.createObjectURL(file) : '';
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
            (form.work_costs || []).forEach((row, i) => {
                ['name', 'work_cost_category_id', 'amount', 'occurred_at', 'note'].forEach((k) => {
                    const v = row[k];
                    if (v != null && v !== '') fd.append(`work_costs[${i}][${k}]`, String(v));
                });
                if (row.vendor_id != null && row.vendor_id !== '') {
                    fd.append(`work_costs[${i}][vendor_id]`, String(row.vendor_id));
                } else if (row._vendor_name != null && String(row._vendor_name).trim() !== '') {
                    fd.append(`work_costs[${i}][vendor_name]`, String(row._vendor_name).trim());
                }
                if (row._file instanceof File) fd.append(`work_costs[${i}][file]`, row._file);
            });
        } else if (key === 'summary_documents') {
            (form.summary_documents || [])
                .filter((row) => row._file instanceof File)
                .forEach((row, i) => {
                    fd.append(`summary_documents[${i}][display_name]`, (row.display_name && String(row.display_name).trim()) || row._file.name);
                    fd.append(`summary_documents[${i}][file]`, row._file);
                });
        } else if (key === 'equipments') {
            (form.equipments || []).forEach((row, i) => {
                if (row.parent_id) fd.append(`equipments[${i}][parent_id]`, String(row.parent_id));
                if (row.equipment_id) fd.append(`equipments[${i}][equipment_id]`, String(row.equipment_id));
            });
        } else if (key === 'work_purpose_ids') {
            (form.work_purpose_ids || []).forEach((id) => fd.append('work_purpose_ids[]', String(id)));
        } else if (key === 'additional_user_ids') {
            (form.additional_user_ids || []).forEach((row, i) => {
                if (row.user_id) fd.append(`additional_user_ids[${i}][user_id]`, String(row.user_id));
            });
        } else if (key === 'comments') {
            (form.comments || []).forEach((row, i) => {
                const msg = (row.message || '').trim();
                if (msg) fd.append(`comments[${i}][message]`, msg);
            });
        } else if (key !== 'equipments' && key !== 'work_purpose_ids' && key !== 'additional_user_ids' && key !== 'comments' && value != null && value !== '') {
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

function normalizeWorkCostsVendor() {
    (form.work_costs || []).forEach((row) => {
        if (row.vendor_id == null || row.vendor_id === '') {
            row.vendor_name = (row._vendor_name && String(row._vendor_name).trim()) ? String(row._vendor_name).trim() : '';
        } else {
            row.vendor_name = '';
        }
    });
}

function submit() {
    normalizeUsedPartsQty();
    normalizeWorkCostsVendor();
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
        <div class="flex gap-6">
            <!-- 左: フォーム -->
            <div class="flex-1 min-w-0 max-w-full space-y-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- 作業概要 -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                        <h2 class="text-sm font-semibold text-slate-800">作業概要</h2>
                    </div>
                    <div class="p-4 space-y-6">
                        <div>
                            <InputLabel value="作業名" required />
                            <TextInput
                                v-model="form.title"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="form.errors.title" />
                        </div>

                        <!-- 設備（複数） -->
                        <div>
                            <InputLabel value="設備" required />
                            <div v-for="(row, idx) in form.equipments" :key="idx" class="flex flex-wrap gap-3 items-end mb-3">
                                <div class="flex-1 min-w-[120px]">
                                    <select
                                        v-model="row.parent_id"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        required
                                        @change="row.equipment_id = ''"
                                    >
                                        <option value="">親設備を選択</option>
                                        <option v-for="opt in parentEquipmentOptions" :key="opt.id" :value="String(opt.id)">{{ opt.name }}</option>
                                    </select>
                                </div>
                                <div class="flex-1 min-w-[120px]">
                                    <select
                                        v-model="row.equipment_id"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        required
                                        :disabled="!row.parent_id"
                                    >
                                        <option value="">{{ row.parent_id ? '選択' : '親設備を選択' }}</option>
                                        <option v-for="opt in equipmentOptionsForParent(row.parent_id)" :key="opt.id" :value="opt.id">{{ opt.display_label ?? opt.name }}</option>
                                    </select>
                                </div>
                                <button type="button" class="text-red-600 hover:text-red-800 p-1" :disabled="form.equipments.length <= 1" @click="removeEquipment(idx)" title="削除">×</button>
                            </div>
                            <SecondaryButton type="button" @click="addEquipment">＋ 設備を追加</SecondaryButton>
                            <InputError :message="form.errors.equipments" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="ステータス" required />
                                <select v-model="form.work_status_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="">選択</option>
                                    <option v-for="s in workStatuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                                <InputError :message="form.errors.work_status_id" />
                            </div>
                            <div>
                                <InputLabel value="優先度" />
                                <select v-model="form.work_priority_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">選択</option>
                                    <option v-for="p in workPriorities" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                                <InputError :message="form.errors.work_priority_id" />
                            </div>
                        </div>
                        <div class="w-full">
                            <InputLabel value="作業目的" required />
                            <div class="mt-1 flex flex-wrap gap-2 p-2 rounded-md border border-gray-300 bg-white min-h-[38px] w-full">
                                <label v-for="p in workPurposes" :key="p.id" class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="checkbox" :value="p.id" v-model="form.work_purpose_ids" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    <span class="text-sm">{{ p.name }}</span>
                                </label>
                            </div>
                            <InputError :message="form.errors.work_purpose_ids" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="主担当" required />
                                <select v-model="form.assigned_user_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="">選択</option>
                                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.api_name ?? u.name }}</option>
                                </select>
                                <InputError :message="form.errors.assigned_user_id" />
                            </div>
                            <div>
                                <InputLabel value="追加担当" />
                                <div v-for="(row, idx) in form.additional_user_ids" :key="idx" class="flex gap-2 items-center mb-2">
                                    <select v-model="row.user_id" class="flex-1 mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                                        <option value="">なし</option>
                                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.api_name ?? u.name }}</option>
                                    </select>
                                    <button type="button" class="text-red-600 hover:text-red-800 shrink-0" :disabled="form.additional_user_ids.length <= 1" @click="removeAdditionalUser(idx)">×</button>
                                </div>
                                <SecondaryButton type="button" class="text-sm" @click="addAdditionalUser">＋ 追加担当を追加</SecondaryButton>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <InputLabel value="停止時間（分）" />
                                <TextInput v-model="form.production_stop_minutes" type="number" min="0" class="mt-1 block w-full" />
                                <InputError :message="form.errors.production_stop_minutes" />
                            </div>
                            <div>
                                <InputLabel value="発生日" />
                                <TextInput v-model="form.occurred_at" type="datetime-local" class="mt-1 block w-full" />
                                <InputError :message="form.errors.occurred_at" />
                            </div>
                            <div>
                                <InputLabel value="完了日時" />
                                <TextInput v-model="form.completed_at" type="datetime-local" class="mt-1 block w-full" />
                                <InputError :message="form.errors.completed_at" />
                            </div>
                        </div>

                        <div>
                            <InputLabel value="詳細" />
                            <textarea v-model="form.note" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                            <InputError :message="form.errors.note" />
                        </div>
                    </div>
                </div>

                <!-- 作業内容 -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                        <h2 class="text-sm font-semibold text-slate-800">作業内容</h2>
                    </div>
                    <div class="p-4">
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
                                <div>
                                    <InputLabel value="作業タグ" required />
                                    <div class="mt-1 flex flex-wrap gap-2 p-2 rounded-md border border-gray-300 bg-white min-h-[38px] w-full">
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
                                <!-- 修理内容：非表示（カラムは残す） -->
                                <div class="hidden">
                                    <InputLabel value="修理内容" />
                                    <div class="mt-1 flex flex-wrap gap-2 p-2 rounded-md border border-gray-300 bg-white min-h-[38px] w-full">
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
                                <div>
                                    <InputLabel value="内容" required />
                                    <textarea
                                        v-model="row.content"
                                        rows="3"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        placeholder="作業内容を入力"
                                    />
                                    <InputError :message="form.errors[`work_contents.${index}.content`]" />
                                </div>
                                <div>
                                    <InputLabel value="添付画像" />
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
                                                v-if="isFile(img)"
                                                :src="getObjectUrl(img)"
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
                                    <div class="flex gap-2 items-end">
                                        <div class="flex-1">
                                            <InputLabel value="開始日時" />
                                            <TextInput
                                                v-model="row.started_at"
                                                type="datetime-local"
                                                class="mt-1 block w-full"
                                            />
                                        </div>
                                        <button type="button" class="p-2 rounded-md border border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 shrink-0" title="発生日を開始日時に入力" @click="row.started_at = form.occurred_at ? (form.occurred_at.length >= 16 ? form.occurred_at.slice(0, 16) : form.occurred_at) : ''">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="flex gap-2 items-end">
                                        <div class="flex-1">
                                            <InputLabel value="終了日時" />
                                            <TextInput
                                                v-model="row.ended_at"
                                                type="datetime-local"
                                                class="mt-1 block w-full"
                                            />
                                        </div>
                                        <button type="button" class="p-2 rounded-md border border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 shrink-0" title="終了日時に現在時刻をセット。完了日時が未入力ならセットし、ステータスを完了にする" @click="setEndAndComplete(row)">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <SecondaryButton type="button" class="mt-4" @click="addWorkContent">＋ 作業内容を追加</SecondaryButton>
                    </div>
                </div>

                <!-- 使用部品 -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                        <h2 class="text-sm font-semibold text-slate-800">使用部品</h2>
                    </div>
                    <div class="p-4">
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
                </div>

                <!-- 費用 -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                        <h2 class="text-sm font-semibold text-slate-800">費用</h2>
                    </div>
                    <div class="p-4">
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
                                        <InputLabel value="名称" />
                                        <TextInput v-model="row.name" type="text" class="mt-1 block w-full" placeholder="例: 部品費A" />
                                    </div>
                                    <div>
                                        <InputLabel value="費用カテゴリ" required />
                                        <select v-model="row.work_cost_category_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                            <option value="">選択</option>
                                            <option v-for="c in workCostCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel value="金額（円）" required />
                                        <TextInput v-model="row.amount" type="number" min="0" class="mt-1 block w-full" />
                                    </div>
                                    <div>
                                        <InputLabel value="業者" />
                                        <input
                                            v-model="row._vendor_name"
                                            type="text"
                                            list="vendors-datalist-create"
                                            placeholder="選択または入力"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                            @input="onVendorInput(row)"
                                        />
                                        <datalist id="vendors-datalist-create">
                                            <option v-for="v in (vendors || [])" :key="v.id" :value="v.name" />
                                        </datalist>
                                    </div>
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
                            <SecondaryButton type="button" class="mt-4" @click="addWorkCost">＋ 費用を追加</SecondaryButton>
                    </div>
                </div>

                <!-- 作業添付資料 -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                        <h2 class="text-sm font-semibold text-slate-800">作業添付資料</h2>
                    </div>
                    <div class="p-4">
                            <p class="text-xs text-slate-500 mb-4">PDF、Excel、Word、画像を登録できます。名前を入力すると、作業詳細でタグとして表示されます。</p>
                            <div
                                v-for="(row, index) in form.summary_documents"
                                :key="index"
                                class="mb-4 p-4 rounded-lg border border-slate-200 bg-slate-50/50 flex flex-wrap items-end gap-4"
                            >
                                <div class="flex-1 min-w-[180px]">
                                    <InputLabel value="表示名（タグ名）" />
                                    <TextInput v-model="row.display_name" type="text" class="mt-1 block w-full" placeholder="例: 取扱マニュアル" />
                                    <InputError :message="form.errors[`summary_documents.${index}.display_name`]" />
                                </div>
                                <div class="flex-1 min-w-[200px]">
                                    <InputLabel value="ファイル" required />
                                    <input type="file" accept=".pdf,.xlsx,.xls,.docx,.doc,.jpg,.jpeg,.png,.gif,.webp" class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-md file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200" @change="setSummaryDocFile(index, $event)" />
                                    <p v-if="row._file" class="mt-1 text-xs text-slate-500">{{ row._file.name }}</p>
                                    <InputError :message="form.errors[`summary_documents.${index}.file`]" />
                                </div>
                                <button type="button" class="text-xs text-slate-500 hover:text-red-600" @click="removeSummaryDocument(index)">削除</button>
                            </div>
                            <SecondaryButton type="button" class="mt-4" @click="addSummaryDocument">＋ 資料を追加</SecondaryButton>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <PrimaryButton :disabled="form.processing">登録</PrimaryButton>
                    <span v-if="form.processing" class="text-sm text-gray-600">送信中...</span>
                </div>
            </form>
            </div>

            <!-- 右: コメント（仮登録・作業登録時に同時に登録） -->
            <div class="w-[30%] min-w-[280px] shrink-0">
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden sticky top-4">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                        <h2 class="text-sm font-semibold text-slate-800">コメント（仮登録）</h2>
                        <p class="text-xs text-slate-500 mt-0.5">作業登録時に一緒に登録されます</p>
                    </div>
                    <div class="p-4 max-h-[70vh] overflow-y-auto">
                        <form @submit.prevent="addComment" class="mb-4">
                            <textarea
                                v-model="draftCommentMessage"
                                rows="3"
                                class="block w-full rounded-md border-slate-300 shadow-sm text-sm"
                                placeholder="コメントを入力..."
                            />
                            <SecondaryButton type="submit" class="mt-2" :disabled="!draftCommentMessage.trim()">追加</SecondaryButton>
                        </form>
                        <template v-if="(form.comments || []).length">
                            <div class="space-y-4">
                                <div
                                    v-for="(c, index) in form.comments"
                                    :key="index"
                                    class="text-sm border-b border-slate-100 pb-3 last:border-0"
                                >
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <span class="text-slate-500 text-xs">仮登録</span>
                                        <button
                                            type="button"
                                            class="text-xs text-slate-500 hover:text-red-600"
                                            @click="removeComment(index)"
                                        >削除</button>
                                    </div>
                                    <p class="text-slate-800 whitespace-pre-wrap">{{ c.message }}</p>
                                </div>
                            </div>
                        </template>
                        <p v-else class="text-slate-500 text-sm">コメントはありません。追加したコメントは作業登録時にまとめて登録されます。</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
