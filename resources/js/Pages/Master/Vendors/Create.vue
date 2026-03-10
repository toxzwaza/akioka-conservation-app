<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    sort_order: '',
    is_active: true,
});
</script>

<template>
    <Head title="業者 - 追加" />

    <AuthenticatedLayout>
        <div class="max-w-2xl">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
                <form @submit.prevent="form.post(route('master.vendors.store'))" class="space-y-6">
                    <div>
                        <InputLabel value="業者名" required />
                        <TextInput v-model="form.name" type="text" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="並び順" />
                        <TextInput v-model="form.sort_order" type="number" min="0" class="mt-1 block w-full" />
                        <InputError :message="form.errors.sort_order" />
                    </div>
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm font-medium text-slate-700">有効</span>
                        </label>
                        <InputError :message="form.errors.is_active" />
                    </div>
                    <div class="flex gap-3">
                        <PrimaryButton type="submit" :disabled="form.processing">登録</PrimaryButton>
                        <Link :href="route('master.vendors.index')" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">キャンセル</Link>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
