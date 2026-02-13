<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    users: Array,
});

const form = useForm({
    user_id: '',
});

const submit = () => {
    form.post(route('login'));
};
</script>

<template>
    <GuestLayout>
        <Head title="ログイン" />

        <form @submit.prevent="submit" class="space-y-6">
            <div>
                <InputLabel value="ユーザーを選択" />
                <select
                    v-model="form.user_id"
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                    required
                >
                    <option value="">選択してください</option>
                    <option
                        v-for="user in users"
                        :key="user.id"
                        :value="user.id"
                    >
                        {{ user.name }}（{{ user.email }}）
                    </option>
                </select>
                <InputError class="mt-2" :message="form.errors.user_id" />
            </div>

            <div>
                <PrimaryButton
                    class="w-full justify-center"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    ログイン
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
