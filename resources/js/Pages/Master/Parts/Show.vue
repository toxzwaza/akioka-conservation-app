<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    item: Object,
    apiDetail: Object,
    allEquipments: { type: Array, default: () => [] },
    thumbnailUrl: { type: String, default: null },
    hasLocalImage: { type: Boolean, default: false },
});

// API連携時はAPIの値を、未連携時はローカルの item を表示（ユーザー独自設定があればそれを優先）
const display = computed(() => {
    const api = props.apiDetail;
    const item = props.item;
    const originalName = api?.name ?? item?.name ?? '—';
    const originalSName = api?.s_name ?? '—';
    const customName = (item?.user_display_name && String(item.user_display_name).trim()) ? item.user_display_name : null;
    const customSName = (item?.user_display_s_name && String(item.user_display_s_name).trim()) ? item.user_display_s_name : null;
    return {
        name: customName ?? originalName,
        s_name: customSName ?? originalSName,
        original_name: originalName,
        original_s_name: originalSName,
        has_custom_name: !!customName,
        has_custom_s_name: !!customSName,
        stock_no: api?.stock_no ?? '—',
        jan_code: api?.jan_code ?? '—',
        price: api?.price != null ? Number(api.price).toLocaleString() : '—',
    };
});

const stockStorages = computed(() => props.apiDetail?.stock_storages ?? []);
const stockSuppliers = computed(() => props.apiDetail?.stock_suppliers ?? []);
const aliases = computed(() => props.apiDetail?.aliases ?? []);

const displayNameForm = useForm({
    display_name: props.item.user_display_name ?? '',
    display_s_name: props.item.user_display_s_name ?? '',
});

watch(() => [props.item.user_display_name, props.item.user_display_s_name], ([name, sName]) => {
    displayNameForm.display_name = name ?? '';
    displayNameForm.display_s_name = sName ?? '';
});

watch(() => props.thumbnailUrl, () => {
    imageError.value = false;
});

function submitDisplayName() {
    displayNameForm.put(route('master.parts.update-display-name', props.item.id), {
        preserveScroll: true,
    });
}

function clearDisplayName() {
    displayNameForm.display_name = '';
    displayNameForm.display_s_name = '';
    displayNameForm.put(route('master.parts.update-display-name', props.item.id), {
        preserveScroll: true,
    });
}

const memoForm = useForm({
    memo: props.item.memo ?? '',
});

watch(() => props.item.memo, (memo) => {
    memoForm.memo = memo ?? '';
});

function submitMemo() {
    memoForm.put(route('master.parts.update-memo', props.item.id), {
        preserveScroll: true,
    });
}

// 設備紐づけ
const linkedEquipments = computed(() => props.item.equipments ?? []);
const equipmentOptions = computed(() => {
    const linkedIds = linkedEquipments.value.map((e) => e.id);
    return (props.allEquipments ?? []).filter((e) => !linkedIds.includes(e.id));
});

const equipmentAttachForm = useForm({
    equipment_id: '',
    note: '',
});

function submitAttachEquipment() {
    if (!equipmentAttachForm.equipment_id) return;
    equipmentAttachForm.post(route('master.parts.equipments.attach', props.item.id), {
        preserveScroll: true,
        onSuccess: () => {
            equipmentAttachForm.equipment_id = '';
            equipmentAttachForm.note = '';
        },
    });
}

function detachEquipment(equipmentId) {
    if (!confirm('この設備との紐づけを解除しますか？')) return;
    router.delete(route('master.parts.equipments.detach', { partId: props.item.id, equipmentId }), {
        preserveScroll: true,
    });
}

// 画像アップロード
const imageUploading = ref(false);
const imageInputRef = ref(null);
const imageError = ref(false);

function triggerImageUpload() {
    imageInputRef.value?.click();
}

function onImageSelected(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('image', file);
    imageUploading.value = true;
    router.post(route('master.parts.image.upload', props.item.id), formData, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            imageUploading.value = false;
            event.target.value = '';
        },
    });
}

