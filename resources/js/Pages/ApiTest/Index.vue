<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    baseUrl: {
        type: String,
        default: 'https://akioka.cloud/api',
    },
});

const methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
const method = ref('GET');
const path = ref('stocks');
const queryParams = ref([{ key: '', value: '' }]);
const body = ref('');
const loading = ref(false);
const error = ref('');
const responseStatus = ref(null);
const responseHeaders = ref([]);
const responseBody = ref(null);
const durationMs = ref(null);
const historyLogs = ref({ data: [], current_page: 1, last_page: 1 });
const historyLoading = ref(false);
const activeTab = ref('response');
const selectedRowIndex = ref(null);
const storageQuantities = ref({});

const endpointPresets = [
    { label: '物品一覧', method: 'GET', path: 'stocks' },
    { label: '物品1件', method: 'GET', path: 'stocks/1' },
    { label: '物品新規', method: 'POST', path: 'stocks' },
    { label: '物品更新', method: 'PUT', path: 'stocks/1' },
    { label: '物品削除', method: 'DELETE', path: 'stocks/1' },
    { label: '在庫減算', method: 'POST', path: 'stock-storages/subtract' },
    { label: '在庫上書き', method: 'PUT', path: 'stock-storages/10' },
    { label: 'ユーザー一覧', method: 'GET', path: 'users' },
    { label: 'ユーザー1件', method: 'GET', path: 'users/1' },
];

// パスごとのクエリパラメータ定義（べた書き）
const PARAM_OPTIONS = {
    stocks: [
        { key: 'name', label: '品名（部分一致）', type: 'text', placeholder: '品名を入力' },
        { key: 's_name', label: '品番（部分一致）', type: 'text', placeholder: '品番を入力' },
        { key: 'ids', label: '物品ID（カンマ区切り）', type: 'text', placeholder: '1,2,3' },
        { key: 'per_page', label: '件数/ページ', type: 'select', options: [15, 20, 50, 100] },
    ],
    users: [
        { key: 'name', label: '氏名（部分一致）', type: 'text', placeholder: '氏名を入力' },
        { key: 'per_page', label: '件数/ページ', type: 'select', options: [15, 20, 50, 100] },
    ],
    default: [
        { key: 'per_page', label: '件数/ページ', type: 'select', options: [15, 20, 50, 100] },
    ],
};

const needsBody = computed(() => ['POST', 'PUT', 'PATCH'].includes(method.value));

const currentParamOptions = computed(() => {
    const p = path.value.trim();
    if (p.startsWith('stocks')) return PARAM_OPTIONS.stocks;
    if (p.startsWith('users')) return PARAM_OPTIONS.users;
    return PARAM_OPTIONS.default;
});

const showQueryParams = computed(() => method.value === 'GET');

const responseStatusClass = computed(() => {
    if (responseStatus.value === null) return '';
    if (responseStatus.value >= 200 && responseStatus.value < 300) return 'bg-emerald-100 text-emerald-800';
    if (responseStatus.value >= 400) return 'bg-red-100 text-red-800';
    return 'bg-amber-100 text-amber-800';
});

function applyPreset(preset) {
    method.value = preset.method;
    path.value = preset.path;
    if (preset.method === 'POST' && preset.path === 'stocks') {
        body.value = JSON.stringify({ name: '新規品名', s_name: 'NEW-001', price: 500 }, null, 2);
    } else if (preset.path === 'stock-storages/subtract') {
        body.value = JSON.stringify({ stock_storage_id: 10, quantity: 5 }, null, 2);
    } else if (preset.path.startsWith('stock-storages/') && preset.method === 'PUT') {
        body.value = JSON.stringify({ quantity: 50 }, null, 2);
    } else {
        body.value = '';
    }
    if (preset.method === 'GET' && (preset.path === 'stocks' || preset.path === 'users')) {
        queryParams.value = [{ key: '', value: '' }];
    }
}

function addQueryParam() {
    queryParams.value.push({ key: '', value: '' });
}

function removeQueryParam(index) {
    queryParams.value.splice(index, 1);
}

