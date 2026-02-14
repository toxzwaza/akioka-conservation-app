<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    node: Object,
    expandedIds: Set,
    depth: { type: Number, default: 0 },
});

const emit = defineEmits(['toggle', 'destroy']);

const depthBgClasses = [
    'bg-white',
    'bg-slate-50',
    'bg-slate-100',
    'bg-slate-200',
    'bg-slate-300',
];
const depthBgClass = depthBgClasses[Math.min(props.depth, depthBgClasses.length - 1)] ?? depthBgClasses.at(-1);

function toggle(id, event) {
    event?.stopPropagation();
    emit('toggle', id);
}

function destroy(id, event) {
    event?.stopPropagation();
    emit('destroy', id, event);
}
</script>

<template>
    <!-- この行 -->
    <tr
        :class="[
            'transition-colors',
            depthBgClass,
            depth === 0 ? 'hover:bg-slate-50' : 'hover:brightness-95',
            node.children?.length ? 'cursor-pointer' : '',
            depth === 0 && expandedIds.has(node.id) ? 'bg-slate-50' : '',
        ]"
        @click="node.children?.length ? toggle(node.id, $event) : null"
    >
        <td class="px-4 py-2 w-10" :style="depth > 0 ? { paddingLeft: `${0.5 + depth * 1.5}rem` } : {}">
            <button
                v-if="node.children?.length"
                type="button"
                class="w-6 h-6 flex items-center justify-center rounded text-slate-500 hover:bg-slate-200 transition-transform"
                :class="{ 'rotate-90': expandedIds.has(node.id) }"
                @click="toggle(node.id, $event)"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
            <span v-else class="inline-block w-6">
                <span v-if="depth > 0" class="text-slate-400 text-xs">└</span>
            </span>
        </td>
        <td class="px-4 py-2">
            <img
                v-if="node.thumbnail_url && !node._imgError"
                :src="node.thumbnail_url"
                alt=""
                :class="[depth === 0 ? 'w-12 h-12' : 'w-10 h-10', 'object-contain rounded border border-slate-200 bg-slate-50']"
                loading="lazy"
                @error="node._imgError = true"
            />
            <span
                v-else
                :class="[depth === 0 ? 'w-12 h-12' : 'w-10 h-10', 'inline-flex items-center justify-center rounded border border-slate-200 bg-slate-100 text-slate-400 text-xs']"
            >
                —
            </span>
        </td>
        <td class="px-4 py-2 whitespace-nowrap text-sm text-slate-600">{{ node.id }}</td>
        <td class="px-4 py-2 whitespace-nowrap text-sm" :class="depth === 0 ? 'text-slate-800 font-medium' : 'text-slate-700 pl-2'">{{ node.name }}</td>
        <td class="px-4 py-2 whitespace-nowrap text-sm text-slate-600">{{ node.parent?.name ?? '—' }}</td>
        <td class="px-4 py-2 whitespace-nowrap text-sm text-slate-600">{{ node.status ?? '—' }}</td>
        <td class="px-4 py-2 whitespace-nowrap text-right text-sm" @click.stop>
            <Link :href="route('master.equipments.show', node.id)" class="text-slate-600 hover:text-slate-900 mr-3">詳細</Link>
            <Link :href="route('master.equipments.edit', node.id)" class="text-slate-600 hover:text-slate-900 mr-3">編集</Link>
            <button type="button" class="text-red-600 hover:text-red-800" @click="destroy(node.id, $event)">削除</button>
        </td>
    </tr>
    <!-- 子（再帰） -->
    <template v-if="node.children?.length && expandedIds.has(node.id)">
        <EquipmentTreeRow
            v-for="child in node.children"
            :key="child.id"
            :node="child"
            :expanded-ids="expandedIds"
            :depth="depth + 1"
            @toggle="$emit('toggle', $event)"
            @destroy="(id, ev) => $emit('destroy', id, ev)"
        />
    </template>
</template>
