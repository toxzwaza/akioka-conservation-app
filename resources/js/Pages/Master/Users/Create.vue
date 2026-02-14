<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const searchName = ref('');
const searchLoading = ref(false);
const searchError = ref('');
const apiResults = ref([]);

async function doSearch() {
    searchError.value = '';
    apiResults.value = [];
    searchLoading.value = true;
    try {
        const { data } = await axios.post(route('master.users.search'), { name: searchName.value });
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

function addUser(user) {
    if (user.already_registered) return;
    router.post(route('master.users.store'), {
        external_id: user.external_id,
        name: user.name,
    });
}
</script>

<template>
    <Head title="ユーザー - APIから追加" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-2">
                <Link
                    :href="route('master.users.index')"
                    class="text-slate-600 hover:text-slate-900 text-sm"
                >
                    ← ユーザー一覧
                </Link>
                <span class="text-slate-400">/</span>
                <h1 class="text-xl font-semibold text-slate-800 tracking-tight">ユーザー - APIから追加</h1>
            </div>
        </template>

        <div class="max-w-full space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
                <p class="text-sm text-slate-600 mb-4">
                    Conservation API から氏名でユーザーを検索し、追加ボタンでUsersに登録します。external_id で連携します。
                </p>
                <div class="flex gap-2">
                    <input
                        v-model="searchName"
                        type="text"
                        placeholder="氏名で検索"
                        class="block w-full max-w-xs rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                        @keydown.enter.prevent="doSearch"
                    />
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
                    検索結果（追加するユーザーを選択）
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">ID</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">社員番号</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">氏名</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">メール</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">所属</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">役職</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">工程</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">操作</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <tr v-for="(row, idx) in apiResults" :key="row.external_id || idx" class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-sm text-slate-700">{{ row.external_id ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ row.emp_no ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-800">{{ row.name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ row.email ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ row.group ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ row.position ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ row.process ?? '—' }}</td>
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
                                        @click="addUser(row)"
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
