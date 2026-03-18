<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Badge from '@/Components/Badge.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import draggable from 'vuedraggable';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    work: Object,
    workContentTags: Array,
    repairTypes: Array,
    parts: Array,
    workCostCategories: Array,
    workStatuses: Array,
    workPriorities: Array,
    workPurposes: Array,
    parentEquipmentOptions: Array,
    equipmentChildrenByParentId: Object,
    users: Array,
    vendors: Array,
    relatedWorks: Array,
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const activeTab = ref('history'); // 'history' | 'comment'
const showContentForm = ref(false);
const editingContentId = ref(null);
const showUsedPartForm = ref(false);
const showCostForm = ref(false);
const costFormSectionRef = ref(null);
const showSummaryDocForm = ref(false);

const historyActivities = computed(() =>
    (props.work?.work_activities ?? []).filter((a) => a.work_activity_type?.name !== 'コメント')
);
const commentActivities = computed(() =>
    (props.work?.work_activities ?? []).filter((a) => a.work_activity_type?.name === 'コメント')
);
const isOwnComment = (activity) => activity.user_id != null && authUser.value?.id === activity.user_id;

/** 設備の祖先パス（ルート→親→...→設備）を取得（複数設備対応） */
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
const equipmentPaths = computed(() => {
    const eqs = props.work?.equipments ?? [];
    return eqs.map((eq) => buildEquipmentPath(eq)).filter((p) => p.length > 0);
});
const equipmentPath = computed(() => equipmentPaths.value[0] ?? []);

const formatDate = (v) => (v ? new Date(v).toLocaleDateString('ja-JP') : '—');
const formatDateTime = (v) => (v ? new Date(v).toLocaleString('ja-JP') : '—');

/** 使用部品の数量表示（90.00 → 90、90.5 → 90.5） */
const formatUsedPartQty = (v) => Number(v);

const contentForm = useForm({
    work_content_tag_ids: [],
    repair_type_ids: [],
    content: '',
    started_at: '',
    ended_at: '',
    images: [],
});

const usedPartForm = useForm({
    part_id: '',
    qty: '1',
    note: '',
});

const partSearchQuery = ref('');
const partSearchResults = ref([]);
const partSearchLoading = ref(false);

async function searchParts() {
    partSearchLoading.value = true;
    partSearchResults.value = [];
    try {
        const { data } = await axios.get(route('work.parts.search'), {
            params: { q: partSearchQuery.value },
        });
        partSearchResults.value = (data.items ?? []).map((p) => ({ ...p, _addQty: 1, _addNote: '' }));
    } catch {
        partSearchResults.value = [];
    } finally {
        partSearchLoading.value = false;
    }
}

function submitUsedPartFromRow(part) {
    const qty = Math.max(1, parseInt(part._addQty, 10) || 1);
    usedPartForm.part_id = part.id;
    usedPartForm.qty = String(qty);
    usedPartForm.note = part._addNote ?? '';
    usedPartForm.post(route('work.works.work-used-parts.store', props.work.id), {
        preserveScroll: true,
        onSuccess: () => {
            usedPartForm.reset('part_id', 'qty', 'note');
            showUsedPartForm.value = false;
        },
    });
}

const costForm = useForm({
    work_cost_category_id: '',
    amount: '',
    name: '',
    vendor_id: '',
    vendor_name: '',
    _vendor_name: '',
    occurred_at: '',
    note: '',
    file: null,
});

const summaryDocForm = useForm({
    display_name: '',
    file: null,
});

/** File インスタンスかどうかを安全に判定（ビルド時に File が undefined になる場合に対応） */
function isFile(obj) {
    return obj != null && typeof File !== 'undefined' && obj instanceof File;
}

/** File からプレビュー用のオブジェクトURLを取得（テンプレートで URL が undefined になる場合に対応） */
function getObjectUrl(file) {
    return isFile(file) && typeof URL !== 'undefined' ? URL.createObjectURL(file) : '';
}

function setContentImages(e) {
    const files = e.target.files;
    if (!files?.length) return;
    contentForm.images = [...(contentForm.images || []), ...Array.from(files)];
    e.target.value = '';
}

function removeContentImage(idx) {
    contentForm.images.splice(idx, 1);
}

/** 編集開始: 既存データを contentForm にセット */
function startEditContent(c) {
    editingContentId.value = c.id;
    contentForm.work_content_tag_ids = (c.work_content_tags ?? []).map((t) => t.id);
    contentForm.repair_type_ids = (c.repair_types ?? []).map((r) => r.id);
    contentForm.content = c.content ?? '';
    contentForm.started_at = toDatetimeLocal(c.started_at);
    contentForm.ended_at = toDatetimeLocal(c.ended_at);
    contentForm.images = [];
}

function cancelEditContent() {
    editingContentId.value = null;
    contentForm.reset();
}

function deleteWorkContent(c) {
    if (!confirm('この作業内容を削除しますか？')) return;
    router.delete(route('work.works.work-contents.destroy', [props.work.id, c.id]), { preserveScroll: true });
}

function deleteWorkAttachment(att) {
    if (!confirm('この画像を削除しますか？')) return;
    router.delete(route('work.works.work-attachments.destroy', [props.work.id, att.id]), { preserveScroll: true });
}

function setEndAndComplete() {
    const endedAt = contentForm.ended_at == null ? '' : String(contentForm.ended_at).trim();
    if (!endedAt) return;
    router.put(route('work.works.complete', props.work.id), { completed_at: endedAt }, {
        preserveScroll: true,
        onSuccess: () => { /* 作業を完了に更新済み */ },
    });
}

function submitContent() {
    const isUpdate = editingContentId.value != null;
    const hasImages = contentForm.images?.length;
    const onSuccess = () => {
        editingContentId.value = null;
        contentForm.reset();
        showContentForm.value = false;
    };
    if (hasImages || isUpdate) {
        const fd = new FormData();
        if (isUpdate) {
            fd.append('_method', 'PUT');
        }
        (contentForm.work_content_tag_ids || []).forEach((id) => fd.append('work_content_tag_ids[]', id));
        (contentForm.repair_type_ids || []).forEach((id) => fd.append('repair_type_ids[]', id));
        fd.append('content', contentForm.content);
        if (contentForm.started_at) fd.append('started_at', contentForm.started_at);
        if (contentForm.ended_at) fd.append('ended_at', contentForm.ended_at);
        (contentForm.images || []).forEach((f) => fd.append('images[]', f));
        contentForm.processing = true;
        if (isUpdate) {
            router.post(route('work.works.work-contents.update', [props.work.id, editingContentId.value]), fd, {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => { contentForm.processing = false; contentForm.images = []; },
                onSuccess,
            });
        } else {
            router.post(route('work.works.work-contents.store', props.work.id), fd, {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => { contentForm.processing = false; contentForm.images = []; },
                onSuccess,
            });
        }
    } else {
        if (isUpdate) {
            contentForm.put(route('work.works.work-contents.update', [props.work.id, editingContentId.value]), {
                preserveScroll: true,
                onSuccess,
            });
        } else {
            contentForm.post(route('work.works.work-contents.store', props.work.id), {
                preserveScroll: true,
                onSuccess,
            });
        }
    }
}

function setCostFile(e) {
    costForm.file = e.target.files?.[0] ?? null;
}

function onCostVendorInput() {
    const name = (costForm._vendor_name || '').trim();
    const found = (props.vendors || []).find((v) => v.name === name);
    costForm.vendor_id = found ? found.id : '';
}