function getParamDef(key) {
    return currentParamOptions.value.find((p) => p.key === key);
}

function buildQuery() {
    const q = {};
    queryParams.value.forEach(({ key, value }) => {
        if (!key) return;
        const def = getParamDef(key);
        const val = typeof value === 'string' ? value.trim() : value;
        if (def?.type === 'select') {
            if (val !== '' && val !== undefined && val !== null) q[key] = val;
        } else if (val !== '') {
            q[key] = val;
        }
    });
    return q;
}

async function execute() {
    error.value = '';
    responseStatus.value = null;
    responseBody.value = null;
    responseHeaders.value = [];
    durationMs.value = null;
    loading.value = true;

    try {
        const { data } = await axios.post(route('api-test.execute'), {
            method: method.value,
            path: path.value.trim(),
            query: buildQuery(),
            body: needsBody.value ? body.value : null,
        });

        if (data.success) {
            responseStatus.value = data.response_status;
            responseHeaders.value = Object.entries(data.response_headers || {}).flatMap(([k, v]) =>
                (Array.isArray(v) ? v : [v]).map((val) => ({ key: k, value: val }))
            );
            responseBody.value = data.response_body;
            durationMs.value = data.duration_ms;
            activeTab.value = 'response';
            loadHistory(1);
        } else {
            error.value = data.error || 'エラーが発生しました。';
        }
    } catch (e) {
        error.value = e.response?.data?.error || e.response?.data?.message || e.message || 'リクエストに失敗しました。';
    } finally {
        loading.value = false;
    }
}

function formatBody(val) {
    if (val === null || val === undefined) return '—';
    if (typeof val === 'object') return JSON.stringify(val, null, 2);
    if (typeof val === 'string') {
        try {
            return JSON.stringify(JSON.parse(val), null, 2);
        } catch {
            return val;
        }
    }
    return String(val);
}

// テーブル表示用: レスポンスから配列を抽出（data/results 配列 or 単体オブジェクト）
const tableData = computed(() => {
    const body = responseBody.value;
    if (body == null) return [];
    const data = typeof body === 'object' ? body : (() => {
        try { return JSON.parse(body); } catch { return null; }
    })();
    if (!data) return [];
    if (Array.isArray(data)) return data;
    if (data.data && Array.isArray(data.data)) return data.data;
    if (data.results && Array.isArray(data.results)) return data.results;
    if (typeof data === 'object' && data !== null && !Array.isArray(data)) return [data];
    return [];
});

// テーブル列: プリミティブ値のキーを優先、なければ全キー
const tableColumns = computed(() => {
    const rows = tableData.value;
    if (rows.length === 0) return [];
    const keys = Object.keys(rows[0]);
    const primitives = keys.filter((k) => {
        const v = rows[0][k];
        return v === null || v === undefined || typeof v === 'string' || typeof v === 'number' || typeof v === 'boolean';
    });
    return primitives.length > 0 ? primitives : keys;
});

function formatTableCell(val) {
    if (val === null || val === undefined) return '—';
    if (typeof val === 'object') return '[参照: 右のJSON]';
    return String(val);
}

function isStockRow(row) {
    return row && (row.s_name !== undefined || row.stock_no !== undefined || (row.stock_storages && Array.isArray(row.stock_storages)));
}

function isUserRow(row) {
    return row && (row.emp_no !== undefined || (row.group !== undefined && row.group !== null));
}

function isStockStorageResultRow(row) {
    return row && (row.stock_storage_id !== undefined && row.previous_quantity !== undefined);
}

function getRowActions(row) {
    if (!row || !row.id) return [];
    const actions = [];
    if (isStockRow(row)) {
        actions.push({ key: 'stock-show', label: '物品1件', method: 'GET', path: `stocks/${row.id}`, body: null });
        actions.push({ key: 'stock-update', label: '物品更新', method: 'PUT', path: `stocks/${row.id}`, body: { name: row.name || '', s_name: row.s_name || '', price: row.price } });
        actions.push({ key: 'stock-delete', label: '物品削除', method: 'DELETE', path: `stocks/${row.id}`, body: null });
    } else if (isUserRow(row)) {
        actions.push({ key: 'user-show', label: 'ユーザー1件', method: 'GET', path: `users/${row.id}`, body: null });
    } else if (isStockStorageResultRow(row)) {
        actions.push({ key: 'overwrite', label: '在庫上書き', method: 'PUT', path: `stock-storages/${row.stock_storage_id}`, body: { quantity: 50 } });
    }
    return actions;
}

