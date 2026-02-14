<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NAME_TO_HEX = [
        'slate' => '#94a3b8', 'red' => '#ef4444', 'orange' => '#f97316', 'amber' => '#f59e0b',
        'yellow' => '#eab308', 'lime' => '#84cc16', 'green' => '#22c55e', 'emerald' => '#10b981',
        'teal' => '#14b8a6', 'cyan' => '#06b6d4', 'sky' => '#0ea5e9', 'blue' => '#3b82f6',
        'indigo' => '#6366f1', 'violet' => '#8b5cf6', 'purple' => '#a855f7', 'fuchsia' => '#d946ef',
        'pink' => '#ec4899', 'rose' => '#f43f5e',
    ];

    /**
     * 色名をHEXに変換（#で始まらない既存データ）
     */
    public function up(): void
    {
        $tables = [
            'work_statuses', 'work_priorities', 'work_purposes', 'work_content_tags',
            'repair_types', 'attachment_types', 'work_activity_types', 'work_cost_categories', 'users',
        ];

        foreach ($tables as $table) {
            $rows = DB::table($table)->whereNotNull('color')->where('color', '!=', '')
                ->whereRaw("color NOT LIKE '#%'")->get(['id', 'color']);

            foreach ($rows as $row) {
                $hex = self::NAME_TO_HEX[trim($row->color)] ?? null;
                if ($hex) {
                    DB::table($table)->where('id', $row->id)->update(['color' => $hex]);
                }
            }
        }
    }

    public function down(): void
    {
        // 戻しは行わない（HEX→色名の逆変換は曖昧なため）
    }
};
