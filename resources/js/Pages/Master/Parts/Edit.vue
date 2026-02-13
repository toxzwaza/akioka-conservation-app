<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    item: Object,
});

const form = useForm({
    external_id: props.item.external_id ?? '',
    part_no: props.item.part_no,
    name: props.item.name,
    manufacturer: props.item.manufacturer ?? '',
    model_number: props.item.model_number ?? '',
    storage_location: props.item.storage_location ?? '',
});
</script>

<template>
    <Head title="部品 - 編集" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-2">
                <Link :href="route('master.parts.index')" class="text-slate-600 hover:text-slate-900 text-sm">← 部品一覧</Link>
                <span class="text-slate-400">/</span>
                <Link :href="route('master.parts.show', item.id)" class="text-slate-600 hover:text-slate-900 text-sm">詳細</Link>
                <span class="text-slate-400">/</span>
                <h1 class="text-xl font-semibold text-slate-800 tracking-tight">部品 - 編集</h1>
            </div>
        </template>

        <div class="max-w-2xl">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
                <form @submit.prevent="form.put(route('master.parts.update', item.id))" class="space-y-6">
                    <div>
                        <InputLabel value="外部ID" />
                        <TextInput v-model="form.external_id" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.external_id" />
                    </div>
                    <div>
                        <InputLabel value="部品番号" />
                        <TextInput v-model="form.part_no" type="text" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.part_no" />
                    </div>
                    <div>
                        <InputLabel value="部品名称" />
                        <TextInput v-model="form.name" type="text" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="メーカー" />
                        <TextInput v-model="form.manufacturer" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.manufacturer" />
                    </div>
                    <div>
                        <InputLabel value="型式" />
                        <TextInput v-model="form.model_number" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.model_number" />
                    </div>
                    <div>
                        <InputLabel value="保管場所" />
                        <TextInput v-model="form.storage_location" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors.storage_location" />
                    </div>
                    <div class="flex gap-3">
                        <PrimaryButton type="submit" :disabled="form.processing">更新</PrimaryButton>
                        <Link
                            :href="route('master.parts.show', item.id)"
                            class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            キャンセル
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
