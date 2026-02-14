<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Badge from '@/Components/Badge.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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
    equipmentOptions: Array,
    users: Array,
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const activeTab = ref('history'); // 'history' | 'comment'

const historyActivities = computed(() =>
    (props.work?.work_activities ?? []).filter((a) => a.work_activity_type?.name !== 'コメント')
);
const commentActivities = computed(() =>
    (props.work?.work_activities ?? []).filter((a) => a.work_activity_type?.name === 'コメント')
);
const isOwnComment = (activity) => activity.user_id != null && authUser.value?.id === activity.user_id;

/** 設備の祖先パス（ルート→親→...→設備）を取得 */
const equipmentPath = computed(() => {
    const eq = props.work?.equipment;
    if (!eq) return [];
    const path = [];
    let cur = eq;
    while (cur) {
        path.unshift(cur);
        cur = cur.parent ?? null;
    }
    return path;
});

const formatDate = (v) => (v ? new Date(v).toLocaleDateString('ja-JP') : '—');
const formatDateTime = (v) => (v ? new Date(v).toLocaleString('ja-JP') : '—');

const contentForm = useForm({
    work_content_tag_id: '',
    repair_type_id: '',
    content: '',
    started_at: '',
    ended_at: '',
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
        onSuccess: () => usedPartForm.reset('part_id', 'qty', 'note'),
    });
}

const costForm = useForm({
    work_cost_category_id: '',
    amount: '',
    vendor_name: '',
    occurred_at: '',
    note: '',
    file: null,
});

function submitContent() {
    contentForm.post(route('work.works.work-contents.store', props.work.id), {
        preserveScroll: true,
        onSuccess: () => contentForm.reset(),
    });
}

function setCostFile(e) {
    costForm.file = e.target.files?.[0] ?? null;
}

