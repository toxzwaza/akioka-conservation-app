<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    parents: Array,
});

const form = useForm({
    parent_id: '',
    name: '',
    model_number: '',
    status: '稼働中',
    installed_at: '',
    vendor_contact: '',
    manufacturer: '',
    note: '',
});
</script>

<template>
    <Head title="設備 - 追加" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-2">
                <Link :href="route('master.equipments.index')" class="text-slate-600 hover:text-slate-900 text-sm">← 設備一覧</Link>
                <span class="text-slate-400">/</span>
                <h1 class="text-xl font-semibold text-slate-800 tracking-tight">設備 - 追加</h1>
            </div>
        </template>

        <div class="max-w-2xl">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
                <form @submit.prevent="form.post(route('master.equipments.store'))" class="space-y-6">
                    <div>
                        <InputLabel value="親設備" />
                        <select
                            v-model="form.parent_id"
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm"
                        >
                            <option value="">なし</option>
                            <option
                                v-for="p in parents"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.display_label ?? p.name }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">階層構造で表示されています。子設備はインデントで表示されます。</p>
                        <InputError :message="form.errors.parent_id" />
                    </div>
                    <div>
                        <InputLabel value="設備名" />
                        <TextInput v-model="form.name" type="text" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="型式" />
                        <TextInput v-model="form.model_number" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.model_number" />
                    </div>
                    <div>
                        <InputLabel value="設備状態" />
                        <TextInput v-model="form.status" type="text" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.status" />
                    </div>
                    <div>
                        <InputLabel value="設置日" />
                        <TextInput v-model="form.installed_at" type="date" class="mt-1 block w-full" />
                        <InputError :message="form.errors.installed_at" />
                    </div>
                    <div>
                        <InputLabel value="対応業者" />
                        <TextInput v-model="form.vendor_contact" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.vendor_contact" />
                    </div>
                    <div>
                        <InputLabel value="製造業者" />
                        <TextInput v-model="form.manufacturer" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.manufacturer" />
                    </div>
                    <div>
                        <InputLabel value="備考" />
                        <textarea v-model="form.note" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm" />
                        <InputError :message="form.errors.note" />
                    </div>
                    <div class="flex gap-3">
                        <PrimaryButton type="submit" :disabled="form.processing">登録</PrimaryButton>
                        <Link :href="route('master.equipments.index')" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">キャンセル</Link>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
