<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    item: Object,
    thumbnailUrl: { type: String, default: null },
    hasLocalImage: { type: Boolean, default: false },
});

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
    router.post(route('master.equipments.image.upload', props.item.id), formData, {
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
    router.delete(route('master.equipments.image.destroy', props.item.id), {
        preserveScroll: true,
    });
}

watch(() => props.thumbnailUrl, () => {
    imageError.value = false;
});
</script>

<template>
    <Head title="設備 - 詳細" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="route('master.equipments.index')" class="text-slate-600 hover:text-slate-900 text-sm">← 設備一覧</Link>
                    <span class="text-slate-400">/</span>
                    <h1 class="text-xl font-semibold text-slate-800 tracking-tight">設備 - 詳細</h1>
                </div>
                <Link :href="route('master.equipments.edit', item.id)" class="inline-flex items-center rounded-lg bg-slate-800 px-3 py-2 text-center text-sm font-medium text-white hover:bg-slate-700">編集</Link>
            </div>
        </template>

        <div class="max-w-2xl space-y-6">
            <!-- 画像 -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                    <h2 class="text-sm font-semibold text-slate-800">画像</h2>
                </div>
                <div class="p-4 flex flex-col gap-3">
                    <div class="w-full aspect-square max-w-40 rounded-lg border border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden shadow-sm">
                        <img
                            v-if="thumbnailUrl && !imageError"
                            :src="thumbnailUrl"
                            alt="設備画像"
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

            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <dl class="divide-y divide-slate-200">
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">ID</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.id }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">親設備</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.parent?.name ?? '—' }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">設備名</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.name }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">型式</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.model_number ?? '—' }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">設備状態</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.status ?? '—' }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">設置日</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.installed_at ?? '—' }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">対応業者</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.vendor_contact ?? '—' }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">製造業者</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2">{{ item.manufacturer ?? '—' }}</dd>
                    </div>
                    <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">備考</dt>
                        <dd class="mt-1 text-sm text-slate-900 sm:mt-0 sm:col-span-2 whitespace-pre-wrap">{{ item.note ?? '—' }}</dd>
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
        </div>
    </AuthenticatedLayout>
</template>