function toggleRowSelection(idx) {
    selectedRowIndex.value = selectedRowIndex.value === idx ? null : idx;
}

function getSelectedRow() {
    if (selectedRowIndex.value === null) return null;
    const data = tableData.value;
    if (selectedRowIndex.value < 0 || selectedRowIndex.value >= data.length) return null;
    return data[selectedRowIndex.value] ?? null;
}

async function executeQuickAction(action) {
    const row = getSelectedRow();
    if (!row && action.key !== 'stock-create') return;
    method.value = action.method;
    path.value = action.path;
    body.value = action.body ? JSON.stringify(action.body, null, 2) : '';
    queryParams.value = [{ key: '', value: '' }];
    selectedRowIndex.value = null;
    await execute();
}

function getGlobalActions() {
    return [
        { key: 'stock-create', label: '物品新規', method: 'POST', path: 'stocks', body: { name: '新規品名', s_name: 'NEW-001', price: 500 } },
    ];
}

function getSelectedStockStorages() {
    const row = getSelectedRow();
    if (!row || !Array.isArray(row.stock_storages)) return [];
    return row.stock_storages;
}

function getStorageAddress(storage) {
    const addr = storage?.storage_address;
    return addr?.address ?? '—';
}

function getStorageLocation(storage) {
    const loc = storage?.storage_address?.location;
    return loc?.name ?? '—';
}

function getStorageQty(storageId, actionType) {
    const key = `${storageId}-${actionType}`;
    return storageQuantities.value[key] ?? (actionType === 'subtract' ? 5 : 50);
}

function setStorageQty(storageId, actionType, val) {
    const key = `${storageId}-${actionType}`;
    const num = Number(val);
    storageQuantities.value = { ...storageQuantities.value, [key]: isNaN(num) ? (actionType === 'subtract' ? 5 : 50) : num };
}

async function executeStorageAction(storage, actionType) {
    if (!storage?.id) return;
    const quantity = getStorageQty(storage.id, actionType);
    if (actionType === 'subtract') {
        method.value = 'POST';
        path.value = 'stock-storages/subtract';
        body.value = JSON.stringify({ stock_storage_id: storage.id, quantity }, null, 2);
    } else if (actionType === 'overwrite') {
        method.value = 'PUT';
        path.value = `stock-storages/${storage.id}`;
        body.value = JSON.stringify({ quantity }, null, 2);
    }
    queryParams.value = [{ key: '', value: '' }];
    selectedRowIndex.value = null;
    await execute();
}

async function loadHistory(page = 1) {
    historyLoading.value = true;
    try {
        const { data } = await axios.get(route('api-test.history'), { params: { page } });
        historyLogs.value = data;
    } catch {
        historyLogs.value = { data: [], current_page: 1, last_page: 1 };
    } finally {
        historyLoading.value = false;
    }
}

