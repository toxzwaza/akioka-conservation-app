<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';

const PARAM_OPTIONS = [
    { key: 'name', label: '品名（部分一致）', type: 'text', placeholder: '品名を入力' },
    { key: 's_name', label: '品番（部分一致）', type: 'text', placeholder: '品番を入力' },
    { key: 'ids', label: '物品ID（カンマ区切り）', type: 'text', placeholder: '1,2,3' },
    { key: 'per_page', label: '件数/ページ', type: 'select', options: [15, 20, 50, 100] },
];

const queryParams = ref([{ key: '', value: '' }]);
const searchLoading = ref(false);
const searchError = ref('');
const apiResults = ref([]);

function getParamDef(key) {
    return PARAM_OPTIONS.find((p) => p.key === key);
}

function addQueryParam() {
    queryParams.value.push({ key: '', value: '' });
}

function removeQueryParam(index) {
    queryParams.value.splice(index, 1);
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

async function doSearch() {
    searchError.value = '';
    apiResults.value = [];
    searchLoading.value = true;
    try {
        const { data } = await axios.post(route('master.parts.search'), { query: buildQuery() });
        apiResults.value = data.items ?? [];
        if (data.message && apiResults.value.length === 0) {
            searchError.value = data.message;
        }
    } catch (e) {
        searchError.value = '検索に失敗しました。';
        apiResults.value = [];
    } finally {
        searchLoading.value = false;
    }
}

function addPart(part) {
    if (part.already_registered) return;
    router.post(route('master.parts.store'), {
        external_id: part.external_id,
    });
}
</script>

<template>
    <Head title="部品 - APIから追加" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-2">
                <Link
                    :href="route('master.parts.index')"
                    class="text-slate-600 hover:text-slate-900 text-sm"
                >
                    ← 部品一覧
                </Link>
                <span class="text-slate-400">/</span>
                <h1 class="text-xl font-semibold text-slate-800 tracking-tight">部品 - APIから追加</h1>
            </div>
        </template>

        <div class="max-w-full space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
                <p class="text-sm text-slate-600 mb-4">
                    Conservation API（物品/stocks）から検索して部品を追加します。セレクトで検索対象を選択し、検索値を入力して検索してください。
                </p>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-medium text-slate-600">検索条件（複数指定可）</label>
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
                                class="w-52 rounded-lg border-slate-300 text-sm"
                                @change="param.value = ''"
                            >
                                <option value="">選択してください</option>
                                <option
                                    v-for="opt in PARAM_OPTIONS"
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
                    <button
                        type="button"
                        class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
                        :disabled="searchLoading"
                        @click="doSearch"
                    >
                        {{ searchLoading ? '検索中...' : '検索' }}
                    </button>
                </div>
                <p v-if="searchError" class="mt-2 text-sm text-red-600">{{ searchError }}</p>
            </div>

            <div v-if="apiResults.length > 0" class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 text-sm font-medium text-slate-700">
                    検索結果（追加する部品を選択）
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase w-16">画像</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">ID</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">品名</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">品番</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">品番(stock_no)</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">価格</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">取引先</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">操作</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <tr v-for="(row, idx) in apiResults" :key="row.external_id || idx" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="relative w-12 h-12 rounded border border-slate-200 bg-slate-50 overflow-hidden flex items-center justify-center">
                                        <img
                                            v-if="row.thumbnail_url && !row._imgError"
                                            :src="row.thumbnail_url"
                                            alt=""
                                            class="w-full h-full object-contain"
                                            loading="lazy"
                                            @error="row._imgError = true"
                                        />
                                        <span
                                            v-else
                                            class="text-slate-400 text-xs"
                                        >
                                            —
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ row.external_id ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-800">{{ row.name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ row.s_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ row.stock_no ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ row.price ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ row.supplier ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        v-if="row.already_registered"
                                        type="button"
                                        class="text-slate-400 cursor-default text-sm"
                                        disabled
                                    >
                                        追加済
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                        @click="addPart(row)"
                                    >
                                        追加
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
