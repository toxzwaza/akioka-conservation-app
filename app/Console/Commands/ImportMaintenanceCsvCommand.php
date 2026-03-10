<?php

namespace App\Console\Commands;

use App\Models\Equipment;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Work;
use App\Models\WorkContent;
use App\Models\WorkCost;
use App\Models\WorkContentTag;
use App\Models\WorkCostCategory;
use App\Models\WorkPriority;
use App\Models\WorkPurpose;
use App\Models\WorkStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportMaintenanceCsvCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maintana:import-csv
                            {file=移行用CSV/メンテナ移行データ.csv : CSVファイルパス}
                            {--dry-run : ログのみ出力しDBには登録しない（デフォルト）}
                            {--execute : 実際にDBへ登録する}
                            {--skip-unknown-equipment : 設備が見つからない行をスキップ}
                            {--default-user-id= : 担当者未登録時のデフォルト user_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'メンテナ移行データCSVを読み込み、作業・作業内容・費用を登録';


    /**
     * CSVカラムインデックス
     */
    private const COL_OCCURRED_AT = 0;
    private const COL_COMPLETED_AT = 1;
    private const COL_STATUS = 2;
    private const COL_TITLE = 3;
    private const COL_CONTENT = 4;
    private const COL_ASSIGNED_USER = 5;
    private const COL_ADDITIONAL_USERS = 6;
    private const COL_VENDOR = 7;
    private const COL_PARENT_EQUIPMENT = 8;
    private const COL_EQUIPMENT = 9;
    private const COL_PRIORITY = 10;
    private const COL_WORK_PURPOSE = 11;
    private const COL_WORK_CONTENT_TAG = 12;

    private string $logPath = '';

    private int $okCount = 0;

    private int $skipCount = 0;

    private int $errorCount = 0;

    /** CSVの文字エンコーディング（UTF-8 or CP932） */
    private string $csvEncoding = 'UTF-8';

    /** 主担当未登録時のフォールバック用ユーザーID（設備保全システム用未登録ユーザー） */
    private ?int $unregisteredUserId = null;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = base_path($this->argument('file'));

        if (! file_exists($filePath)) {
            $this->error("CSVファイルが見つかりません: {$filePath}");

            return self::FAILURE;
        }

        $this->logPath = storage_path('logs/maintana_import_' . date('Ymd_His') . '.log');
        $this->okCount = 0;
        $this->skipCount = 0;
        $this->errorCount = 0;

        // 主担当未登録時のフォールバック用ユーザーを1回だけ取得
        $unregisteredUser = User::where('name', '設備保全システム用未登録ユーザー')->first();
        $this->unregisteredUserId = $unregisteredUser?->id;

        file_put_contents($this->logPath, "\xEF\xBB\xBF", LOCK_EX);

        $execute = $this->option('execute');
        $modeLabel = $execute ? '本番登録' : 'ドライラン（ログのみ）';
        $this->log("=== メンテナ移行データインポート（{$modeLabel}）開始 ===");
        $this->log("開始時刻: " . now()->format('Y-m-d H:i:s'));
        $this->log("対象CSV: {$filePath}");
        $this->log("モード: " . ($execute ? 'execute（DB登録あり）' : 'dry-run（ログのみ）'));
        $this->log("オプション: skip-unknown-equipment=" . ($this->option('skip-unknown-equipment') ? 'true' : 'false'));
        $this->log("---");

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->error("CSVを開けませんでした: {$filePath}");

            return self::FAILURE;
        }

        // BOM付きUTF-8の場合はBOMをスキップ、なければShift_JIS(CP932)と仮定
        $bom = fread($handle, 3);
        if ($bom === "\xEF\xBB\xBF") {
            $this->csvEncoding = 'UTF-8';
        } else {
            rewind($handle);
            $this->csvEncoding = 'CP932';
        }

        // ヘッダー行を読み飛ばす
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if ($header === false) {
            $this->log("ERROR: ヘッダー行の読み取りに失敗");
            fclose($handle);

            return self::FAILURE;
        }

        $rowNum = 1;

        while (($row = $this->readCsvRow($handle)) !== null) {
            $rowNum++;

            if (count($row) < 13) {
                $this->logRow($rowNum, $row[self::COL_TITLE] ?? '(タイトルなし)', 'SKIP', '列数不足');
                $this->skipCount++;
                continue;
            }

            $this->processRow($rowNum, $row, $execute);
        }

        fclose($handle);

        $this->log("---");
        $this->log("=== 集計 ===");
        $this->log("処理件数: " . ($this->okCount + $this->skipCount + $this->errorCount));
        $this->log("OK" . ($execute ? '（登録完了）' : '（登録予定）') . ": {$this->okCount}");
        $this->log("SKIP: {$this->skipCount}");
        $this->log("ERROR: {$this->errorCount}");
        $this->log("終了時刻: " . now()->format('Y-m-d H:i:s'));
        $this->log("=== 完了 ===");

        $this->info("ログを出力しました: {$this->logPath}");

        return self::SUCCESS;
    }

    /**
     * CSVの1行を読み込む（fgetcsv はダブルクォート内の改行を正しく処理する）
     * Shift_JIS(CP932)の場合はUTF-8に変換して返す
     */
    private function readCsvRow($handle): ?array
    {
        $row = fgetcsv($handle, 0, ',', '"', '\\');
        if ($row === false) {
            return null;
        }
        if ($this->csvEncoding === 'CP932') {
            $row = array_map(
                fn ($cell) => mb_convert_encoding((string) $cell, 'UTF-8', 'CP932'),
                $row
            );
        }

        return $row;
    }

    /**
     * 1行分のデータを処理
     *
     * @param  array<string, mixed>  $row
     */
    private function processRow(int $rowNum, array $row, bool $execute = false): void
    {
        $title = trim($row[self::COL_TITLE] ?? '');
        $contentRaw = trim($row[self::COL_CONTENT] ?? '');
        $equipmentName = trim($row[self::COL_EQUIPMENT] ?? '');
        $assignedUserName = trim($row[self::COL_ASSIGNED_USER] ?? '');
        $additionalUsersRaw = trim($row[self::COL_ADDITIONAL_USERS] ?? '');
        $vendorName = trim($row[self::COL_VENDOR] ?? '');
        $workPurposeName = trim($row[self::COL_WORK_PURPOSE] ?? '');
        $workContentTagRaw = trim($row[self::COL_WORK_CONTENT_TAG] ?? '');

        if ($title === '') {
            $this->logRow($rowNum, '(タイトルなし)', 'SKIP', '作業名が空');
            $this->skipCount++;

            return;
        }

        // 設備照合
        $equipment = Equipment::where('name', $equipmentName)->first();
        if (! $equipment) {
            if ($this->option('skip-unknown-equipment')) {
                $this->logRow($rowNum, $title, 'SKIP', "設備未登録: {$equipmentName}");
                $this->skipCount++;

                return;
            }
            $equipment = Equipment::orderBy('id')->first();
            $this->logRow($rowNum, $title, 'OK', "設備「{$equipmentName}」は未登録のため暫定で equipment_id={$equipment?->id} を使用");
        }

        if (! $equipment) {
            $this->logRow($rowNum, $title, 'ERROR', '設備が1件も登録されていません');
            $this->errorCount++;

            return;
        }

        // 主担当ユーザー照合
        $defaultUserId = $this->option('default-user-id') ? (int) $this->option('default-user-id') : User::orderBy('id')->first()?->id;
        $assignedUser = null;
        if ($assignedUserName !== '') {
            $assignedUser = User::where('name', 'LIKE', '%' . $assignedUserName . '%')->first();
        }
        // 主担当が見つからない・未指定の場合は設備保全システム用未登録ユーザー → オプション/先頭ユーザー
        $assignedUserId = $assignedUser?->id ?? $this->unregisteredUserId ?? $defaultUserId;

        if (! $assignedUserId) {
            $this->logRow($rowNum, $title, 'ERROR', '担当者が1件も登録されていません');
            $this->errorCount++;

            return;
        }

        if ($assignedUser === null) {
            $fallbackLabel = ($assignedUserId === $this->unregisteredUserId) ? '設備保全システム用未登録ユーザー' : 'デフォルト';
            $reason = $assignedUserName !== '' ? "担当者「{$assignedUserName}」は未登録のため{$fallbackLabel}" : "担当者が未指定のため{$fallbackLabel}";
            $this->logRow($rowNum, $title, 'OK', "{$reason} user_id={$assignedUserId} を使用");
        }

        // 追加担当
        $additionalUserIds = [];
        if ($additionalUsersRaw !== '') {
            $names = preg_split('/[\r\n]+/u', $additionalUsersRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($names as $name) {
                $name = trim($name);
                if ($name === '') {
                    continue;
                }
                $u = User::where('name', 'LIKE', '%' . $name . '%')->first();
                if ($u) {
                    $additionalUserIds[] = $u->id;
                }
            }
        }

        // ステータス・優先度・作業目的
        $workStatus = WorkStatus::where('name', trim($row[self::COL_STATUS] ?? ''))->first()
            ?? WorkStatus::where('name', '完了')->first();
        $workPriority = WorkPriority::where('name', trim($row[self::COL_PRIORITY] ?? ''))->first();
        $workPurpose = WorkPurpose::where('name', $workPurposeName)->first()
            ?? WorkPurpose::where('name', 'その他')->first();

        if (! $workStatus) {
            $this->logRow($rowNum, $title, 'ERROR', 'ワークステータスが取得できません');
            $this->errorCount++;

            return;
        }

        if (! $workPurpose) {
            $this->logRow($rowNum, $title, 'ERROR', '作業目的が取得できません');
            $this->errorCount++;

            return;
        }

        // 作業タグ（改行区切りで複数登録、システム登録文言と一致するためそのまま検索）
        $workContentTags = [];
        $workContentTagNames = preg_split('/[\r\n]+/u', $workContentTagRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($workContentTagNames as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $tag = WorkContentTag::where('name', $name)->first();
            if ($tag && ! in_array($tag->id, array_column($workContentTags, 'id'), true)) {
                $workContentTags[] = $tag;
            }
        }
        // 1件も見つからない場合は「その他」を使用
        if (empty($workContentTags)) {
            $defaultTag = WorkContentTag::where('name', 'その他')->first();
            if ($defaultTag) {
                $workContentTags[] = $defaultTag;
            }
        }
        if (empty($workContentTags)) {
            $this->logRow($rowNum, $title, 'ERROR', '作業タグが取得できません');
            $this->errorCount++;

            return;
        }

        // 日時パース
        $occurredAt = $this->parseDateTime($row[self::COL_OCCURRED_AT] ?? '');
        $completedAt = $this->parseDateTime($row[self::COL_COMPLETED_AT] ?? '');

        // 費用カテゴリ（業者のみの場合は外注費 or その他）
        $workCostCategory = WorkCostCategory::where('name', '外注費')->first()
            ?? WorkCostCategory::where('name', 'その他')->first()
            ?? WorkCostCategory::orderBy('id')->first();

        // 登録予定データを構築
        $workData = [
            'title' => $title,
            'work_status_id' => $workStatus->id,
            'work_priority_id' => $workPriority?->id,
            'assigned_user_id' => $assignedUserId,
            'occurred_at' => $occurredAt,
            'completed_at' => $completedAt,
            'note' => null,
        ];

        $equipmentIds = [$equipment->id];
        $workPurposeIds = [$workPurpose->id];

        $workContentData = null;
        if ($contentRaw !== '' && ! empty($workContentTags)) {
            $workContentData = [
                'content' => $contentRaw,
                'work_content_tag_ids' => array_map(fn ($t) => $t->id, $workContentTags),
            ];
        }

        $workCostData = null;
        if ($vendorName !== '') {
            $workCostData = [
                'work_cost_category_id' => $workCostCategory?->id,
                'amount' => 0,
                'vendor_name' => $vendorName,
                'occurred_at' => $occurredAt ? $occurredAt->format('Y-m-d') : null,
            ];
        }

        // ログ用の表示データ（日本語名で取得）
        $assignedUserNameForLog = User::find($assignedUserId)?->name ?? "（ID:{$assignedUserId}）";
        $additionalUserNames = User::whereIn('id', $additionalUserIds)->pluck('name')->toArray();

        $detail = [
            'work_summary' => [
                'タイトル' => $title,
                'ステータス' => $workStatus->name,
                '優先度' => $workPriority?->name ?? '（未設定）',
                '主担当' => $assignedUserNameForLog,
                '発生日時' => $occurredAt ? $occurredAt->format('Y-m-d H:i') : '（未設定）',
                '完了日時' => $completedAt ? $completedAt->format('Y-m-d H:i') : '（未設定）',
                '設備' => $equipment->name,
                '作業目的' => $workPurpose->name,
                '追加担当' => empty($additionalUserNames) ? '（なし）' : implode('、', $additionalUserNames),
            ],
            'work_content' => $workContentData ? [
                '内容' => $contentRaw,
                '作業タグ' => implode('、', array_map(fn ($t) => $t->name, $workContentTags)),
            ] : null,
            'work_cost' => $workCostData ? [
                '業者名' => $vendorName,
                '費用カテゴリ' => $workCostCategory?->name ?? '（なし）',
                '金額' => ($workCostData['amount'] ?? 0) . '円',
                '発生日' => $workCostData['occurred_at'] ?? '（なし）',
            ] : null,
        ];

        if ($execute) {
            try {
                DB::transaction(function () use ($workData, $equipmentIds, $workPurposeIds, $additionalUserIds, $workContentData, $workCostData, $vendorName) {
                    $work = Work::create($workData);

                    foreach ($equipmentIds as $sort => $equipmentId) {
                        $work->equipments()->attach($equipmentId, ['sort_order' => $sort]);
                    }
                    $work->workPurposes()->sync($workPurposeIds);
                    $additionalIdsWithOrder = [];
                    foreach ($additionalUserIds as $sort => $userId) {
                        $additionalIdsWithOrder[$userId] = ['sort_order' => $sort];
                    }
                    $work->additionalUsers()->sync($additionalIdsWithOrder);

                    if ($workContentData !== null) {
                        $wc = WorkContent::withoutGlobalScope('order')->create([
                            'work_id' => $work->id,
                            'display_order' => 0,
                            'content' => $workContentData['content'],
                            'started_at' => null,
                            'ended_at' => null,
                        ]);
                        $wc->workContentTags()->sync($workContentData['work_content_tag_ids']);
                    }

                    if ($workCostData !== null) {
                        $vendorId = null;
                        if ($vendorName !== '') {
                            $vendor = Vendor::firstOrCreate(
                                ['name' => $vendorName],
                                ['is_active' => true, 'sort_order' => 0]
                            );
                            $vendorId = $vendor->id;
                            Cache::forget('vendors_options');
                        }
                        WorkCost::create([
                            'work_id' => $work->id,
                            'work_cost_category_id' => $workCostData['work_cost_category_id'],
                            'amount' => $workCostData['amount'],
                            'vendor_id' => $vendorId,
                            'vendor_name' => $vendorId ? null : $vendorName,
                            'occurred_at' => $workCostData['occurred_at'],
                        ]);
                    }
                });
            } catch (\Throwable $e) {
                $this->logRow($rowNum, $title, 'ERROR', 'DB登録失敗: ' . $e->getMessage());
                $this->errorCount++;

                return;
            }
        }

        $reason = $execute ? '登録完了' : '登録予定';
        $this->logRow($rowNum, $title, 'OK', $reason, $detail);
        $this->okCount++;
    }

    /**
     * 日時文字列をパース
     */
    private function parseDateTime(?string $value): ?\DateTime
    {
        $value = trim($value ?? '');
        if ($value === '') {
            return null;
        }

        $formats = ['Y/m/d H:i', 'Y/m/d H:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y/m/d', 'Y-m-d'];
        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $value);
            if ($dt !== false) {
                return $dt;
            }
        }

        return null;
    }

    /**
     * ログファイルに1行書き込み
     */
    private function log(string $message): void
    {
        file_put_contents($this->logPath, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
        Log::channel('single')->info('[maintana-import] ' . $message);
    }

    /**
     * 1行分の結果をログに出力
     */
    private function logRow(int $rowNum, string $title, string $status, string $reason, ?array $detail = null): void
    {
        $line = sprintf('[行%d] %s | %s | %s', $rowNum, $status, $title, $reason);
        $this->log($line);

        if ($detail !== null && $status === 'OK') {
            // 【作業概要】
            if (! empty($detail['work_summary'])) {
                $this->log('  【作業概要】');
                foreach ($detail['work_summary'] as $label => $value) {
                    if ($value !== '' && $value !== null) {
                        $this->log("    {$label}: {$value}");
                    }
                }
            }
            // 【作業内容】
            if (! empty($detail['work_content'])) {
                $this->log('  【作業内容】');
                foreach ($detail['work_content'] as $label => $value) {
                    if ($value === '' || $value === null) {
                        continue;
                    }
                    if ($label === '内容' && is_string($value)) {
                        $this->log('    内容:');
                        foreach (explode("\n", $value) as $contentLine) {
                            $this->log('      ' . $contentLine);
                        }
                    } else {
                        $this->log("    {$label}: {$value}");
                    }
                }
            }
            // 【費用】
            if (! empty($detail['work_cost'])) {
                $this->log('  【費用】');
                foreach ($detail['work_cost'] as $label => $value) {
                    if ($value !== '' && $value !== null) {
                        $this->log("    {$label}: {$value}");
                    }
                }
            }
        }
    }
}