function setSummaryDocFile(e) {
    summaryDocForm.file = e.target.files?.[0] ?? null;
}

const summaryDocuments = computed(() => props.work?.summary_documents ?? []);

function getDocDisplayName(doc) {
    return doc.display_name || doc.original_name || '資料';
}

function submitSummaryDoc() {
    if (!summaryDocForm.file) {
        return;
    }
    const fd = new FormData();
    fd.append('display_name', summaryDocForm.display_name || summaryDocForm.file.name);
    fd.append('file', summaryDocForm.file);
    summaryDocForm.processing = true;
    router.post(route('work.works.summary-documents.store', props.work.id), fd, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => { summaryDocForm.processing = false; summaryDocForm.file = null; },
        onSuccess: () => {
            summaryDocForm.reset('display_name', 'file');
            summaryDocForm.file = null;
            showSummaryDocForm.value = false;
        },
    });
}

const onCostSuccess = () => {
    costForm.reset();
    costForm.file = null;
    showCostForm.value = false;
};

function submitCost() {
    if (costForm.vendor_id) {
        costForm.vendor_name = '';
    } else if (costForm._vendor_name && String(costForm._vendor_name).trim()) {
        costForm.vendor_name = String(costForm._vendor_name).trim();
    } else {
        costForm.vendor_name = '';
    }
    if (isFile(costForm.file)) {
        const fd = new FormData();
        fd.append('work_cost_category_id', costForm.work_cost_category_id);
        fd.append('amount', costForm.amount);
        if (costForm.name) fd.append('name', costForm.name);
        if (costForm.vendor_id) fd.append('vendor_id', costForm.vendor_id);
        else if (costForm.vendor_name) fd.append('vendor_name', costForm.vendor_name);
        if (costForm.occurred_at) fd.append('occurred_at', costForm.occurred_at);
        if (costForm.note) fd.append('note', costForm.note);
        fd.append('file', costForm.file);
        costForm.processing = true;
        router.post(route('work.works.work-costs.store', props.work.id), fd, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => { costForm.processing = false; costForm.file = null; },
            onSuccess: onCostSuccess,
        });
    } else {
        costForm.post(route('work.works.work-costs.store', props.work.id), {
            preserveScroll: true,
            onSuccess: onCostSuccess,
        });
    }
}

const commentForm = useForm({ message: '' });
function submitComment() {
    commentForm.post(route('work.works.comments.store', props.work.id), {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset('message');
            activeTab.value = 'comment';
        },
    });
}

const editCommentId = ref(null);
const editCommentForm = useForm({ message: '' });
function startEditComment(activity) {
    editCommentId.value = activity.id;
    editCommentForm.message = activity.message ?? '';
}
function cancelEditComment() {
    editCommentId.value = null;
    editCommentForm.reset('message');
}
function submitEditComment() {
    if (!editCommentId.value) return;
    editCommentForm.put(route('work.works.comments.update', [props.work.id, editCommentId.value]), {
        preserveScroll: true,
        onSuccess: () => {
            editCommentId.value = null;
            editCommentForm.reset('message');
        },
    });
}
function deleteComment(activity) {
    if (!confirm('このコメントを削除しますか？')) return;
    router.delete(route('work.works.comments.destroy', [props.work.id, activity.id]), {
        preserveScroll: true,
    });
}

function deleteUsedPart(u) {
    if (!confirm('この使用部品を削除しますか？在庫が戻ります。')) return;
    router.delete(route('work.works.work-used-parts.destroy', [props.work.id, u.id]), {
        preserveScroll: true,
    });
}

/** 本日の日付を YYYY-MM-DD 形式で返す */
function todayAsDateString() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