function loadFromHistory(log) {
    const url = new URL(log.url);
    path.value = url.pathname.replace(/^\//, '').replace(/^\/?api\/?/, '') || 'stocks';
    method.value = log.method;
    body.value = log.request_body || '';
    activeTab.value = 'request';
}

watch(
    () => activeTab.value,
    (tab) => {
        if (tab === 'history' && historyLogs.value.data.length === 0) loadHistory(1);
    }
);
</script>

<template>
    <Head title="Conservation API テスト" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-slate-800 tracking-tight">Conservation API テスト</h1>
        </template>

        <div class="space-y-6">
            <p class="text-sm text-slate-600">
                物品・在庫格納先・ユーザーAPIのテスト実行と実行履歴の確認ができます。ベースURL:
                <code class="rounded bg-slate-100 px-1 py-0.5 text-slate-800">{{ baseUrl }}</code>
            </p>

            <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
                <h2 class="text-sm font-semibold text-slate-700 mb-4">クイック選択</h2>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="preset in endpointPresets"
                        :key="preset.label"
                        type="button"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        @click="applyPreset(preset)"
                    >
                        {{ preset.label }}
                    </button>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700">リクエスト</h2>

                <div class="flex flex-wrap items-center gap-2">
                    <select
                        v-model="method"
                        class="rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                    >
                        <option v-for="m in methods" :key="m" :value="m">{{ m }}</option>
                    </select>
                    <span class="text-slate-500">/</span>
                    <input
                        v-model="path"
                        type="text"
                        placeholder="stocks, users, stock-storages/subtract..."
                        class="flex-1 min-w-[200px] rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                    />
                </div>

                <div v-if="showQueryParams">
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-sm font-medium text-slate-600">クエリパラメータ（複数条件指定可）</label>
                        <button
                            type="button"
                            class="text-xs text-slate-500 hover:text-slate-700"
                            @click="addQueryParam"
                        >
                            + 条件を追加
                        </button>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="(param, idx) in queryParams"
                            :key="idx"
                            class="flex gap-2 items-center flex-wrap"
                        >
                            <select
                                v-model="param.key"
                                class="w-48 rounded-lg border-slate-300 text-sm"
                                @change="param.value = ''"
                            >
                                <option value="">選択してください</option>
                                <option
                                    v-for="opt in currentParamOptions"
                                    :key="opt.key"
                                    :value="opt.key"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                            <template v-if="param.key">
                                <span class="text-slate-400">=</span>
                                <select
                                    v-if="getParamDef(param.key)?.type === 'select'"
                                    v-model="param.value"
                                    class="w-24 rounded-lg border-slate-300 text-sm"
                                >
                                    <option value="">—</option>
                                    <option
                                        v-for="v in getParamDef(param.key)?.options"
                                        :key="v"
                                        :value="String(v)"
                                    >
                                        {{ v }}
                                    </option>
                                </select>
                                <input
                                    v-else
                                    v-model="param.value"
                                    type="text"
                                    :placeholder="getParamDef(param.key)?.placeholder"
                                    class="flex-1 min-w-[120px] rounded-lg border-slate-300 text-sm"
                                />
                            </template>
                            <button
                                v-if="queryParams.length > 1"
                                type="button"
                                class="text-slate-400 hover:text-red-600 shrink-0"
                                @click="removeQueryParam(idx)"
                            >
                                × 削除
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="needsBody">
                    <label class="block text-sm font-medium text-slate-600 mb-1">リクエスト Body (JSON)</label>
                    <textarea
                        v-model="body"
                        rows="8"
                        placeholder='{"name": "新規品名", "s_name": "NEW-001"}'
                        class="w-full rounded-lg border-slate-300 font-mono text-sm"
                    />
                </div>

                <button
                    type="button"
                    class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
                    :disabled="loading || !path.trim()"
                    @click="execute"
                >
                    {{ loading ? '実行中...' : '実行' }}
                </button>
            </div>

            <p v-if="error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                {{ error }}
            </p>

            <div
                v-if="responseStatus !== null || (historyLogs.data && historyLogs.data.length > 0)"
                class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden"
            >
                <div class="flex border-b border-slate-200">
                    <button
                        type="button"
                        class="px-4 py-3 text-sm font-medium"
                        :class="activeTab === 'response' ? 'border-b-2 border-slate-800 text-slate-800' : 'text-slate-500 hover:text-slate-700'"
                        @click="activeTab = 'response'"
                    >
                        レスポンス
                    </button>
                    <button
                        type="button"
                        class="px-4 py-3 text-sm font-medium"
                        :class="activeTab === 'history' ? 'border-b-2 border-slate-800 text-slate-800' : 'text-slate-500 hover:text-slate-700'"
                        @click="activeTab = 'history'"
                    >
                        実行履歴
                    </button>
                </div>

                <div v-show="activeTab === 'response'" class="p-6 space-y-4">
                    <div v-if="responseStatus !== null" class="flex flex-wrap items-center gap-2">
                        <span
                            :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', responseStatusClass]"
                        >
                            {{ responseStatus }}
                        </span>
                        <span v-if="durationMs !== null" class="text-sm text-slate-500">
                            {{ durationMs }} ms
                        </span>
                    </div>
                    <div v-if="responseHeaders.length > 0">
                        <p class="text-xs font-medium text-slate-500 mb-1">レスポンスヘッダー</p>
                        <pre class="overflow-x-auto rounded bg-slate-50 p-2 text-xs">{{ responseHeaders.map(h => `${h.key}: ${h.value}`).join('\n') }}</pre>
                    </div>
                    <div
                        v-if="responseBody !== null"
                        class="grid grid-cols-1 lg:grid-cols-2 gap-4"
                    >
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500 mb-2">検索結果（テーブル・行クリックで操作）</p>
                            <div
                                v-if="tableData.length > 0"
                                class="rounded-lg border border-slate-200 overflow-hidden overflow-x-auto max-h-96 overflow-y-auto"
                            >
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50 sticky top-0">
                                        <tr>
                                            <th
                                                v-for="col in tableColumns"
                                                :key="col"
                                                scope="col"
                                                class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase whitespace-nowrap"
                                            >
                                                {{ col }}
                                            </th>
                                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase whitespace-nowrap w-16">
                                                操作
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-200">
                                        <tr
                                            v-for="(row, idx) in tableData"
                                            :key="idx"
                                            :class="[
                                                'hover:bg-slate-50 cursor-pointer',
                                                selectedRowIndex === idx ? 'bg-slate-100' : '',
                                            ]"
                                            @click="toggleRowSelection(idx)"
                                        >
                                            <td
                                                v-for="col in tableColumns"
                                                :key="col"
                                                class="px-3 py-2 text-slate-700 whitespace-nowrap max-w-[200px] truncate"
                                                :title="formatTableCell(row[col])"
                                            >
                                                {{ formatTableCell(row[col]) }}
                                            </td>
                                            <td class="px-3 py-2">
                                                <span
                                                    v-if="getRowActions(row).length > 0 || isStockRow(row)"
                                                    class="text-xs text-slate-500"
                                                >
                                                    {{ selectedRowIndex === idx ? '▲ 閉じる' : '▼ 操作' }}
                                                </span>
                                                <span v-else class="text-slate-300">—</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div
                                v-if="selectedRowIndex !== null && (getRowActions(getSelectedRow()).length > 0 || (isStockRow(getSelectedRow()) && getGlobalActions().length > 0))"
                                class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-3"
                            >
                                <p class="text-xs font-medium text-slate-600">選択行の操作（クリックでAPI実行）</p>
                                <div class="flex flex-wrap gap-2">
                                    <template v-for="action in getGlobalActions()" :key="action.key">
                                        <button
                                            v-if="isStockRow(getSelectedRow())"
                                            type="button"
                                            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50"
                                            :disabled="loading"
                                            @click.stop="executeQuickAction(action)"
                                        >
                                            {{ action.label }}
                                        </button>
                                    </template>
                                    <button
                                        v-for="action in getRowActions(getSelectedRow())"
                                        :key="action.key"
                                        type="button"
                                        class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50"
                                        :disabled="loading"
                                        @click.stop="executeQuickAction(action)"
                                    >
                                        {{ action.label }}
                                    </button>
                                </div>
                                <div
                                    v-if="isStockRow(getSelectedRow()) && getSelectedStockStorages().length > 0"
                                    class="mt-3 pt-3 border-t border-slate-200"
                                >
                                    <p class="text-xs font-medium text-slate-600 mb-2">在庫格納先（減算・上書きはここから）</p>
                                    <div class="overflow-x-auto rounded border border-slate-200 bg-white">
                                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                                            <thead class="bg-slate-50">
                                                <tr>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">格納先ID</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">アドレス</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">場所</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">数量</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">減算</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">上書き</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-200">
                                                <tr
                                                    v-for="storage in getSelectedStockStorages()"
                                                    :key="storage.id"
                                                    class="hover:bg-slate-50"
                                                >
                                                    <td class="px-3 py-2 text-slate-700">{{ storage.id }}</td>
                                                    <td class="px-3 py-2 text-slate-700">{{ getStorageAddress(storage) }}</td>
                                                    <td class="px-3 py-2 text-slate-700">{{ getStorageLocation(storage) }}</td>
                                                    <td class="px-3 py-2 text-slate-700">{{ storage.quantity ?? '—' }}</td>
                                                    <td class="px-3 py-2">
                                                        <div class="flex items-center gap-1">
                                                            <input
                                                                type="number"
                                                                min="1"
                                                                class="w-16 rounded border-slate-300 text-sm text-right"
                                                                :value="getStorageQty(storage.id, 'subtract')"
                                                                @input="setStorageQty(storage.id, 'subtract', ($event.target).value)"
                                                            />
                                                            <button
                                                                type="button"
                                                                class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50"
                                                                :disabled="loading"
                                                                @click.stop="executeStorageAction(storage, 'subtract')"
                                                            >
                                                                実行
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <div class="flex items-center gap-1">
                                                            <input
                                                                type="number"
                                                                min="0"
                                                                class="w-16 rounded border-slate-300 text-sm text-right"
                                                                :value="getStorageQty(storage.id, 'overwrite')"
                                                                @input="setStorageQty(storage.id, 'overwrite', ($event.target).value)"
                                                            />
                                                            <button
                                                                type="button"
                                                                class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50"
                                                                :disabled="loading"
                                                                @click.stop="executeStorageAction(storage, 'overwrite')"
                                                            >
                                                                実行
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <p v-else-if="tableData.length === 0" class="text-sm text-slate-500 py-4">テーブル表示するデータがありません</p>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500 mb-2">レスポンス Body (JSON)</p>
                            <pre class="overflow-x-auto max-h-96 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm font-mono whitespace-pre-wrap">{{ formatBody(responseBody) }}</pre>
                        </div>
                    </div>
                    <p v-if="responseStatus === null && !error" class="text-sm text-slate-500">実行して結果を表示</p>
                </div>

                <div v-show="activeTab === 'history'" class="p-6">
                    <div v-if="historyLoading" class="text-sm text-slate-500">読み込み中...</div>
                    <div v-else-if="!historyLogs.data || historyLogs.data.length === 0" class="text-sm text-slate-500">
                        履歴はまだありません
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="log in historyLogs.data"
                            :key="log.id"
                            class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50"
                        >
                            <div class="min-w-0 flex-1">
                                <span
                                    :class="[
                                        'inline-block w-12 rounded px-1.5 py-0.5 text-xs font-medium',
                                        log.response_status >= 200 && log.response_status < 300 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600',
                                    ]"
                                >
                                    {{ log.method }}
                                </span>
                                <span class="ml-2 text-sm text-slate-700 truncate">{{ log.url }}</span>
                                <span
                                    v-if="log.response_status"
                                    class="ml-2 text-xs text-slate-500"
                                >
                                    {{ log.response_status }} · {{ log.duration_ms }}ms
                                </span>
                            </div>
                            <button
                                type="button"
                                class="ml-2 shrink-0 text-xs text-slate-500 hover:text-slate-700"
                                @click="loadFromHistory(log)"
                            >
                                再実行
                            </button>
                        </div>
                        <div
                            v-if="historyLogs.last_page > 1"
                            class="flex justify-center gap-2 pt-4"
                        >
                            <button
                                type="button"
                                class="rounded border border-slate-300 px-2 py-1 text-sm disabled:opacity-50"
                                :disabled="historyLogs.current_page <= 1"
                                @click="loadHistory(historyLogs.current_page - 1)"
                            >
                                前へ
                            </button>
                            <span class="py-1 text-sm text-slate-600">
                                {{ historyLogs.current_page }} / {{ historyLogs.last_page }}
                            </span>
                            <button
                                type="button"
                                class="rounded border border-slate-300 px-2 py-1 text-sm disabled:opacity-50"
                                :disabled="historyLogs.current_page >= historyLogs.last_page"
                                @click="loadHistory(historyLogs.current_page + 1)"
                            >
                                次へ
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
