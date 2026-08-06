<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Transactional\PublicDisplaySettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pengaturan pengarsipan visual halaman publik (rentang tahun lulusan).
 * Dijaga middleware role:head_tracer, sama dengan pengelolaan laporan.
 */
class PublicDisplaySettingController extends Controller
{
    public function __construct(
        private readonly PublicDisplaySettingService $service,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'range'           => $this->service->getRange(),
                // Batas data nyata dikirim bersamaan supaya form pengaturan
                // bisa menunjukkan rentang yang masuk akal tanpa request kedua.
                'data_bounds'     => $this->service->getDataBounds(),
                'available_years' => $this->service->getAvailableYears(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['nullable', 'integer', 'min:' . PublicDisplaySettingService::MIN_YEAR, 'max:' . PublicDisplaySettingService::MAX_YEAR],
            // Batas akhir tidak boleh mendahului batas awal -- rentang terbalik
            // menghasilkan halaman publik kosong tanpa pesan galat apa pun.
            'end'   => ['nullable', 'integer', 'min:' . PublicDisplaySettingService::MIN_YEAR, 'max:' . PublicDisplaySettingService::MAX_YEAR, 'gte:start'],
        ]);

        $range = $this->service->setRange(
            $validated['start'] ?? null,
            $validated['end'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Rentang tahun halaman publik berhasil disimpan.',
            'data'    => [
                'range'           => $range,
                'available_years' => $this->service->getAvailableYears(),
            ],
        ]);
    }
}
