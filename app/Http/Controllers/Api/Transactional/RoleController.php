<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Models\Transactional\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => Role::orderBy('id')->get(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => Role::findOrFail($id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:50|unique:oltp.roles,name',
            'label'       => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'scope'       => 'nullable|string|max:100',
        ]);

        $role = Role::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil dibuat.',
            'data'    => $role,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:50', Rule::unique('oltp.roles', 'name')->ignore($id)],
            'label'       => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'scope'       => 'nullable|string|max:100',
        ]);

        $role->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil diperbarui.',
            'data'    => $role,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil dihapus.',
        ]);
    }
}
