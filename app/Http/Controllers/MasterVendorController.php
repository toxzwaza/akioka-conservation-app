<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class MasterVendorController extends Controller
{
    public function index()
    {
        $vendors = Vendor::orderBy('sort_order')->orderBy('id')->get();

        return Inertia::render('Master/Vendors/Index', [
            'vendors' => $vendors,
        ]);
    }

    /**
     * 並び順の変更（ドラッグ＆ドロップ用）
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['required', 'integer'],
        ]);

        $ids = $validated['order'];
        $exists = Vendor::whereIn('id', $ids)->pluck('id')->toArray();
        if (count($exists) !== count($ids) || count(array_diff($ids, $exists)) > 0) {
            abort(422, 'Invalid order: one or more IDs do not exist.');
        }

        foreach ($ids as $sort => $id) {
            Vendor::where('id', $id)->update(['sort_order' => $sort]);
        }

        Cache::forget('vendors_options');

        return redirect()->route('master.vendors.index')->with('success', '業者の並び順を更新しました。');
    }

    public function create()
    {
        return Inertia::render('Master/Vendors/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'name' => '業者名',
            'sort_order' => '並び順',
            'is_active' => '有効',
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $validated['is_active'] ?? true;

        Vendor::create($validated);

        Cache::forget('vendors_options');

        return redirect()->route('master.vendors.index')->with('success', '業者を登録しました。');
    }

    public function edit(int $id)
    {
        $item = Vendor::findOrFail($id);

        return Inertia::render('Master/Vendors/Edit', [
            'item' => $item,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $vendor = Vendor::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'name' => '業者名',
            'sort_order' => '並び順',
            'is_active' => '有効',
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $vendor->update($validated);

        Cache::forget('vendors_options');

        return redirect()->route('master.vendors.index')->with('success', '業者を更新しました。');
    }

    public function destroy(int $id)
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->delete();

        Cache::forget('vendors_options');

        return redirect()->route('master.vendors.index')->with('success', '業者を削除しました。');
    }
}