/** 使用部品から費用フォームを開く（費用カテゴリ・金額・業者名・発生日を自動セット、ユーザーが追加ボタンを押す） */
async function openCostFormFromUsedPart(u) {
    const part = u.part;
    if (!part) return;

    // デバッグ: 部品情報
    console.log('[費用追加デバッグ] 使用部品 u:', u);
    console.log('[費用追加デバッグ] part:', part, 'part.id:', part?.id, 'part.external_id:', part?.external_id);

    const partName = part.display_name ?? part.name ?? '—';
    const partNo = part.part_no ?? '';
    const suggestedName = partNo ? `${partName}/${partNo}` : partName;

    const buhinhiCategory = props.workCostCategories?.find((c) => c.name === '部品費');
    const qty = Math.max(0.0001, parseFloat(u.qty) || 1);
    console.log('[費用追加デバッグ] qty:', qty, 'u.qty:', u.qty);

    let amount = 0;
    if (part.external_id) {
        const apiUrl = `/work/parts/${part.id}/price`;
        console.log('[費用追加デバッグ] API呼び出し URL:', apiUrl);
        try {
            const response = await axios.get(apiUrl);
            const data = response?.data;
            console.log('[費用追加デバッグ] API応答 生データ:', response);
            console.log('[費用追加デバッグ] data:', data, 'data.price:', data?.price, 'typeof data.price:', typeof data?.price);

            const unitPrice = data?.price != null ? parseFloat(data.price) : 0;
            amount = Math.round(unitPrice * qty);
            console.log('[費用追加デバッグ] unitPrice:', unitPrice, 'amount:', amount);
        } catch (err) {
            console.error('[費用追加デバッグ] API エラー:', err);
            console.error('[費用追加デバッグ] エラー詳細:', err?.response?.status, err?.response?.data);
            amount = 0;
        }
    } else {
        console.log('[費用追加デバッグ] part.external_id が無いため API を呼び出していません');
    }

    console.log('[費用追加デバッグ] 最終 amount:', amount);

    costForm.work_cost_category_id = buhinhiCategory ? String(buhinhiCategory.id) : '';
    costForm.amount = String(amount);
    costForm.name = suggestedName;
    costForm.vendor_id = '';
    costForm._vendor_name = '';
    costForm.occurred_at = todayAsDateString();
    costForm.note = '';
    costForm.file = null;

    showCostForm.value = true;
    await nextTick();
    costFormSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

const showWorkEdit = ref(false);
const workContentsList = ref([]);
watch(() => props.work?.work_contents, (val) => { workContentsList.value = val ? [...val] : []; }, { immediate: true });

function onReorderWorkContents() {
    const order = workContentsList.value.map((c) => c.id);
    router.put(route('work.works.work-contents.reorder', props.work.id), { order }, { preserveScroll: true });
}
function workEditEquipmentOptionsForParent(parentId) {
    if (!parentId) return [];
    const map = props.equipmentChildrenByParentId ?? {};
    return map[String(parentId)] ?? [];
}

const workEditForm = useForm({
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
});

function addWorkEditEquipment() {
    workEditForm.equipments.push({ parent_id: '', equipment_id: '' });
}

function removeWorkEditEquipment(index) {
    workEditForm.equipments.splice(index, 1);
}

function addWorkEditAdditionalUser() {
    workEditForm.additional_user_ids.push({ user_id: '' });
}

function removeWorkEditAdditionalUser(index) {
    workEditForm.additional_user_ids.splice(index, 1);
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

/** ステータスが「完了」に変更されたら完了日時を自動入力 */
watch(
    () => workEditForm.work_status_id,
    (newVal) => {
        if (completedStatusId.value && String(newVal) === String(completedStatusId.value)) {
            workEditForm.completed_at = nowAsDatetimeLocal();
        }
    }
);
function toDatetimeLocal(v) {
    if (!v) return '';
    const d = new Date(v);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const h = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `${y}-${m}-${day}T${h}:${min}`;
}
function openWorkEdit() {
    workEditForm.title = props.work.title ?? '';
    const eqs = props.work?.equipments ?? [];
    workEditForm.equipments = eqs.length
        ? eqs.map((eq) => {
            let cur = eq;
            while (cur?.parent) cur = cur.parent;
            const parentId = cur ? String(cur.id) : '';
            return { parent_id: parentId, equipment_id: eq.id };
        })
        : [{ parent_id: '', equipment_id: '' }];
    workEditForm.work_status_id = props.work.work_status_id ?? '';
    workEditForm.work_priority_id = props.work.work_priority_id ?? '';
    workEditForm.work_purpose_ids = (props.work?.work_purposes ?? []).map((p) => p.id);
    workEditForm.assigned_user_id = props.work.assigned_user_id ?? '';
    workEditForm.additional_user_ids = (props.work?.additional_users ?? []).length
        ? (props.work.additional_users ?? []).map((u) => ({ user_id: u.id }))
        : [{ user_id: '' }];
    workEditForm.production_stop_minutes = props.work.production_stop_minutes ?? '';
    workEditForm.occurred_at = toDatetimeLocal(props.work.occurred_at);
    workEditForm.completed_at = toDatetimeLocal(props.work.completed_at);
    workEditForm.note = props.work.note ?? '';
    showWorkEdit.value = true;
}

function submitWorkEdit() {
    const data = {
        ...workEditForm.data(),
        equipments: workEditForm.equipments,
        work_purpose_ids: workEditForm.work_purpose_ids,
        additional_user_ids: workEditForm.additional_user_ids,
    };
    router.put(route('work.works.update', props.work.id), data, {
        preserveScroll: true,
        onSuccess: () => { showWorkEdit.value = false; },
    });
}
</script>

<template>
    <Head :title="`作業: ${work.title}`" />

    <AuthenticatedLayout>
        <div class="flex gap-6">
            <!-- 左: 詳細コンテンツ（約70%） -->
            <div class="flex-1 min-w-0 max-w-full space-y-5">

            <!-- 作業概要 -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-slate-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        作業概要
                    </h2>
                    <SecondaryButton v-if="!showWorkEdit" type="button" @click="openWorkEdit">編集</SecondaryButton>
                </div>
                <template v-if="!showWorkEdit">
                    <div class="p-4 space-y-4">
                        <!-- 作業名・設備（メイン情報） -->
                        <div class="space-y-3">
                            <div class="flex gap-3">
                                <div class="shrink-0 w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-0.5">作業名</p>
                                    <p class="text-base font-semibold text-slate-800 leading-snug">{{ work.title ?? '—' }}</p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <div class="shrink-0 w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">設備</p>
                                    <div class="text-slate-800 space-y-1">
                                        <template v-if="equipmentPaths.length">
                                            <div v-for="(path, pathIdx) in equipmentPaths" :key="pathIdx" class="flex flex-wrap gap-x-1 items-baseline">
                                                <template v-for="(eq, i) in path" :key="eq.id">
                                                    <span v-if="i < path.length - 1" class="text-slate-400 text-sm">{{ eq.name }} › </span>
                                                    <span v-else class="font-medium text-slate-800">{{ eq.name }}</span>
                                                </template>
                                            </div>
                                        </template>
                                        <template v-else><span class="text-slate-500">—</span></template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ステータス・優先度・目的（一括表示） -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div class="flex gap-2.5 p-2.5 rounded-lg bg-slate-50/80 border border-slate-100">
                                <div class="shrink-0 w-8 h-8 rounded-lg bg-white flex items-center justify-center border border-slate-200">
                                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-slate-500 mb-0.5">ステータス</p>
                                    <Badge :label="work.work_status?.name ?? '—'" :color="work.work_status?.color" />
                                </div>
                            </div>
                            <div class="flex gap-2.5 p-2.5 rounded-lg bg-slate-50/80 border border-slate-100">
                                <div class="shrink-0 w-8 h-8 rounded-lg bg-white flex items-center justify-center border border-slate-200">
                                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-slate-500 mb-0.5">優先度</p>
                                    <Badge :label="work.work_priority?.name ?? '—'" :color="work.work_priority?.color" />
                                </div>
                            </div>
                            <div class="flex gap-2.5 p-2.5 rounded-lg bg-slate-50/80 border border-slate-100 sm:col-span-2 lg:col-span-1">
                                <div class="shrink-0 w-8 h-8 rounded-lg bg-white flex items-center justify-center border border-slate-200">
                                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-slate-500 mb-0.5">作業目的</p>
                                    <div class="flex flex-wrap gap-1">
                                        <Badge v-for="p in (work.work_purposes || [])" :key="p.id" :label="p.name" :color="p.color" />
                                        <span v-if="!(work.work_purposes?.length)" class="text-slate-500 text-sm">—</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 担当者 -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="flex gap-2.5 p-2.5 rounded-lg bg-slate-50/80 border border-slate-100">
                                <div class="shrink-0 w-8 h-8 rounded-lg bg-white flex items-center justify-center border border-slate-200">
                                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-slate-500 mb-0.5">主担当</p>
                                    <Badge :label="work.assigned_user?.name ?? '—'" :color="work.assigned_user?.color" />
                                </div>
                            </div>
                            <div class="flex gap-2.5 p-2.5 rounded-lg bg-slate-50/80 border border-slate-100">
                                <div class="shrink-0 w-8 h-8 rounded-lg bg-white flex items-center justify-center border border-slate-200">
                                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-slate-500 mb-0.5">追加担当</p>
                                    <div class="flex flex-wrap gap-1">
                                        <Badge v-for="u in (work.additional_users || [])" :key="u.id" :label="u.name" :color="u.color" />
                                        <span v-if="!(work.additional_users?.length)" class="text-slate-500 text-sm">—</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 日時情報 -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="flex gap-2.5 p-2.5 rounded-lg bg-slate-50/80 border border-slate-100">
                                <div class="shrink-0 w-8 h-8 rounded-lg bg-white flex items-center justify-center border border-slate-200">
                                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-slate-500 mb-0.5">発生日</p>
                                    <p class="text-sm font-medium text-slate-800">{{ formatDateTime(work.occurred_at) }}</p>
                                </div>
                            </div>
                            <div class="flex gap-2.5 p-2.5 rounded-lg bg-slate-50/80 border border-slate-100">
                                <div class="shrink-0 w-8 h-8 rounded-lg bg-white flex items-center justify-center border border-slate-200">
                                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-slate-500 mb-0.5">停止時間</p>
                                    <p class="text-sm font-medium text-slate-800">{{ work.production_stop_minutes != null ? work.production_stop_minutes + '分' : '—' }}</p>
                                </div>
                            </div>
                            <div class="flex gap-2.5 p-2.5 rounded-lg bg-slate-50/80 border border-slate-100">
                                <div class="shrink-0 w-8 h-8 rounded-lg bg-white flex items-center justify-center border border-slate-200">
                                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-slate-500 mb-0.5">完了日時</p>
                                    <p class="text-sm font-medium text-slate-800">{{ formatDateTime(work.completed_at) }}</p>
                                </div>
                            </div>
                            <div class="flex gap-2.5 p-2.5 rounded-lg bg-slate-50/80 border border-slate-100 sm:col-span-2 lg:col-span-1">
                                <div class="shrink-0 w-8 h-8 rounded-lg bg-white flex items-center justify-center border border-slate-200">
                                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-slate-500 mb-0.5">作成・更新</p>
                                    <p class="text-sm font-medium text-slate-800">{{ formatDateTime(work.updated_at) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- 備考 -->
                        <div v-if="work.note" class="flex gap-2.5 p-3 rounded-lg bg-amber-50/80 border border-amber-100">
                            <div class="shrink-0 w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" /></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-amber-800 uppercase tracking-wide mb-1">備考</p>
                                <p class="text-sm text-slate-800 whitespace-pre-wrap leading-relaxed">{{ work.note }}</p>
                            </div>
                        </div>
                    </div>
                </template>
                <!-- 編集モード（インライン・青系） -->
                <form v-else @submit.prevent="submitWorkEdit" class="p-5 rounded-b-xl border-t-0 border-2 border-indigo-200 bg-indigo-50/80 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-semibold text-indigo-800 flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            作業概要を編集
                        </h3>
                        <button type="button" class="text-slate-500 hover:text-slate-700 text-sm" @click="showWorkEdit = false">キャンセル</button>
                    </div>
                    <div>
                        <InputLabel value="作業名" required />
                        <TextInput v-model="workEditForm.title" type="text" class="mt-1 block w-full rounded-md border-indigo-200" required />
                        <InputError :message="workEditForm.errors.title" />
                    </div>
                    <div>
                        <InputLabel value="設備" required />
                        <div v-for="(row, idx) in workEditForm.equipments" :key="idx" class="flex gap-2 items-end mb-2">
                            <div class="flex-1 min-w-[100px]">
                                <select v-model="row.parent_id" class="mt-1 block w-full rounded-md border-indigo-200 shadow-sm text-sm" required @change="row.equipment_id = ''">
                                    <option value="">親設備を選択</option>
                                    <option v-for="opt in parentEquipmentOptions" :key="opt.id" :value="String(opt.id)">{{ opt.name }}</option>
                                </select>
                            </div>
                            <div class="flex-1 min-w-[100px]">
                                <select v-model="row.equipment_id" class="mt-1 block w-full rounded-md border-indigo-200 shadow-sm text-sm" required :disabled="!row.parent_id">
                                    <option value="">{{ row.parent_id ? '選択' : '親設備を選択' }}</option>
                                    <option v-for="opt in workEditEquipmentOptionsForParent(row.parent_id)" :key="opt.id" :value="opt.id">{{ opt.display_label ?? opt.name }}</option>
                                </select>
                            </div>
                            <button type="button" class="text-red-600 hover:text-red-800 shrink-0" :disabled="workEditForm.equipments.length <= 1" @click="removeWorkEditEquipment(idx)">×</button>
                        </div>
                        <SecondaryButton type="button" class="text-sm" @click="addWorkEditEquipment">＋ 設備を追加</SecondaryButton>
                        <InputError :message="workEditForm.errors.equipments" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="ステータス" required />
                            <select v-model="workEditForm.work_status_id" class="mt-1 block w-full rounded-md border-indigo-200 shadow-sm" required>
                                <option value="">選択</option>
                                <option v-for="s in workStatuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <InputError :message="workEditForm.errors.work_status_id" />
                        </div>
                        <div>
                            <InputLabel value="優先度" />
                            <select v-model="workEditForm.work_priority_id" class="mt-1 block w-full rounded-md border-indigo-200 shadow-sm">
                                <option value="">選択</option>
                                <option v-for="p in workPriorities" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                            <InputError :message="workEditForm.errors.work_priority_id" />
                        </div>
                    </div>
                    <div class="w-full">
                        <InputLabel value="作業目的" required />
                        <div class="mt-1 flex flex-wrap gap-2 p-2 rounded-md border border-indigo-200 bg-white min-h-[38px] w-full">
                            <label v-for="p in workPurposes" :key="p.id" class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" :value="p.id" v-model="workEditForm.work_purpose_ids" class="rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm">{{ p.name }}</span>
                            </label>
                        </div>
                        <InputError :message="workEditForm.errors.work_purpose_ids" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="主担当" required />
                            <select v-model="workEditForm.assigned_user_id" class="mt-1 block w-full rounded-md border-indigo-200 shadow-sm" required>
                                <option value="">選択</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.api_name ?? u.name }}</option>
                            </select>
                            <InputError :message="workEditForm.errors.assigned_user_id" />
                        </div>
                        <div>
                            <InputLabel value="追加担当" />
                            <div v-for="(row, idx) in workEditForm.additional_user_ids" :key="idx" class="flex gap-2 items-center mb-2">
                                <select v-model="row.user_id" class="flex-1 mt-1 block w-full rounded-md border-indigo-200 shadow-sm text-sm">
                                    <option value="">なし</option>
                                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.api_name ?? u.name }}</option>
                                </select>
                                <button type="button" class="text-red-600 hover:text-red-800 shrink-0" :disabled="workEditForm.additional_user_ids.length <= 1" @click="removeWorkEditAdditionalUser(idx)">×</button>
                            </div>
                            <SecondaryButton type="button" class="text-sm" @click="addWorkEditAdditionalUser">＋ 追加担当</SecondaryButton>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <InputLabel value="停止時間（分）" />
                            <TextInput v-model="workEditForm.production_stop_minutes" type="number" min="0" class="mt-1 block w-full rounded-md border-indigo-200" />
                            <InputError :message="workEditForm.errors.production_stop_minutes" />
                        </div>
                        <div>
                            <InputLabel value="発生日" />
                            <TextInput v-model="workEditForm.occurred_at" type="datetime-local" class="mt-1 block w-full rounded-md border-indigo-200" />
                            <InputError :message="workEditForm.errors.occurred_at" />
                        </div>
                        <div>
                            <InputLabel value="完了日時" />
                            <TextInput v-model="workEditForm.completed_at" type="datetime-local" class="mt-1 block w-full rounded-md border-indigo-200" />
                            <InputError :message="workEditForm.errors.completed_at" />
                        </div>
                    </div>
                    <div>
                        <InputLabel value="詳細" />
                        <textarea v-model="workEditForm.note" rows="3" class="mt-1 block w-full rounded-md border-indigo-200 shadow-sm" />
                        <InputError :message="workEditForm.errors.note" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="text-slate-600 hover:text-slate-800 text-sm" @click="showWorkEdit = false">キャンセル</button>
                        <SecondaryButton type="submit" :disabled="workEditForm.processing">保存</SecondaryButton>
                    </div>
                </form>
            </div>

            <!-- 作業内容 -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-slate-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                        作業内容
                    </h2>
                    <SecondaryButton type="button" @click="showContentForm = !showContentForm">
                        {{ showContentForm ? '閉じる' : '追加' }}
                    </SecondaryButton>
                </div>
                <div class="p-4">
                    <!-- 追加フォーム（登録済みの上に表示） -->
                    <form v-show="showContentForm" @submit.prevent="submitContent" class="mb-6 p-5 rounded-xl border-2 border-indigo-200 bg-indigo-50/80 space-y-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-indigo-800 flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                作業内容を追加
                            </h3>
                            <button
                                type="button"
                                @click="showContentForm = false"
                                class="text-slate-500 hover:text-slate-700 text-sm"
                            >
                                閉じる
                            </button>
                        </div>
                        <div>
                            <InputLabel value="作業タグ" required />
                            <div class="mt-1 flex flex-wrap gap-2 p-2 rounded-md border border-indigo-200 bg-white min-h-[38px] w-full">
                                    <label v-for="t in workContentTags" :key="t.id" class="inline-flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" :value="t.id" v-model="contentForm.work_content_tag_ids" class="rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500" />
                                        <span class="text-sm">{{ t.name }}</span>
                                    </label>
                                </div>
                                <InputError :message="contentForm.errors.work_content_tag_ids" />
                            </div>
                            <!-- 修理内容：非表示（カラムは残す） -->
                            <div class="hidden">
                                <InputLabel value="修理内容" />
                                <div class="mt-1 flex flex-wrap gap-2 p-2 rounded-md border border-indigo-200 bg-white min-h-[38px] w-full">
                                    <label v-for="r in repairTypes" :key="r.id" class="inline-flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" :value="r.id" v-model="contentForm.repair_type_ids" class="rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500" />
                                        <span class="text-sm">{{ r.name }}</span>
                                    </label>
                                </div>
                                <InputError :message="contentForm.errors.repair_type_ids" />
                            </div>
                        <div>
                            <InputLabel value="内容" required />
                            <textarea v-model="contentForm.content" rows="3" class="mt-1 block w-full rounded-md border-indigo-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required placeholder="作業内容を入力してください" />
                            <InputError :message="contentForm.errors.content" />
                        </div>
                        <div>
                            <InputLabel value="添付画像" />
                            <input type="file" accept="image/*" multiple class="mt-1 block w-full text-sm text-slate-600 file:mr-2 file:rounded-md file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-white file:text-sm file:font-medium hover:file:bg-indigo-700" @change="setContentImages" />
                            <div v-if="contentForm.images?.length" class="mt-2 flex flex-wrap gap-2">
                                <div v-for="(img, imgIdx) in contentForm.images" :key="imgIdx" class="relative inline-block">
                                    <img v-if="isFile(img)" :src="getObjectUrl(img)" alt="プレビュー" class="h-16 w-16 object-cover rounded-lg border-2 border-indigo-200" />
                                    <button type="button" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs leading-none flex items-center justify-center hover:bg-red-600" @click="removeContentImage(imgIdx)">×</button>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="flex gap-2 items-end">
                                <div class="flex-1">
                                    <InputLabel value="開始日時" />
                                    <TextInput v-model="contentForm.started_at" type="datetime-local" class="mt-1 block w-full rounded-md border-indigo-200" />
                                </div>
                                <button type="button" class="p-2 rounded-md border border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 shrink-0" title="発生日を開始日時に入力" @click="contentForm.started_at = work.occurred_at ? toDatetimeLocal(work.occurred_at) : ''">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </button>
                            </div>
                            <div class="flex gap-2 items-end">
                                <div class="flex-1">
                                    <InputLabel value="終了日時" />
                                    <TextInput v-model="contentForm.ended_at" type="datetime-local" class="mt-1 block w-full rounded-md border-indigo-200" />
                                </div>
                                <button type="button" class="p-2 rounded-md border border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 shrink-0 disabled:opacity-50 disabled:cursor-not-allowed" title="ステータスを完了にし、作業概要の完了日時をこの終了日時で上書きする" :disabled="!contentForm.ended_at || !String(contentForm.ended_at).trim()" @click="setEndAndComplete">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <PrimaryButton type="submit" :disabled="contentForm.processing">{{ contentForm.processing ? '登録中...' : '追加する' }}</PrimaryButton>
                    </form>

                    <!-- 登録済み作業内容（ドラッグ＆ドロップで並び替え可能） -->
                    <div v-if="workContentsList.length" class="rounded-lg bg-slate-50/60">
                        <draggable
                            v-model="workContentsList"
                            item-key="id"
                            handle=".drag-handle"
                            @end="onReorderWorkContents"
                            class="space-y-0"
                        >
                            <template #item="{ element: c }">
                        <div
                            :key="c.id"
                            class="py-4 px-4 border-b border-dashed border-slate-300 last:border-b-0 relative"
                        >
                            <!-- 編集中: オレンジの編集フォーム -->
                            <form
                                v-if="editingContentId === c.id"
                                @submit.prevent="submitContent"
                                class="p-5 rounded-xl border-2 border-orange-200 bg-orange-50/80 space-y-4 shadow-sm"
                            >
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-orange-800 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        作業内容を編集
                                    </h3>
                                    <button type="button" class="text-slate-500 hover:text-slate-700 text-sm" @click="cancelEditContent">キャンセル</button>
                                </div>
                                <div>
                                    <InputLabel value="作業タグ" required />
                                    <div class="mt-1 flex flex-wrap gap-2 p-2 rounded-md border border-orange-200 bg-white min-h-[38px] w-full">
                                        <label v-for="t in workContentTags" :key="t.id" class="inline-flex items-center gap-1.5 cursor-pointer">
                                            <input type="checkbox" :value="t.id" v-model="contentForm.work_content_tag_ids" class="rounded border-orange-300 text-orange-600 focus:ring-orange-500" />
                                            <span class="text-sm">{{ t.name }}</span>
                                        </label>
                                    </div>
                                    <InputError :message="contentForm.errors.work_content_tag_ids" />
                                </div>
                                <div class="hidden">
                                    <InputLabel value="修理内容" />
                                    <div class="mt-1 flex flex-wrap gap-2 p-2 rounded-md border border-orange-200 bg-white min-h-[38px] w-full">
                                        <label v-for="r in repairTypes" :key="r.id" class="inline-flex items-center gap-1.5 cursor-pointer">
                                            <input type="checkbox" :value="r.id" v-model="contentForm.repair_type_ids" class="rounded border-orange-300 text-orange-600 focus:ring-orange-500" />
                                            <span class="text-sm">{{ r.name }}</span>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <InputLabel value="内容" required />
                                    <textarea v-model="contentForm.content" rows="3" class="mt-1 block w-full rounded-md border-orange-200 shadow-sm focus:border-orange-500 focus:ring-orange-500" required placeholder="作業内容を入力してください" />
                                    <InputError :message="contentForm.errors.content" />
                                </div>
                                <div>
                                    <InputLabel value="登録済み画像" />
                                    <div v-if="c.work_attachments?.length" class="mt-2 flex flex-wrap gap-2">
                                        <div v-for="att in c.work_attachments" :key="att.id" class="relative inline-block">
                                            <img
                                                v-if="att.url && /\.(jpe?g|png|gif|webp)$/i.test(att.original_name || '')"
                                                :src="att.url"
                                                :alt="att.original_name || '画像'"
                                                class="h-20 w-20 object-cover rounded-lg border-2 border-orange-200"
                                            />
                                            <span v-else class="inline-flex items-center gap-1.5 px-2 py-1.5 rounded border border-orange-200 text-slate-600 text-xs h-20 w-20 flex items-center justify-center">{{ att.original_name || '添付' }}</span>
                                            <button type="button" class="absolute -top-1 -right-1 w-6 h-6 bg-red-500 text-white rounded-full text-sm leading-none flex items-center justify-center hover:bg-red-600" title="画像を削除" @click="deleteWorkAttachment(att)">×</button>
                                        </div>
                                    </div>
                                    <p v-else class="text-slate-500 text-xs mt-1">登録済み画像なし</p>
                                </div>
                                <div>
                                    <InputLabel value="新規画像を追加" />
                                    <input type="file" accept="image/*" multiple class="mt-1 block w-full text-sm text-slate-600 file:mr-2 file:rounded-md file:border-0 file:bg-orange-600 file:px-4 file:py-2 file:text-white file:text-sm file:font-medium hover:file:bg-orange-700" @change="setContentImages" />
                                    <div v-if="contentForm.images?.length" class="mt-2 flex flex-wrap gap-2">
                                        <div v-for="(img, imgIdx) in contentForm.images" :key="imgIdx" class="relative inline-block">
                                            <img v-if="isFile(img)" :src="getObjectUrl(img)" alt="プレビュー" class="h-16 w-16 object-cover rounded-lg border-2 border-orange-200" />
                                            <button type="button" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs leading-none flex items-center justify-center hover:bg-red-600" @click="removeContentImage(imgIdx)">×</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <InputLabel value="開始日時" />
                                        <TextInput v-model="contentForm.started_at" type="datetime-local" class="mt-1 block w-full rounded-md border-orange-200" />
                                    </div>
                                    <div>
                                        <InputLabel value="終了日時" />
                                        <TextInput v-model="contentForm.ended_at" type="datetime-local" class="mt-1 block w-full rounded-md border-orange-200" />
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" class="text-slate-600 hover:text-slate-800 text-sm" @click="cancelEditContent">キャンセル</button>
                                    <PrimaryButton type="submit" :disabled="contentForm.processing" class="!bg-orange-600 hover:!bg-orange-700 focus:!ring-orange-500">
                                        {{ contentForm.processing ? '保存中...' : '保存' }}
                                    </PrimaryButton>
                                </div>
                            </form>
                            <!-- 表示モード: ブロック + 右上に編集・削除 -->
                            <template v-else>
                                <div class="absolute top-2 right-2 flex gap-1 z-10">
                                    <button type="button" class="p-1.5 rounded text-slate-500 hover:text-orange-600 hover:bg-orange-50" title="編集" @click="startEditContent(c)">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                    </button>
                                    <button type="button" class="p-1.5 rounded text-slate-500 hover:text-red-600 hover:bg-red-50" title="削除" @click="deleteWorkContent(c)">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                                <div class="flex gap-3">
                                    <div class="drag-handle shrink-0 w-6 h-6 flex items-center justify-center rounded cursor-move text-slate-400 hover:text-slate-600 hover:bg-slate-200/80" title="ドラッグで並び替え">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 6h2v2H8V6zm0 5h2v2H8v-2zm0 5h2v2H8v-2zm5-10h2v2h-2V6zm0 5h2v2h-2v-2zm0 5h2v2h-2v-2z"/></svg>
                                    </div>
                                    <div class="shrink-0 w-6 h-6 flex items-center justify-center rounded-full bg-slate-300/70 text-slate-600 text-xs font-medium">
                                        {{ workContentsList.indexOf(c) + 1 }}
                                    </div>
                                    <div class="flex-1 min-w-0 pr-16">
                                        <!-- タグ・修理内容（横並び） -->
                                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 mb-2">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-xs text-slate-500">タグ</span>
                                                <div class="flex flex-wrap gap-1">
                                                    <Badge v-for="t in (c.work_content_tags || [])" :key="t.id" :label="t.name" :color="t.color" />
                                                    <span v-if="!(c.work_content_tags?.length)" class="text-slate-400 text-xs">—</span>
                                                </div>
                                            </div>
                                            <div class="hidden">
                                                <span class="text-xs text-slate-500">修理内容</span>
                                                <div class="flex flex-wrap gap-1">
                                                    <Badge v-for="r in (c.repair_types || [])" :key="r.id" :label="r.name" :color="r.color" />
                                                    <span v-if="!(c.repair_types?.length)" class="text-slate-400 text-xs">—</span>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- 開始日時・終了日時 -->
                                        <div v-if="c.started_at || c.ended_at" class="flex flex-wrap gap-x-6 gap-y-1 px-2.5 py-1.5 rounded bg-slate-100/80 text-xs text-slate-600 mb-2">
                                            <span v-if="c.started_at" class="flex items-center gap-1.5">
                                                <span class="text-slate-400 font-medium">開始日時</span>
                                                <span>{{ formatDateTime(c.started_at) }}</span>
                                            </span>
                                            <span v-if="c.ended_at" class="flex items-center gap-1.5">
                                                <span class="text-slate-400 font-medium">終了日時</span>
                                                <span>{{ formatDateTime(c.ended_at) }}</span>
                                            </span>
                                        </div>
                                        <!-- 本文 -->
                                        <p class="text-slate-800 whitespace-pre-wrap text-sm leading-relaxed mb-2">{{ c.content || '（内容なし）' }}</p>
                                        <!-- 添付画像（表示時も画像右上に削除可にする場合はここに削除ボタン追加可能。計画では編集フォーム内のみ） -->
                                        <div v-if="c.work_attachments?.length" class="flex flex-wrap gap-2 mb-2">
                                            <a
                                                v-for="att in c.work_attachments"
                                                :key="att.id"
                                                :href="att.url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="group relative inline-block"
                                            >
                                                <img
                                                    v-if="att.url && /\.(jpe?g|png|gif|webp)$/i.test(att.original_name || '')"
                                                    :src="att.url"
                                                    :alt="att.original_name || '画像'"
                                                    class="h-20 w-20 object-cover rounded border border-slate-200 group-hover:border-indigo-400 transition-colors"
                                                />
                                                <span
                                                    v-else
                                                    class="inline-flex items-center gap-1.5 px-2 py-1.5 rounded border border-slate-200 text-slate-600 text-xs hover:border-indigo-300"
                                                >
                                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    {{ att.original_name || '添付' }}
                                                </span>
                                                <button type="button" class="absolute top-0 right-0 w-5 h-5 bg-red-500 text-white rounded-full text-xs leading-none flex items-center justify-center hover:bg-red-600 opacity-0 group-hover:opacity-100 transition-opacity" title="画像を削除" @click.prevent="deleteWorkAttachment(att)">×</button>
                                            </a>
                                        </div>
                                        <!-- 作成日時・更新日時 -->
                                        <div v-if="c.created_at || c.updated_at" class="flex flex-wrap gap-x-5 gap-y-1 pt-2 mt-2 border-t border-slate-200/80 text-xs text-slate-500">
                                            <span v-if="c.created_at" class="flex items-center gap-1.5">
                                                <span class="text-slate-400">作成日時</span>
                                                <span>{{ formatDateTime(c.created_at) }}</span>
                                            </span>
                                            <span v-if="c.updated_at" class="flex items-center gap-1.5">
                                                <span class="text-slate-400">更新日時</span>
                                                <span>{{ formatDateTime(c.updated_at) }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                            </template>
                        </draggable>
                    </div>
                    <p v-else class="text-slate-500 text-sm">登録なし</p>
                </div>
            </div>

            <!-- 使用部品 -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-slate-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                        使用部品
                    </h2>
                    <SecondaryButton type="button" @click="showUsedPartForm = !showUsedPartForm">
                        {{ showUsedPartForm ? '閉じる' : '追加' }}
                    </SecondaryButton>
                </div>
                <div class="p-4">
                    <!-- 追加フォーム（登録済みの上に表示） -->
                    <div v-show="showUsedPartForm" class="mb-6 p-5 rounded-xl border-2 border-indigo-200 bg-indigo-50/80 space-y-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-indigo-800 flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                使用部品を追加（部品を検索して追加）
                            </h3>
                            <button type="button" @click="showUsedPartForm = false" class="text-slate-500 hover:text-slate-700 text-sm">閉じる</button>
                        </div>
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="flex-1 min-w-[200px]">
                                <InputLabel value="部品を検索（部品名・型番）" />
                                <TextInput
                                    v-model="partSearchQuery"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-indigo-200"
                                    placeholder="例: サンプル または P001"
                                    @keydown.enter.prevent="searchParts"
                                />
                            </div>
                            <SecondaryButton type="button" :disabled="partSearchLoading" @click="searchParts">
                                {{ partSearchLoading ? '検索中...' : '検索' }}
                            </SecondaryButton>
                        </div>
                        <div v-if="partSearchResults.length > 0" class="overflow-x-auto rounded-lg border border-indigo-200 bg-white">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-indigo-50/50">
                                    <tr>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">部品番号</th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">部品名</th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">型番</th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">格納先</th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">アドレス</th>
                                        <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">現在の数量</th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase w-24">数量</th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">備考</th>
                                        <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase w-20">操作</th>
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
                                        <td class="px-4 py-2">
                                            <TextInput v-model.number="p._addQty" type="number" min="1" step="1" class="block w-full text-sm rounded border-indigo-200" />
                                        </td>
                                        <td class="px-4 py-2">
                                            <TextInput v-model="p._addNote" type="text" class="block w-full text-sm rounded border-indigo-200" />
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <button
                                                type="button"
                                                class="text-indigo-600 hover:text-indigo-800 font-medium disabled:opacity-50"
                                                :disabled="usedPartForm.processing"
                                                @click="submitUsedPartFromRow(p)"
                                            >
                                                追加
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else-if="partSearchQuery && !partSearchLoading" class="text-sm text-slate-500">検索結果はありません。</p>
                    </div>

                    <!-- 登録済み使用部品 -->
                    <table v-if="work.work_used_parts?.length" class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-slate-500 border-b">
                                <th class="pb-2">部品</th>
                                <th class="pb-2 text-right">単価</th>
                                <th class="pb-2 text-right">数量</th>
                                <th class="pb-2">備考</th>
                                <th class="pb-2 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="u in work.work_used_parts" :key="u.id">
                                <td class="py-2">{{ u.part?.part_no }} - {{ u.part?.display_name ?? u.part?.name }}</td>
                                <td class="py-2 text-right">{{ u.unit_price != null ? Number(u.unit_price).toLocaleString() + '円' : '—' }}</td>
                                <td class="py-2 text-right">{{ formatUsedPartQty(u.qty) }}</td>
                                <td class="py-2">{{ u.note || '—' }}</td>
                                <td class="py-2">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="text-indigo-600 hover:text-indigo-800 text-sm font-medium"
                                            @click="openCostFormFromUsedPart(u)"
                                        >
                                            費用追加
                                        </button>
                                        <button
                                            type="button"
                                            class="text-red-600 hover:text-red-800 text-sm font-medium"
                                            @click="deleteUsedPart(u)"
                                        >
                                            削除
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-slate-500 text-sm">登録なし</p>
                </div>
            </div>

            <!-- 費用 -->
            <div ref="costFormSectionRef" class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-slate-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        費用
                    </h2>
                    <SecondaryButton type="button" @click="showCostForm = !showCostForm">
                        {{ showCostForm ? '閉じる' : '追加' }}
                    </SecondaryButton>
                </div>
                <div class="p-4">
                    <!-- 追加フォーム（登録済みの上に表示） -->
                    <form v-show="showCostForm" @submit.prevent="submitCost" class="mb-6 p-5 rounded-xl border-2 border-indigo-200 bg-indigo-50/80 space-y-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-indigo-800 flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                費用を追加
                            </h3>
                            <button type="button" @click="showCostForm = false" class="text-slate-500 hover:text-slate-700 text-sm">閉じる</button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="費用カテゴリ" required />
                                <select v-model="costForm.work_cost_category_id" class="mt-1 block w-full rounded-md border-indigo-200 shadow-sm" required>
                                    <option value="">選択</option>
                                    <option v-for="c in workCostCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="金額（円）" required />
                                <TextInput v-model="costForm.amount" type="number" min="0" class="mt-1 block w-full rounded-md border-indigo-200" required />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="名称" />
                                <TextInput v-model="costForm.name" type="text" class="mt-1 block w-full rounded-md border-indigo-200" placeholder="例: 部品費A、外注費" />
                            </div>
                            <div>
                                <InputLabel value="業者" />
                                <input
                                    v-model="costForm._vendor_name"
                                    type="text"
                                    list="vendors-datalist-show"
                                    placeholder="選択または入力"
                                    class="mt-1 block w-full rounded-md border-indigo-200 shadow-sm text-sm"
                                    @input="onCostVendorInput"
                                />
                                <datalist id="vendors-datalist-show">
                                    <option v-for="v in (vendors || [])" :key="v.id" :value="v.name" />
                                </datalist>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div><InputLabel value="発生日" /><TextInput v-model="costForm.occurred_at" type="date" class="mt-1 block w-full rounded-md border-indigo-200" /></div>
                            <div><InputLabel value="備考" /><TextInput v-model="costForm.note" type="text" class="mt-1 block w-full rounded-md border-indigo-200" /></div>
                        </div>
                        <div>
                            <InputLabel value="添付ファイル（証憑など）" />
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png,.gif" class="mt-1 block w-full text-sm text-slate-600 file:mr-2 file:rounded-md file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-white file:text-sm file:font-medium hover:file:bg-indigo-700" @change="setCostFile" />
                            <p v-if="costForm.file" class="mt-1 text-xs text-slate-500">{{ costForm.file.name }}</p>
                        </div>
                        <PrimaryButton type="submit" :disabled="costForm.processing">{{ costForm.processing ? '登録中...' : '追加する' }}</PrimaryButton>
                    </form>

                    <!-- 登録済み費用 -->
                    <table v-if="work.work_costs?.length" class="min-w-full text-sm">
                        <thead><tr class="text-left text-slate-500 border-b"><th class="pb-2">カテゴリ</th><th class="pb-2">名称</th><th class="pb-2">金額</th><th class="pb-2">業者</th><th class="pb-2">発生日</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="c in work.work_costs" :key="c.id">
                                <td class="py-2"><Badge :label="c.work_cost_category?.name ?? '—'" :color="c.work_cost_category?.color" /></td>
                                <td class="py-2">{{ c.name || '—' }}</td>
                                <td class="py-2">{{ Number(c.amount).toLocaleString() }}円</td>
                                <td class="py-2">{{ c.vendor?.name ?? c.vendor_name ?? '—' }}</td>
                                <td class="py-2">{{ formatDate(c.occurred_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-slate-500 text-sm">登録なし</p>
                </div>
            </div>

            <!-- 添付資料（独立ブロック・最下部） -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-slate-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        添付資料
                    </h2>
                    <SecondaryButton v-if="!showSummaryDocForm" type="button" @click="showSummaryDocForm = true">＋ 資料を追加</SecondaryButton>
                    <button v-else type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="showSummaryDocForm = false">閉じる</button>
                </div>
                <div class="p-4">
                    <div v-if="summaryDocuments.length" class="flex flex-wrap gap-2 mb-4">
                        <a
                            v-for="doc in summaryDocuments"
                            :key="doc.id"
                            :href="doc.url"
                            :download="doc.original_name"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 border border-indigo-200 hover:bg-indigo-200 hover:border-indigo-300 transition-colors cursor-pointer"
                        >
                            <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            {{ getDocDisplayName(doc) }}
                        </a>
                    </div>
                    <form v-show="showSummaryDocForm" @submit.prevent="submitSummaryDoc" class="p-4 rounded-lg border-2 border-indigo-200 bg-indigo-50/80 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-indigo-800">資料を追加</span>
                            <button type="button" class="text-xs text-slate-500 hover:text-slate-700" @click="showSummaryDocForm = false">閉じる</button>
                        </div>
                        <div class="flex flex-wrap gap-3 items-end">
                            <div class="min-w-[160px]">
                                <InputLabel value="表示名（タグ名）" />
                                <TextInput v-model="summaryDocForm.display_name" type="text" class="mt-1 block w-full text-sm rounded-md border-indigo-200" placeholder="例: 取扱マニュアル" />
                                <InputError :message="summaryDocForm.errors.display_name" />
                            </div>
                            <div class="min-w-[200px]">
<InputLabel value="ファイル（PDF, Excel, Word, 画像）" required />
                                        <input type="file" accept=".pdf,.xlsx,.xls,.docx,.doc,.jpg,.jpeg,.png,.gif,.webp" class="mt-1 block w-full text-sm text-slate-600 file:mr-2 file:rounded-md file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-white file:text-sm" @change="setSummaryDocFile" />
                                <p v-if="summaryDocForm.file" class="mt-1 text-xs text-slate-500">{{ summaryDocForm.file.name }}</p>
                                <InputError :message="summaryDocForm.errors.file" />
                            </div>
                            <PrimaryButton type="submit" :disabled="summaryDocForm.processing || !summaryDocForm.file">
                                {{ summaryDocForm.processing ? '登録中...' : '追加する' }}
                            </PrimaryButton>
                        </div>
                    </form>
                    <p v-if="!summaryDocuments.length && !showSummaryDocForm" class="text-slate-500 text-sm">登録なし</p>
                </div>
            </div>
            </div>

            <!-- 右: 履歴・コメント・関連作業ブロック（約30%） -->
            <div class="w-[30%] min-w-[280px] shrink-0 space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="flex border-b border-slate-200">
                        <button
                            type="button"
                            :class="[
                                'flex-1 flex items-center justify-center gap-2 py-3 text-sm font-medium transition-colors',
                                activeTab === 'history'
                                    ? 'bg-slate-100 text-slate-800 border-b-2 border-slate-800'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700',
                            ]"
                            @click="activeTab = 'history'"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            履歴
                        </button>
                        <button
                            type="button"
                            :class="[
                                'flex-1 flex items-center justify-center gap-2 py-3 text-sm font-medium transition-colors',
                                activeTab === 'comment'
                                    ? 'bg-slate-100 text-slate-800 border-b-2 border-slate-800'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700',
                            ]"
                            @click="activeTab = 'comment'"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            コメント
                        </button>
                    </div>
                    <div class="max-h-[70vh] overflow-y-auto p-4">
                        <!-- 履歴タブ -->
                        <div v-show="activeTab === 'history'" class="space-y-4">
                            <template v-if="historyActivities.length">
                                <div
                                    v-for="a in historyActivities"
                                    :key="a.id"
                                    class="text-sm border-b border-slate-100 pb-3 last:border-0"
                                >
                                    <div class="flex items-center gap-2 text-slate-500 mb-1">
                                        <span>{{ formatDateTime(a.created_at) }}</span>
                                        <span>{{ a.user?.name ?? '—' }}</span>
                                        <Badge v-if="a.work_activity_type?.name" :label="a.work_activity_type.name" :color="a.work_activity_type.color" />
                                    </div>
                                    <p class="text-slate-800 whitespace-pre-wrap">{{ a.message ?? '—' }}</p>
                                </div>
                            </template>
                            <p v-else class="text-slate-500 text-sm">履歴はありません</p>
                        </div>
                        <!-- コメントタブ -->
                        <div v-show="activeTab === 'comment'" class="space-y-4">
                            <form @submit.prevent="submitComment" class="mb-4">
                                <textarea
                                    v-model="commentForm.message"
                                    rows="3"
                                    class="block w-full rounded-md border-slate-300 shadow-sm text-sm"
                                    placeholder="コメントを入力..."
                                />
                                <InputError :message="commentForm.errors.message" />
                                <SecondaryButton type="submit" class="mt-2" :disabled="commentForm.processing">投稿</SecondaryButton>
                            </form>
                            <template v-if="commentActivities.length">
                                <div
                                    v-for="a in commentActivities"
                                    :key="a.id"
                                    class="text-sm border-b border-slate-100 pb-3 last:border-0"
                                >
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <div class="flex items-center gap-2 text-slate-500">
                                            <span>{{ formatDateTime(a.updated_at ?? a.created_at) }}</span>
                                            <span>{{ a.user?.name ?? '—' }}</span>
                                        </div>
                                        <div v-if="isOwnComment(a)" class="flex gap-1">
                                            <button
                                                v-if="editCommentId !== a.id"
                                                type="button"
                                                class="text-xs text-slate-500 hover:text-slate-700"
                                                @click="startEditComment(a)"
                                            >編集</button>
                                            <button
                                                v-if="editCommentId !== a.id"
                                                type="button"
                                                class="text-xs text-slate-500 hover:text-red-600"
                                                @click="deleteComment(a)"
                                            >削除</button>
                                        </div>
                                    </div>
                                    <div v-if="editCommentId === a.id">
                                        <textarea v-model="editCommentForm.message" rows="2" class="block w-full rounded-md border-slate-300 shadow-sm text-sm" />
                                        <InputError :message="editCommentForm.errors.message" />
                                        <div class="flex gap-2 mt-1">
                                            <SecondaryButton type="button" @click="submitEditComment" :disabled="editCommentForm.processing">保存</SecondaryButton>
                                            <button type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="cancelEditComment">キャンセル</button>
                                        </div>
                                    </div>
                                    <p v-else class="text-slate-800 whitespace-pre-wrap">{{ a.message ?? '—' }}</p>
                                </div>
                            </template>
                            <p v-else class="text-slate-500 text-sm">コメントはありません</p>
                        </div>
                    </div>
                </div>

                <!-- 関連作業 -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <h3 class="px-4 py-3 text-sm font-semibold text-slate-800 border-b border-slate-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-slate-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                        関連作業
                    </h3>
                    <div class="max-h-[40vh] overflow-y-auto p-4">
                        <template v-if="(relatedWorks ?? []).length">
                            <div
                                v-for="rw in (relatedWorks ?? [])"
                                :key="rw.id"
                                class="text-sm border-b border-slate-100 pb-3 last:border-0 cursor-pointer hover:bg-slate-50 -mx-2 px-2 rounded"
                                @click="router.visit(route('work.works.show', rw.id))"
                            >
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-indigo-600 hover:text-indigo-800 font-medium">{{ rw.title }}</span>
                                    <Badge v-if="rw.work_status?.name" :label="rw.work_status.name" :color="rw.work_status.color" />
                                </div>
                                <div class="text-slate-500 text-xs mb-1">
                                    <template v-if="(rw.equipments ?? []).length">
                                        <template v-for="(eq, ei) in rw.equipments" :key="eq.id">
                                            <span v-if="ei > 0" class="text-slate-300"> / </span>
                                            <template v-for="(p, pi) in buildEquipmentPath(eq)" :key="p.id">
                                                <span v-if="pi < buildEquipmentPath(eq).length - 1">{{ p.name }} › </span>
                                                <span v-else class="text-slate-600">{{ p.name }}</span>
                                            </template>
                                        </template>
                                    </template>
                                    <span v-else>—</span>
                                </div>
                                <div class="text-slate-400 text-xs">
                                    発生日: {{ formatDate(rw.occurred_at) }}<template v-if="rw.completed_at">　完了日: {{ formatDate(rw.completed_at) }}</template>
                                </div>
                            </div>
                        </template>
                        <p v-else class="text-slate-500 text-sm">関連する作業はありません</p>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