function submitCost() {
    if (costForm.file instanceof File) {
        const fd = new FormData();
        fd.append('work_cost_category_id', costForm.work_cost_category_id);
        fd.append('amount', costForm.amount);
        if (costForm.vendor_name) fd.append('vendor_name', costForm.vendor_name);
        if (costForm.occurred_at) fd.append('occurred_at', costForm.occurred_at);
        if (costForm.note) fd.append('note', costForm.note);
        fd.append('file', costForm.file);
        costForm.processing = true;
        router.post(route('work.works.work-costs.store', props.work.id), fd, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => { costForm.processing = false; costForm.file = null; },
            onSuccess: () => costForm.reset(),
        });
    } else {
        costForm.post(route('work.works.work-costs.store', props.work.id), {
            preserveScroll: true,
            onSuccess: () => costForm.reset(),
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

const showWorkEdit = ref(false);
const workEditForm = useForm({
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
});
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
    workEditForm.equipment_id = props.work.equipment_id ?? '';
    workEditForm.work_status_id = props.work.work_status_id ?? '';
    workEditForm.work_priority_id = props.work.work_priority_id ?? '';
    workEditForm.work_purpose_id = props.work.work_purpose_id ?? '';
    workEditForm.assigned_user_id = props.work.assigned_user_id ?? '';
    workEditForm.additional_user_id = props.work.additional_user_id ?? '';
    workEditForm.production_stop_minutes = props.work.production_stop_minutes ?? '';
    workEditForm.occurred_at = toDatetimeLocal(props.work.occurred_at);
    workEditForm.started_at = toDatetimeLocal(props.work.started_at);
    workEditForm.completed_at = toDatetimeLocal(props.work.completed_at);
    workEditForm.note = props.work.note ?? '';
    showWorkEdit.value = true;
}
function submitWorkEdit() {
    workEditForm.put(route('work.works.update', props.work.id), {
        preserveScroll: true,
        onSuccess: () => { showWorkEdit.value = false; },
    });
}
</script>

<template>
    <Head :title="`作業: ${work.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('work.works.index')"
                        class="text-slate-500 hover:text-slate-700"
                    >
                        ← 一覧
                    </Link>
                    <h1 class="text-xl font-semibold text-slate-800 tracking-tight">{{ work.title }}</h1>
                </div>
            </div>
        </template>

        <div class="flex gap-6">
            <!-- 左: 詳細コンテンツ（約70%） -->
            <div class="flex-1 min-w-0 max-w-full space-y-6">

            <!-- 作業概要 -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h2 class="text-sm font-semibold text-slate-800">作業概要</h2>
                    <SecondaryButton type="button" @click="openWorkEdit">編集</SecondaryButton>
                </div>
                <dl class="px-4 py-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-slate-500">作業名</dt><dd class="font-medium text-slate-800">{{ work.title ?? '—' }}</dd></div>
                    <div>
                        <dt class="text-slate-500">設備</dt>
                        <dd class="text-slate-800">
                            <template v-if="equipmentPath.length">
                                <template v-for="(eq, i) in equipmentPath" :key="eq.id">
                                    <span v-if="i < equipmentPath.length - 1" class="text-slate-400">{{ eq.name }} › </span>
                                    <span v-else class="font-semibold">{{ eq.name }}</span>
                                </template>
                            </template>
                            <template v-else>—</template>
                        </dd>
                    </div>
                    <div><dt class="text-slate-500">作業目的</dt><dd class="font-medium text-slate-800"><Badge :label="work.work_purpose?.name ?? '—'" :color="work.work_purpose?.color" /></dd></div>
                    <div><dt class="text-slate-500">優先度</dt><dd class="font-medium text-slate-800"><Badge :label="work.work_priority?.name ?? '—'" :color="work.work_priority?.color" /></dd></div>
                    <div><dt class="text-slate-500">主担当</dt><dd class="font-medium text-slate-800"><Badge :label="work.assigned_user?.name ?? '—'" :color="work.assigned_user?.color" /></dd></div>
                    <div><dt class="text-slate-500">追加担当</dt><dd class="font-medium text-slate-800"><Badge :label="work.additional_user?.name ?? '—'" :color="work.additional_user?.color" /></dd></div>
                    <div><dt class="text-slate-500">発生日</dt><dd class="font-medium text-slate-800">{{ formatDateTime(work.occurred_at) }}</dd></div>
                    <div><dt class="text-slate-500">停止時間</dt><dd class="font-medium text-slate-800">{{ work.production_stop_minutes != null ? work.production_stop_minutes + '分' : '—' }}</dd></div>
                    <div><dt class="text-slate-500">開始日時</dt><dd class="font-medium text-slate-800">{{ formatDateTime(work.started_at) }}</dd></div>
                    <div><dt class="text-slate-500">完了日時</dt><dd class="font-medium text-slate-800">{{ formatDateTime(work.completed_at) }}</dd></div>
                    <div><dt class="text-slate-500">作成日時</dt><dd class="font-medium text-slate-800">{{ formatDateTime(work.created_at) }}</dd></div>
                    <div><dt class="text-slate-500">更新日時</dt><dd class="font-medium text-slate-800">{{ formatDateTime(work.updated_at) }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-slate-500">ステータス</dt><dd class="font-medium text-slate-800"><Badge :label="work.work_status?.name ?? '—'" :color="work.work_status?.color" /></dd></div>
                    <div v-if="work.note" class="sm:col-span-2"><dt class="text-slate-500">備考</dt><dd class="font-medium text-slate-800 whitespace-pre-wrap">{{ work.note }}</dd></div>
                </dl>
            </div>

            <!-- 作業内容 -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h2 class="text-sm font-semibold text-slate-800">作業内容</h2>
                </div>
                <div class="p-4">
                    <table v-if="work.work_contents?.length" class="min-w-full text-sm mb-4">
                        <thead><tr class="text-left text-slate-500 border-b"><th class="pb-2">タグ</th><th class="pb-2">修理内容</th><th class="pb-2">内容</th><th class="pb-2">開始〜終了</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="c in work.work_contents" :key="c.id">
                                <td class="py-2"><Badge :label="c.work_content_tag?.name ?? '—'" :color="c.work_content_tag?.color" /></td>
                                <td class="py-2"><Badge :label="c.repair_type?.name ?? '—'" :color="c.repair_type?.color" /></td>
                                <td class="py-2 whitespace-pre-wrap">{{ c.content }}</td>
                                <td class="py-2">{{ formatDateTime(c.started_at) }} ～ {{ formatDateTime(c.ended_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-slate-500 text-sm mb-4">登録なし</p>
                    <form @submit.prevent="submitContent" class="p-4 rounded-lg bg-slate-50 space-y-3">
                        <p class="text-xs font-medium text-slate-600">作業内容を追加</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="作業タグ" />
                                <select v-model="contentForm.work_content_tag_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm" required>
                                    <option value="">選択</option>
                                    <option v-for="t in workContentTags" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                                <InputError :message="contentForm.errors.work_content_tag_id" />
                            </div>
                            <div>
                                <InputLabel value="修理内容" />
                                <select v-model="contentForm.repair_type_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm" required>
                                    <option value="">選択</option>
                                    <option v-for="r in repairTypes" :key="r.id" :value="r.id">{{ r.name }}</option>
                                </select>
                                <InputError :message="contentForm.errors.repair_type_id" />
                            </div>
                        </div>
                        <div>
                            <InputLabel value="内容" />
                            <textarea v-model="contentForm.content" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm" required />
                            <InputError :message="contentForm.errors.content" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div><InputLabel value="開始日時" /><TextInput v-model="contentForm.started_at" type="datetime-local" class="mt-1 block w-full" /></div>
                            <div><InputLabel value="終了日時" /><TextInput v-model="contentForm.ended_at" type="datetime-local" class="mt-1 block w-full" /></div>
                        </div>
                        <SecondaryButton type="submit" :disabled="contentForm.processing">追加</SecondaryButton>
                    </form>
                </div>
            </div>

            <!-- 使用部品 -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                    <h2 class="text-sm font-semibold text-slate-800">使用部品</h2>
                </div>
                <div class="p-4">
                    <table v-if="work.work_used_parts?.length" class="min-w-full text-sm mb-4">
                        <thead><tr class="text-left text-slate-500 border-b"><th class="pb-2">部品</th><th class="pb-2">数量</th><th class="pb-2">備考</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="u in work.work_used_parts" :key="u.id">
                                <td class="py-2">{{ u.part?.part_no }} - {{ u.part?.display_name ?? u.part?.name }}</td>
                                <td class="py-2">{{ u.qty }}</td>
                                <td class="py-2">{{ u.note || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-slate-500 text-sm mb-4">登録なし</p>
                    <div class="p-4 rounded-lg bg-slate-50 space-y-4">
                        <p class="text-xs font-medium text-slate-600">使用部品を追加（部品を検索して追加）</p>
                        <div class="flex flex-wrap items-end gap-3">
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
                            <SecondaryButton type="button" :disabled="partSearchLoading" @click="searchParts">
                                {{ partSearchLoading ? '検索中...' : '検索' }}
                            </SecondaryButton>
                        </div>
                        <div v-if="partSearchResults.length > 0" class="overflow-x-auto rounded-lg border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50">
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
                                            <TextInput
                                                v-model.number="p._addQty"
                                                type="number"
                                                min="1"
                                                step="1"
                                                class="block w-full text-sm"
                                            />
                                        </td>
                                        <td class="px-4 py-2">
                                            <TextInput v-model="p._addNote" type="text" class="block w-full text-sm" />
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <button
                                                type="button"
                                                class="text-slate-600 hover:text-slate-900 font-medium disabled:opacity-50"
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
                </div>
            </div>

            <!-- 費用 -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                    <h2 class="text-sm font-semibold text-slate-800">費用</h2>
                </div>
                <div class="p-4">
                    <table v-if="work.work_costs?.length" class="min-w-full text-sm mb-4">
                        <thead><tr class="text-left text-slate-500 border-b"><th class="pb-2">カテゴリ</th><th class="pb-2">金額</th><th class="pb-2">業者</th><th class="pb-2">発生日</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="c in work.work_costs" :key="c.id">
                                <td class="py-2"><Badge :label="c.work_cost_category?.name ?? '—'" :color="c.work_cost_category?.color" /></td>
                                <td class="py-2">{{ Number(c.amount).toLocaleString() }}円</td>
                                <td class="py-2">{{ c.vendor_name || '—' }}</td>
                                <td class="py-2">{{ formatDate(c.occurred_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-slate-500 text-sm mb-4">登録なし</p>
                    <form @submit.prevent="submitCost" class="p-4 rounded-lg bg-slate-50 space-y-3">
                        <p class="text-xs font-medium text-slate-600">費用を追加</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="費用カテゴリ" />
                                <select v-model="costForm.work_cost_category_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm" required>
                                    <option value="">選択</option>
                                    <option v-for="c in workCostCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="金額（円）" />
                                <TextInput v-model="costForm.amount" type="number" min="0" class="mt-1 block w-full" required />
                            </div>
                        </div>
                        <div>
                            <InputLabel value="業者名" />
                            <TextInput v-model="costForm.vendor_name" type="text" class="mt-1 block w-full" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div><InputLabel value="発生日" /><TextInput v-model="costForm.occurred_at" type="date" class="mt-1 block w-full" /></div>
                            <div><InputLabel value="備考" /><TextInput v-model="costForm.note" type="text" class="mt-1 block w-full" /></div>
                        </div>
                        <div>
                            <InputLabel value="添付ファイル" />
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png,.gif" class="mt-1 block w-full text-sm text-slate-600 file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm" @change="setCostFile" />
                            <p v-if="costForm.file" class="mt-1 text-xs text-slate-500">{{ costForm.file.name }}</p>
                        </div>
                        <SecondaryButton type="submit" :disabled="costForm.processing">追加</SecondaryButton>
                    </form>
                </div>
            </div>
            </div>

            <!-- 右: 履歴・コメントブロック（約30%） -->
            <div class="w-[30%] min-w-[280px] shrink-0">
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden sticky top-4">
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
            </div>
        </div>

        <!-- 作業概要編集モーダル -->
        <Teleport to="body">
            <div v-if="showWorkEdit" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showWorkEdit = false">
                <div class="bg-white rounded-xl shadow-lg w-full max-w-lg max-h-[90vh] overflow-y-auto p-6" @click.stop>
                    <h3 class="text-lg font-semibold text-slate-800 mb-4">作業概要を編集</h3>
                    <form @submit.prevent="submitWorkEdit" class="space-y-4">
                        <div>
                            <InputLabel value="作業名" />
                            <TextInput v-model="workEditForm.title" type="text" class="mt-1 block w-full" required />
                            <InputError :message="workEditForm.errors.title" />
                        </div>
                        <div>
                            <InputLabel value="設備" />
                            <select v-model="workEditForm.equipment_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm" required>
                                <option value="">選択してください</option>
                                <option v-for="opt in equipmentOptions" :key="opt.id" :value="opt.id">{{ opt.display_label ?? opt.name }}</option>
                            </select>
                            <InputError :message="workEditForm.errors.equipment_id" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <InputLabel value="ステータス" />
                                <select v-model="workEditForm.work_status_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm" required>
                                    <option value="">選択</option>
                                    <option v-for="s in workStatuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                                <InputError :message="workEditForm.errors.work_status_id" />
                            </div>
                            <div>
                                <InputLabel value="優先度" />
                                <select v-model="workEditForm.work_priority_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm" required>
                                    <option value="">選択</option>
                                    <option v-for="p in workPriorities" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                                <InputError :message="workEditForm.errors.work_priority_id" />
                            </div>
                            <div>
                                <InputLabel value="作業目的" />
                                <select v-model="workEditForm.work_purpose_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm" required>
                                    <option value="">選択</option>
                                    <option v-for="p in workPurposes" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                                <InputError :message="workEditForm.errors.work_purpose_id" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="主担当" />
                                <select v-model="workEditForm.assigned_user_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm" required>
                                    <option value="">選択</option>
                                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                                </select>
                                <InputError :message="workEditForm.errors.assigned_user_id" />
                            </div>
                            <div>
                                <InputLabel value="追加担当" />
                                <select v-model="workEditForm.additional_user_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                                    <option value="">なし</option>
                                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                                </select>
                                <InputError :message="workEditForm.errors.additional_user_id" />
                            </div>
                        </div>
                        <div>
                            <InputLabel value="停止時間（分）" />
                            <TextInput v-model="workEditForm.production_stop_minutes" type="number" min="0" class="mt-1 block w-full" />
                            <InputError :message="workEditForm.errors.production_stop_minutes" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <InputLabel value="発生日" />
                                <TextInput v-model="workEditForm.occurred_at" type="datetime-local" class="mt-1 block w-full" />
                                <InputError :message="workEditForm.errors.occurred_at" />
                            </div>
                            <div>
                                <InputLabel value="開始日時" />
                                <TextInput v-model="workEditForm.started_at" type="datetime-local" class="mt-1 block w-full" />
                                <InputError :message="workEditForm.errors.started_at" />
                            </div>
                            <div>
                                <InputLabel value="完了日時" />
                                <TextInput v-model="workEditForm.completed_at" type="datetime-local" class="mt-1 block w-full" />
                                <InputError :message="workEditForm.errors.completed_at" />
                            </div>
                        </div>
                        <div>
                            <InputLabel value="備考" />
                            <textarea v-model="workEditForm.note" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm" />
                            <InputError :message="workEditForm.errors.note" />
                        </div>
                        <div class="flex justify-end gap-2 pt-4">
                            <button type="button" class="text-slate-600 hover:text-slate-800" @click="showWorkEdit = false">キャンセル</button>
                            <SecondaryButton type="submit" :disabled="workEditForm.processing">保存</SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
