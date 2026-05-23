<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Models\Transactional\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::with('program')->orderBy('id')->get()->map(fn ($u) => $this->formatUser($u));

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:oltp.users,email',
            'password'   => 'required|string|min:6',
            'role'       => ['required', Rule::in(User::ROLES_ALL)],
            'program_id' => 'nullable|integer|exists:oltp.programs,id',
            'jurusan'    => 'nullable|string|max:100',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);
        $user->load('program');

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dibuat.',
            'data'    => $this->formatUser($user),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => ['required', 'email', Rule::unique('oltp.users', 'email')->ignore($id)],
            'password'   => 'nullable|string|min:6',
            'role'       => ['required', Rule::in(User::ROLES_ALL)],
            'program_id' => 'nullable|integer|exists:oltp.programs,id',
            'jurusan'    => 'nullable|string|max:100',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        $user->load('program');

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diperbarui.',
            'data'    => $this->formatUser($user),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        User::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'User berhasil dihapus.']);
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['status' => !$user->status]);
        $user->load('program');

        return response()->json([
            'success' => true,
            'message' => $user->status ? 'User berhasil diaktifkan.' : 'User berhasil dinonaktifkan.',
            'data'    => $this->formatUser($user),
        ]);
    }

    private function formatUser(User $user): array
    {
        $scope = match ($user->role) {
            User::ROLE_HEAD_TRACER, User::ROLE_TRACER_TEAM, User::ROLE_WADIR => 'Seluruh Jurusan',
            User::ROLE_KAJUR => $user->jurusan ?? '-',
            User::ROLE_KAPRODI => $user->program ? ($user->program->name . ' (' . $user->program->degree . ')') : '-',
            default => '-',
        };

        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'role'         => $user->role,
            'scope'        => $scope,
            'status'       => $user->status,
            'program_id'   => $user->program_id,
            'program_name' => $user->program ? ($user->program->name . ' (' . $user->program->degree . ')') : null,
            'jurusan'      => $user->jurusan,
            'created_at'   => $user->created_at,
        ];
    }
}
