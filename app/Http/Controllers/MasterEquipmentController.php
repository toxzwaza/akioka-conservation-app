<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MasterEquipmentController extends Controller
{
    public function index()
    {
        $all = Equipment::with('parent:id,name')->get();
        $tree = $this->buildEquipmentTree($all);

        return Inertia::render('Master/Equipments/Index', [
            'tree' => $tree,
        ]);
    }

    /**
     * 親なしをルートとし、子設備をネストした木構造を返す
     */
    private function buildEquipmentTree($equipments): array
    {
        $byParent = $equipments->groupBy('parent_id');
        $roots = ($byParent->get(null) ?? collect())->sortBy('name')->values();

        $buildNode = function ($item) use (&$buildNode, $byParent) {
            $children = ($byParent->get($item->id) ?? collect())->sortBy('name')->values();
            $node = array_merge($item->toArray(), [
                'thumbnail_url' => $item->image_path ? url('/storage/' . $item->image_path) : null,
                'children' => $children->map($buildNode)->values()->all(),
            ]);

            return $node;
        };

        return $roots->map($buildNode)->values()->all();
    }

    public function create()
    {
        return Inertia::render('Master/Equipments/Create', [
            'parents' => Equipment::getOptionsForSelect(null),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['parent_id' => $request->input('parent_id') ?: null]);

        $validated = $request->validate([
            'parent_id'       => ['nullable', 'exists:equipments,id'],
            'name'            => ['required', 'string', 'max:255'],
            'model_number'    => ['nullable', 'string', 'max:255'],
            'status'          => ['required', Rule::in(['稼働中', '休止中', '撤去済み'])],
            'installed_at'    => ['nullable', 'date'],
            'vendor_contact'  => ['nullable', 'string', 'max:255'],
            'manufacturer'    => ['nullable', 'string', 'max:255'],
            'note'            => ['nullable', 'string', 'max:65535'],
        ], [], [
            'parent_id'      => '親設備',
            'name'           => '設備名',
            'model_number'   => '型式',
            'status'         => '設備状態',
            'installed_at'   => '設置日',
            'vendor_contact' => '対応業者',
            'manufacturer'   => '製造業者',
            'note'           => '備考',
        ]);

        Equipment::create($validated);

        return redirect()->route('master.equipments.index')->with('success', '設備を登録しました。');
    }

    public function show(int $id)
    {
        $item = Equipment::with('parent:id,name')->findOrFail($id);

        $thumbnailUrl = $item->image_path
            ? url('/storage/' . $item->image_path)
            : null;

        return Inertia::render('Master/Equipments/Show', [
            'item' => $item,
            'thumbnailUrl' => $thumbnailUrl,
            'hasLocalImage' => (bool) $item->image_path,
        ]);
    }

    /**
     * 設備のサムネイル画像をアップロード
     */
    public function uploadImage(Request $request, int $id)
    {
        $equipment = Equipment::findOrFail($id);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ], [], [
            'image' => '画像',
        ]);

        if ($equipment->image_path) {
            Storage::disk('public')->delete($equipment->image_path);
        }

        $file = $request->file('image');
        $path = $file->store('equipments/' . $equipment->id, 'public');

        $equipment->update(['image_path' => $path]);

        return back()->with('success', '画像をアップロードしました。');
    }

    /**
     * 設備のアップロード画像を削除
     */
    public function destroyImage(int $id)
    {
        $equipment = Equipment::findOrFail($id);

        if ($equipment->image_path) {
            Storage::disk('public')->delete($equipment->image_path);
            $equipment->update(['image_path' => null]);
        }

        return back()->with('success', '画像を削除しました。');
    }

    public function edit(int $id)
    {
        $item = Equipment::findOrFail($id);

        return Inertia::render('Master/Equipments/Edit', [
            'item'   => $item,
            'parents' => Equipment::getOptionsForSelect($id),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $equipment = Equipment::findOrFail($id);
        $request->merge(['parent_id' => $request->input('parent_id') ?: null]);

        $validated = $request->validate([
            'parent_id'       => ['nullable', 'exists:equipments,id', Rule::notIn([$id])],
            'name'            => ['required', 'string', 'max:255'],
            'model_number'    => ['nullable', 'string', 'max:255'],
            'status'          => ['required', Rule::in(['稼働中', '休止中', '撤去済み'])],
            'installed_at'    => ['nullable', 'date'],
            'vendor_contact'  => ['nullable', 'string', 'max:255'],
            'manufacturer'    => ['nullable', 'string', 'max:255'],
            'note'            => ['nullable', 'string', 'max:65535'],
        ], [], [
            'parent_id'      => '親設備',
            'name'           => '設備名',
            'model_number'   => '型式',
            'status'         => '設備状態',
            'installed_at'   => '設置日',
            'vendor_contact' => '対応業者',
            'manufacturer'   => '製造業者',
            'note'           => '備考',
        ]);
        $validated['parent_id'] = $validated['parent_id'] ?: null;

        $equipment->update($validated);

        return redirect()->route('master.equipments.show', $equipment->id)->with('success', '設備を更新しました。');
    }

    public function destroy(int $id)
    {
        $equipment = Equipment::findOrFail($id);
        $equipment->delete();

        return redirect()->route('master.equipments.index')->with('success', '設備を削除しました。');
    }
}
