<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MasterUserController extends Controller
{
    /**
     * 一覧表示
     */
    public function index()
    {
        $items = User::orderBy('id')->get();

        return Inertia::render('Master/Users/Index', [
            'items' => $items,
        ]);
    }

    /**
     * 新規追加画面（API検索フォーム）
     */
    public function create()
    {
        return Inertia::render('Master/Users/Create');
    }

    /**
     * Conservation API でユーザー検索（氏名の部分一致）
     */
    public function searchApi(Request $request)
    {
        $request->validate(['name' => ['nullable', 'string', 'max:255']]);
        $name = $request->input('name', '');

        $baseUrl = rtrim(config('services.conservation_api.base_url'), '/');
        $url = $baseUrl . '/users?' . http_build_query([
            'name' => $name,
            'per_page' => 100,
        ]);

        try {
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                return response()->json(['items' => [], 'message' => 'APIリクエストに失敗しました。']);
            }

            $data = $response->json();
            $items = $data['data'] ?? [];
            if (! is_array($items)) {
                $items = [];
            }

            $externalIds = User::whereNotNull('external_id')->pluck('external_id')->toArray();

            $normalized = collect($items)->map(function ($row) use ($externalIds) {
                $id = $row['id'] ?? null;
                $externalId = $id !== null ? (string) $id : '';

                return [
                    'id' => $id,
                    'external_id' => $externalId,
                    'name' => $row['name'] ?? '',
                    'email' => $row['email'] ?? null,
                    'emp_no' => $row['emp_no'] ?? null,
                    'group' => ($row['group'] ?? [])['name'] ?? null,
                    'position' => ($row['position'] ?? [])['name'] ?? null,
                    'process' => ($row['process'] ?? [])['name'] ?? null,
                    'already_registered' => $externalId !== '' && in_array($externalId, $externalIds, true),
                ];
            })->values()->all();

            return response()->json(['items' => $normalized]);
        } catch (\Throwable $e) {
            return response()->json(['items' => [], 'message' => '検索中にエラーが発生しました。']);
        }
    }

    /**
     * APIで検索したユーザーをローカルに登録（external_id と name のみ保存、API経由で情報取得）
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'external_id' => ['required', 'string', 'max:255'],
            'name'        => ['required', 'string', 'max:255'],
        ], [], [
            'external_id' => '外部ID',
            'name'        => '氏名',
        ]);

        if (User::where('external_id', $validated['external_id'])->exists()) {
            return redirect()->route('master.users.create')->with('error', 'このユーザーは既に登録されています。');
        }

        User::create([
            'external_id' => $validated['external_id'],
            'name'        => $validated['name'],
            'email'       => null,
            'password'    => null,
        ]);

        return redirect()->route('master.users.index')->with('success', 'ユーザーを追加しました。');
    }

    /**
     * 詳細表示（external_id がある場合は Conservation API から詳細取得）
     */
    public function show(int $id)
    {
        $item = User::findOrFail($id);
        $apiDetail = null;

        if ($item->external_id) {
            $baseUrl = rtrim(config('services.conservation_api.base_url'), '/');
            $url = $baseUrl . '/users/' . $item->external_id;

            try {
                $response = Http::timeout(10)->get($url);
                if ($response->successful()) {
                    $apiDetail = $response->json();
                }
            } catch (\Throwable $e) {
                // API 取得失敗時は apiDetail は null のまま
            }
        }

        return Inertia::render('Master/Users/Show', [
            'item' => $item,
            'apiDetail' => $apiDetail,
        ]);
    }

    /**
     * 編集フォーム
     */
    public function edit(int $id)
    {
        $item = User::findOrFail($id);

        return Inertia::render('Master/Users/Edit', [
            'item' => $item,
        ]);
    }

    /**
     * 更新
     */
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], [], [
            'name'  => '氏名',
            'email' => 'メールアドレス',
        ]);

        $user->update($validated);

        return redirect()->route('master.users.show', $user->id)->with('success', 'ユーザーを更新しました。');
    }

    /**
     * 削除
     */
    public function destroy(int $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('master.users.index')->with('success', 'ユーザーを削除しました。');
    }
}
