/**
 * バッジ用の色オプション
 * value: HEX形式でDBに保存
 * 未設定時はグレー（#94a3b8）で表示
 */
export const BADGE_COLOR_OPTIONS = [
    { value: '', label: '未設定（グレー）' },
    { value: '#94a3b8', label: 'グレー' },
    { value: '#ef4444', label: '赤' },
    { value: '#f97316', label: 'オレンジ' },
    { value: '#f59e0b', label: 'アンバー' },
    { value: '#eab308', label: '黄' },
    { value: '#84cc16', label: 'ライム' },
    { value: '#22c55e', label: '緑' },
    { value: '#10b981', label: 'エメラルド' },
    { value: '#14b8a6', label: 'ティール' },
    { value: '#06b6d4', label: 'シアン' },
    { value: '#0ea5e9', label: 'スカイ' },
    { value: '#3b82f6', label: '青' },
    { value: '#6366f1', label: 'インディゴ' },
    { value: '#8b5cf6', label: 'バイオレット' },
    { value: '#a855f7', label: 'パープル' },
    { value: '#d946ef', label: 'フクシア' },
    { value: '#ec4899', label: 'ピンク' },
    { value: '#f43f5e', label: 'ローズ' },
];

/** デフォルト色（未設定時） */
export const DEFAULT_BADGE_COLOR = '#94a3b8';

/** 色名→HEX変換（旧データ互換用） */
const NAME_TO_HEX = {
    slate: '#94a3b8', red: '#ef4444', orange: '#f97316', amber: '#f59e0b', yellow: '#eab308',
    lime: '#84cc16', green: '#22c55e', emerald: '#10b981', teal: '#14b8a6', cyan: '#06b6d4',
    sky: '#0ea5e9', blue: '#3b82f6', indigo: '#6366f1', violet: '#8b5cf6', purple: '#a855f7',
    fuchsia: '#d946ef', pink: '#ec4899', rose: '#f43f5e',
};

/** DBの色をフォーム用HEXに変換（色名の場合はHEXに、HEXはそのまま） */
export function toFormColor(value) {
    if (!value || typeof value !== 'string') return '';
    const v = value.trim();
    if (v.startsWith('#')) return v;
    return NAME_TO_HEX[v] ?? '';
}
