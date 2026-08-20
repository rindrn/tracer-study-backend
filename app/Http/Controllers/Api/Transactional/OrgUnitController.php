<?php
// app/Http/Controllers/Api/Transactional/OrgUnitController.php
namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Services\Transactional\OrgUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OrgUnitController — HTTP untuk pohon unit organisasi (`org_units`).
 * Fase 4 (DFR-07/08/09/10/11): satu-satunya pintu masuk HTTP ke
 * OrgUnitService, yang sejak Fase 1 sudah berisi validasi anti-siklus
 * (DFR-09) dan guard level (assertParentIsShallower) -- controller ini
 * murni menerjemahkan request/response.
 */
class OrgUnitController extends Controller
{
    public function __construct(
        private readonly OrgUnitService $service,
    ) {}

    /** GET /api/org-units/tree?institution_type=politeknik (DFR-08) */
    public function tree(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->tree($request->query('institution_type')),
        ]);
    }

    /** GET /api/org-units/search?q=...&org_unit_type_id=... (DFR-11) */
    public function search(Request $request): JsonResponse
    {
        $orgUnitTypeId = $request->query('org_unit_type_id');

        return response()->json([
            'success' => true,
            'data'    => $this->service->search(
                $request->query('q'),
                $orgUnitTypeId !== null ? (int) $orgUnitTypeId : null,
            ),
        ]);
    }

    /** POST /api/org-units (DFR-07) */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'org_unit_type_id' => ['required', 'integer', 'exists:org_unit_types,id'],
            'name'             => ['required', 'string', 'max:150'],
            'parent_id'        => ['nullable', 'integer'],
            'is_active'        => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Unit organisasi berhasil ditambahkan.',
            'data'    => $this->service->create(
                (int) $validated['org_unit_type_id'],
                $validated['name'],
                isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
                $validated['is_active'] ?? true,
            ),
        ], 201);
    }

    /** PUT /api/org-units/{id} (DFR-07, DFR-10 rename merambat) */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $result = $this->service->update($id, $validated['name'], $validated['is_active'] ?? null);

        $affected = $result['jurusan_affected'];
        $message  = 'Unit organisasi berhasil diperbarui.';
        if ($affected['programs'] > 0 || $affected['users'] > 0) {
            $bagian = [];
            if ($affected['programs'] > 0) $bagian[] = "{$affected['programs']} program studi";
            if ($affected['users'] > 0)    $bagian[] = "{$affected['users']} akun staf";
            $message .= ' Nama baru ikut diterapkan pada ' . implode(' dan ', $bagian) . '.';
        }

        return response()->json(['success' => true, 'message' => $message, 'data' => $result]);
    }

    /** PATCH /api/org-units/{id}/reparent (DFR-09) */
    public function reparent(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer'],
        ]);

        $this->service->reparent($id, isset($validated['parent_id']) ? (int) $validated['parent_id'] : null);

        return response()->json(['success' => true, 'message' => 'Unit organisasi berhasil dipindahkan.']);
    }

    /** DELETE /api/org-units/{id} (DFR-07, nonaktifkan permanen -- untuk nonaktifkan sementara, pakai PUT is_active=false) */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Unit organisasi berhasil dihapus.']);
    }
}