function removeImage() {
    if (!confirm('アップロードした画像を削除しますか？')) return;
    router.delete(route('master.parts.image.destroy', props.item.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="部品 - 詳細" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('master.parts.index')"
                        class="text-slate-600 hover:text-slate-900 text-sm"
                    >
                        ← 部品一覧
                    </Link>
                    <span class="text-slate-400">/</span>
                    <h1 class="text-xl font-semibold text-slate-800 tracking-tight">部品 - 詳細</h1>
                </div>
            </div>
        </template>

        <div class="max-w-full space-y-6">
            <div v-if="apiDetail" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
                API連携済み：Conservation API から取得した情報を表示しています
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- 左カラム：画像+自分の表示名・基本情報・メモ・別名 -->
                <div class="lg:col-span-2 space-y-6">
            <!-- 画像と自分の表示名（横並び・半々） -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- 画像（左） -->
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-slate-500 mb-2">画像</div>
                        <div class="flex flex-col gap-3">
                            <div class="w-full aspect-square max-w-40 rounded-lg border border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden shadow-sm">
                                <img
                                    v-if="thumbnailUrl && !imageError"
                                    :src="thumbnailUrl"
                                    alt="部品画像"
                                    class="w-full h-full object-contain p-1"
                                    @error="imageError = true"
                                />
                                <span
                                    v-else
                                    class="text-slate-400 text-xs"
                                >
                                    画像なし
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <input
                                    ref="imageInputRef"
                                    type="file"
                                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                    class="hidden"
                                    @change="onImageSelected"
                                />
                                <button
                                    type="button"
                                    :disabled="imageUploading"
                                    class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 transition-colors"
                                    @click="triggerImageUpload"
                                >
                                    {{ imageUploading ? 'アップロード中...' : '選択' }}
                                </button>
                                <button
                                    v-if="hasLocalImage"
                                    type="button"
                                    :disabled="imageUploading"
                                    class="inline-flex items-center rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50 transition-colors"
                                    @click="removeImage"
                                >
                                    削除
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- 自分の表示名（右） -->
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-slate-500 mb-2">自分の表示名</div>
                        <p class="text-xs text-slate-500 mb-3">品名・型番/品番を自分用に設定できます。未設定の項目はAPIの値が表示されます。</p>
                        <form @submit.prevent="submitDisplayName" class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">品名</label>
                                <input
                                    v-model="displayNameForm.display_name"
                                    type="text"
                                    maxlength="255"
                                    placeholder="例: ○○メーカー製 電源ユニット"
                                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                                />
                                <p v-if="displayNameForm.errors.display_name" class="mt-1 text-sm text-red-600">{{ displayNameForm.errors.display_name }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">型番・品番</label>
                                <input
                                    v-model="displayNameForm.display_s_name"
                                    type="text"
                                    maxlength="255"
                                    placeholder="例: PWR-001"
                                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                                />
                                <p v-if="displayNameForm.errors.display_s_name" class="mt-1 text-sm text-red-600">{{ displayNameForm.errors.display_s_name }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button
                                    type="submit"
                                    :disabled="displayNameForm.processing"
                                    class="inline-flex items-center rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
                                >
                                    保存
                                </button>
                                <button
                                    v-if="item.user_display_name || item.user_display_s_name"
                                    type="button"
                                    @click="clearDisplayName"
                                    :disabled="displayNameForm.processing"
                                    class="text-sm text-slate-500 hover:text-slate-700 disabled:opacity-50"
                                >
                                    表示名を解除
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <dl class="divide-y divide-slate-200">
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">ID</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.id }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">外部ID</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.external_id ?? '—' }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">部品番号</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.part_no }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">部品名称</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">
                            <template v-if="display.has_custom_name">{{ display.name }} / <span class="text-slate-400">{{ display.original_name }}</span></template>
                            <template v-else>{{ display.name }}</template>
                        </dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">型番・品番</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">
                            <template v-if="display.has_custom_s_name">{{ display.s_name }} / <span class="text-slate-400">{{ display.original_s_name }}</span></template>
                            <template v-else>{{ display.s_name }}</template>
                        </dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">物品番号</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ display.stock_no }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">JANコード</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ display.jan_code }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">単価</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ display.price }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">登録日時</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.created_at }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">更新日時</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.updated_at }}</dd>
                    </div>
                </dl>
            </div>

            <!-- メモ（在庫格納先の上） -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                    <h2 class="text-sm font-semibold text-slate-800">メモ</h2>
                </div>
                <form @submit.prevent="submitMemo" class="p-4">
                    <textarea
                        v-model="memoForm.memo"
                        rows="4"
                        placeholder="部品に関するメモを記入できます"
                        class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                    />
                    <p v-if="memoForm.errors.memo" class="mt-1 text-sm text-red-600">{{ memoForm.errors.memo }}</p>
                    <div class="mt-3">
                        <button
                            type="submit"
                            :disabled="memoForm.processing"
                            class="inline-flex items-center rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
                        >
                            保存
                        </button>
                    </div>
                </form>
            </div>

            <!-- 別名 -->
            <div v-if="aliases.length" class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                    <h2 class="text-sm font-semibold text-slate-800">別名</h2>
                </div>
                <ul class="divide-y divide-slate-200">
                    <li v-for="a in aliases" :key="a.id" class="px-4 py-2 text-sm text-slate-900">{{ a.alias ?? '—' }}</li>
                </ul>
            </div>

                </div>

                <!-- 右カラム：在庫格納先・取引先・設備紐づけ -->
                <div class="space-y-6">
            <!-- 在庫格納先 -->
            <div v-if="stockStorages.length" class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                    <h2 class="text-sm font-semibold text-slate-800">在庫格納先</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">格納先名</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">アドレス</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">数量</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <tr v-for="ss in stockStorages" :key="ss.id" class="hover:bg-slate-50">
                                <td class="px-4 py-2 text-sm text-slate-900">{{ ss.storage_address?.location?.name ?? '—' }}</td>
                                <td class="px-4 py-2 text-sm text-slate-600">{{ ss.storage_address?.address ?? '—' }}</td>
                                <td class="px-4 py-2 text-sm text-slate-900 text-right">{{ ss.quantity != null ? Number(ss.quantity).toLocaleString() : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-2 bg-slate-50 border-t border-slate-200 text-sm text-slate-600">
                    合計: {{ stockStorages.reduce((s, ss) => s + (Number(ss.quantity) || 0), 0).toLocaleString() }}
                </div>
            </div>

            <!-- 取引先 -->
            <div v-if="stockSuppliers.length" class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                    <h2 class="text-sm font-semibold text-slate-800">取引先</h2>
                </div>
                <ul class="divide-y divide-slate-200">
                    <li v-for="sup in stockSuppliers" :key="sup.id" class="px-4 py-2 flex items-center justify-between">
                        <span class="text-sm text-slate-900">{{ sup.supplier?.name ?? '—' }}</span>
                        <span v-if="sup.main_flg" class="text-xs px-2 py-0.5 rounded bg-slate-200 text-slate-700">主</span>
                    </li>
                </ul>
            </div>

            <!-- 設備紐づけ -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                    <h2 class="text-sm font-semibold text-slate-800">設備紐づけ</h2>
                    <p class="text-xs text-slate-500 mt-1">この部品を使用する設備を複数紐づけできます。</p>
                </div>
                <div class="p-4 space-y-4">
                    <ul v-if="linkedEquipments.length" class="divide-y divide-slate-200 border border-slate-200 rounded-lg overflow-hidden">
                        <li v-for="eq in linkedEquipments" :key="eq.id" class="px-4 py-3 flex items-center justify-between gap-2 bg-white hover:bg-slate-50">
                            <div class="min-w-0 flex-1">
                                <Link :href="route('master.equipments.show', eq.id)" class="text-sm font-medium text-slate-800 hover:text-slate-600">{{ eq.name }}</Link>
                                <p v-if="eq.pivot?.note" class="text-xs text-slate-500 mt-0.5">{{ eq.pivot.note }}</p>
                            </div>
                            <button
                                type="button"
                                @click="detachEquipment(eq.id)"
                                class="shrink-0 text-sm text-red-600 hover:text-red-800"
                            >
                                解除
                            </button>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-slate-500">紐づけている設備はありません。</p>
                    <form v-if="equipmentOptions.length" @submit.prevent="submitAttachEquipment" class="flex flex-wrap items-end gap-2">
                        <div class="min-w-[200px] flex-1">
                            <label class="block text-xs font-medium text-slate-600 mb-1">設備を追加</label>
                            <select
                                v-model="equipmentAttachForm.equipment_id"
                                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                            >
                                <option value="">選択してください</option>
                                <option v-for="e in equipmentOptions" :key="e.id" :value="e.id">{{ e.name }}</option>
                            </select>
                        </div>
                        <div class="min-w-[160px] flex-1">
                            <label class="block text-xs font-medium text-slate-600 mb-1">メモ（任意）</label>
                            <input
                                v-model="equipmentAttachForm.note"
                                type="text"
                                placeholder="例: 標準装備"
                                class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                            />
                        </div>
                        <button
                            type="submit"
                            :disabled="equipmentAttachForm.processing || !equipmentAttachForm.equipment_id"
                            class="inline-flex items-center rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
                        >
                            追加
                        </button>
                    </form>
                    <p v-else-if="allEquipments?.length" class="text-sm text-slate-500">すべての設備を紐づけ済みです。</p>
                </div>
            </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
