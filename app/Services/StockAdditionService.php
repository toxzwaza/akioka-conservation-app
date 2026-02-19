<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class StockAdditionService
{
    /**
     * external_id を持つ部品について、在庫加算APIをまとめて実行する。
     * 各部品の先頭の格納先（stock_storages[0]）に指定数量を加算する。
     *
     * @param  array<int, array{part_id: int, external_id: string, qty: int}>  $items  part_id, external_id, qty の配列
     * @return array{success: bool, results: array, message?: string}
     */
    public function addBatch(array $items): array
    {
        if ($items === []) {
            return ['success' => true, 'results' => []];
        }

        $baseUrl = rtrim(config('services.conservation_api.base_url'), '/');
        $payload = [];

        foreach ($items as $item) {
            $externalId = $item['external_id'] ?? '';
            $qty = (int) ($item['qty'] ?? 0);
            if ($externalId === '' || $qty < 1) {
                continue;
            }

            $url = $baseUrl . '/stocks/' . $externalId;
            try {
                $response = Http::timeout(10)->get($url);
                if (! $response->successful()) {
                    continue;
                }
                $data = $response->json();
                $storages = $data['stock_storages'] ?? [];
                if (! is_array($storages) || $storages === []) {
                    continue;
                }
                $first = $storages[0];
                $stockStorageId = $first['id'] ?? null;
                if ($stockStorageId !== null) {
                    $payload[] = [
                        'stock_storage_id' => (int) $stockStorageId,
                        'quantity' => $qty,
                    ];
                }
            } catch (\Throwable $e) {
                // 1件取得失敗時はその部品をスキップ
                continue;
            }
        }

        if ($payload === []) {
            return ['success' => true, 'results' => []];
        }

        $addUrl = $baseUrl . '/stock-storages/add';
        try {
            $response = Http::timeout(15)->asJson()->post($addUrl, $payload);
            $body = $response->json();
            $results = $body['results'] ?? [];

            if ($response->status() === 207) {
                $failed = array_filter($results, fn ($r) => ($r['success'] ?? false) === false);
                $message = count($failed) > 0
                    ? '在庫加算の一部に失敗しました。'
                    : null;
                return [
                    'success' => true,
                    'results' => $results,
                    'message' => $message,
                ];
            }

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'results' => $results,
                    'message' => '在庫加算APIの呼び出しに失敗しました。',
                ];
            }

            return ['success' => true, 'results' => $results];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'results' => [],
                'message' => '在庫加算の通信中にエラーが発生しました。',
            ];
        }
    }
}
