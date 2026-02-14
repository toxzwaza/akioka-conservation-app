<script setup>
import { DEFAULT_BADGE_COLOR } from '@/Constants/badgeColors';

const props = defineProps({
    /** 表示ラベル */
    label: { type: String, default: '' },
    /** 表示色（HEX、未設定時はグレー） */
    color: { type: String, default: null },
});

const NAME_TO_HEX = {
    slate: '#94a3b8', red: '#ef4444', orange: '#f97316', amber: '#f59e0b', yellow: '#eab308',
    lime: '#84cc16', green: '#22c55e', emerald: '#10b981', teal: '#14b8a6', cyan: '#06b6d4',
    sky: '#0ea5e9', blue: '#3b82f6', indigo: '#6366f1', violet: '#8b5cf6', purple: '#a855f7',
    fuchsia: '#d946ef', pink: '#ec4899', rose: '#f43f5e',
};

function getBgColor() {
    const raw = props.color && props.color.trim() !== '' ? props.color.trim() : null;
    if (!raw) return DEFAULT_BADGE_COLOR;
    if (raw.startsWith('#')) return raw;
    return NAME_TO_HEX[raw] ?? DEFAULT_BADGE_COLOR;
}

function getBadgeStyle() {
    const hex = getBgColor();
    return {
        backgroundColor: `${hex}20`,
        color: '#1e293b',
        borderColor: `${hex}40`,
        borderWidth: '1px',
        borderStyle: 'solid',
    };
}
</script>

<template>
    <span
        v-if="label != null && label !== ''"
        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold tracking-wide shadow-sm"
        :style="getBadgeStyle()"
    >
        {{ label }}
    </span>
    <span v-else class="text-slate-400">—</span>
</template>
