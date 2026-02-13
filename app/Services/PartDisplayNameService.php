<?php

namespace App\Services;

use App\Models\Part;
use App\Models\PartUserDisplayName;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class PartDisplayNameService
{
    /**
     * 部品コレクションに display_name を付与する。
     * ユーザー別表示名が設定されていればそれを使用、なければAPIの name / s_name または part.name を使用。
     *
     * @param  Collection<int, Part>  $parts
     * @return Collection<int, Part&array{display_name: string, user_display_name: string|null, api_name: string|null, api_s_name: string|null}>
     */
    public function resolveDisplayNames(Collection $parts, ?User $user = null): Collection
    {
        if ($parts->isEmpty()) {
            return collect();
        }

        $customNames = $user
            ? PartUserDisplayName::whereIn('part_id', $parts->pluck('id'))
                ->where('user_id', $user->id)
                ->get()
                ->keyBy(fn ($r) => (string) $r->part_id)
            : collect();

        $externalIds = $parts->whereNotNull('external_id')->pluck('external_id')->unique()->values();
        $apiMap = $this->fetchStocksFromApi($externalIds->toArray());

        return $parts->map(function (Part $part) use ($customNames, $apiMap, $user) {
            $api = $apiMap[$part->external_id] ?? null;
            $apiName = $api['name'] ?? null;
            $apiSName = $api['s_name'] ?? null;
            $custom = $user ? $customNames->get((string) $part->id) : null;
            $customName = $custom && trim((string) ($custom->display_name ?? '')) !== '' ? trim($custom->display_name) : null;
            $customSName = $custom && trim((string) ($custom->display_s_name ?? '')) !== '' ? trim($custom->display_s_name) : null;

            $effectiveName = $customName ?? $apiName ?? $part->name;
            $effectiveSName = $customSName ?? $apiSName;
            $displayName = $this->buildDisplayString($effectiveName ?? '', $effectiveSName ?? '');

            $arr = $part->toArray();
            $arr['display_name'] = $displayName;
            $arr['user_display_name'] = $customName;
            $arr['user_display_s_name'] = $customSName;
            $arr['api_name'] = $apiName;
            $arr['api_s_name'] = $apiSName;

            return $arr;
        });
    }

    /**
     * API から物品情報を一括取得
     *
     * @param  array<int|string>  $externalIds
     * @return array<string, array{name: string, s_name: string|null}>
     */
    private function fetchStocksFromApi(array $externalIds): array
    {
        if ($externalIds === []) {
            return [];
        }

        $ids = implode(',', array_map('strval', $externalIds));
        $baseUrl = rtrim(config('services.conservation_api.base_url'), '/');
        $url = $baseUrl . '/stocks?ids=' . $ids . '&per_page=100';

        try {
            $response = Http::timeout(10)->get($url);
            if (! $response->successful()) {
                return [];
            }
            $data = $response->json();
            $items = $data['data'] ?? [];
            if (! is_array($items)) {
                return [];
            }

            $map = [];
            foreach ($items as $row) {
                $id = $row['id'] ?? null;
                if ($id !== null) {
                    $map[(string) $id] = [
                        'name' => $row['name'] ?? '',
                        's_name' => $row['s_name'] ?? null,
                    ];
                }
            }

            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function buildDisplayString(string $name, string $sName): string
    {
        $name = trim($name);
        $sName = trim($sName);
        if ($name !== '' && $sName !== '') {
            return $name . ' (' . $sName . ')';
        }
        if ($sName !== '') {
            return $sName;
        }

        return $name !== '' ? $name : '—';
    }
}
