<?php
// app/Http/Controllers/Api/Transactional/OrgUnitTypeController.php
namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Services\Transactional\OrgUnitTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OrgUnitTypeController — HTTP untuk katalog level hierarki
 * (`org_unit_types`). Fase 4 (DFR-01, DFR-04, DFR-06): satu-satunya
 * pintu masuk HTTP ke OrgUnitTypeService, yang sejak Fase 1 sudah
 * berisi seluruh validasi bisnis (guard DFR-05, anti-siklus lewat
 * OrgUnitService, dst) -- controller ini murni menerjemahkan
 * request/response, tidak menduplikasi logic apa pun.
 */
class OrgUnitTypeController extends Controller
{
    public function __construct(
        private readonly OrgUnitTypeService $service,
    ) {}

    /** GET /api/org-unit-types?institution_type=politeknik (DFR-03) */
    public function index(Request $request): JsonResponse
    {
        $institutionType = $request->query('institution_type') ?: $this->service->activeInstitutionType();

        return response()->json([
            'success'          => true,
            'active_template'  => $this->service->activeInstitutionType(),
            'data'             => $this->service->listByInstitutionType($institutionType),
        ]);
    }

    /** GET /api/org-unit-types/active-template */
    public function activeTemplate(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['institution_type' => $this->service->activeInstitutionType()]]);
    }

    /** POST /api/org-unit-types/select-template (DFR-01) */
    public function selectTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'institution_type' => ['required', 'string', 'in:politeknik,universitas,institut,custom'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Template \"{$validated['institution_type']}\" berhasil dipilih.",
            'data'    => $this->service->selectTemplate($validated['institution_type']),
        ]);
    }

    /** POST /api/org-unit-types/custom-template (DFR-04) */
    public function defineCustomTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'levels'                 => ['required', 'array', 'min:2', 'max:5'],
            'levels.*.label'         => ['required', 'string', 'max:100'],
            'levels.*.is_required'   => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Template custom berhasil didefinisikan.',
            'data'    => $this->service->defineCustomTemplate($validated['levels']),
        ], 201);
    }

    /** PUT /api/org-unit-types/{id} — rename label saja, selalu aman (lihat OrgUnitTypeService::renameLabel). */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'label'       => ['nullable', 'string', 'max:100'],
            'level_index' => ['nullable', 'integer', 'min:1'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('label', $validated) && $validated['label'] !== null) {
            $this->service->renameLabel($id, $validated['label']);
        }

        if (array_key_exists('level_index', $validated) || array_key_exists('is_required', $validated)) {
            $this->service->changeStructure($id, $validated['level_index'] ?? null, $validated['is_required'] ?? null);
        }

        return response()->json(['success' => true, 'message' => 'Level struktur organisasi berhasil diperbarui.']);
    }

    /** DELETE /api/org-unit-types/{id} — hanya kalau belum dipakai org_units (DFR-05). Untuk level berisi data, lihat removeLevel(). */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Level struktur organisasi berhasil dihapus.']);
    }

    /** POST /api/org-unit-types/insert-level (DFR-06 wizard) */
    public function insertLevel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'institution_type' => ['required', 'string'],
            'at_level_index'   => ['required', 'integer', 'min:1'],
            'label'            => ['required', 'string', 'max:100'],
            'is_required'      => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Level \"{$validated['label']}\" berhasil disisipkan.",
            'data'    => $this->service->insertLevel(
                $validated['institution_type'],
                (int) $validated['at_level_index'],
                $validated['label'],
                $validated['is_required'] ?? true,
            ),
        ], 201);
    }

    /** POST /api/org-unit-types/{id}/remove-level (DFR-06 wizard) */
    public function removeLevel(int $id): JsonResponse
    {
        $result = $this->service->removeLevel($id);

        return response()->json([
            'success' => true,
            'message' => "Level \"{$result['removed_level']}\" berhasil dihapus. {$result['units_removed']} unit pada level itu ikut dihapus, keturunannya dipindah ke induk level yang dihapus.",
            'data'    => $result,
        ]);
    }
}
