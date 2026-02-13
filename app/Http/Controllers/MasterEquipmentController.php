<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MasterEquipmentController extends Controller
{
    public function index()
    {
        $items = Equipment::with('parent:id,name')->orderBy('name')->get();

        return Inertia::render('Master/Equipments/Index', [
            'items' => $items,
        ]);
    }

    public function create()
    {
        $parents = Equipment::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Master/Equipments/Create', [
            'parents' => $parents,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['parent_id' => $request->input('parent_id') ?: null]);

        $validated = $request->validate([
            'parent_id'       => ['nullable', 'exists:equipments,id'],
            'name'            => ['required', 'string', 'max:255'],
            'model_number'    => ['nullable', 'string', 'max:255'],
            'status'          => ['required', 'string', 'max:255'],
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

        return Inertia::render('Master/Equipments/Show', [
            'item' => $item,
        ]);
    }

    public function edit(int $id)
    {
        $item = Equipment::findOrFail($id);
        $parents = Equipment::where('id', '!=', $id)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Master/Equipments/Edit', [
            'item'   => $item,
            'parents' => $parents,
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
            'status'          => ['required', 'string', 'max:255'],
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
