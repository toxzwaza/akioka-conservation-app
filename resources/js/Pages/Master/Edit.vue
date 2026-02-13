<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    masterKey: String,
    title: String,
    item: Object,
});

const form = useForm({
    name: props.item.name,
    sort_order: props.item.sort_order ?? 0,
    is_active: props.item.is_active ?? true,
});
</script>

<template>
    <Head :title="`${title} - 編集`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-2">
                <Link
                    :href="route('master.index', { masterKey })"
                    class="text-slate-600 hover:text-slate-900 text-sm"
                >
                    ← {{ title }}一覧
                </Link>
                <span class="text-slate-400">/</span>
                <Link
                    :href="route('master.show', { masterKey, id: item.id })"
                    class="text-slate-600 hover:text-slate-900 text-sm"
                >
                    詳細
                </Link>
                <span class="text-slate-400">/</span>
                <h1 class="text-xl font-semibold text-slate-800 tracking-tight">{{ title }} - 編集</h1>
            </div>
        </template>

        <div class="max-w-2xl">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
                <form @submit.prevent="form.put(route('master.update', { masterKey, id: item.id }))" class="space-y-6">
                    <div>
                        <InputLabel value="表示名" />
                        <TextInput
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="並び順" />
                        <TextInput
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.sort_order" />
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox v-model:checked="form.is_active" />
                        <InputLabel value="有効" class="!mb-0" />
                    </div>
                    <InputError :message="form.errors.is_active" />
                    <div class="flex gap-3">
                        <PrimaryButton type="submit" :disabled="form.processing">
                            更新
                        </PrimaryButton>
                        <Link
                            :href="route('master.show', { masterKey, id: item.id })"
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
