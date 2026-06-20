<?php

namespace App\Repositories\Analytical;

use App\Repositories\Analytical\KeterserapanRepository;
use App\Repositories\Analytical\MasaTungguRepository;
use App\Repositories\Analytical\KesesuaianRepository;
use App\Repositories\Analytical\WirausahaRepository;
use App\Repositories\Analytical\PendapatanRepository;
use App\Repositories\Analytical\SebaranInstansiRepository;
use Illuminate\Support\Collection;

/**
 * EmploymentSummaryRepository
 *
 * Tidak punya query Cube.js sendiri — hanya mengkoordinasikan 6 Repository
 * KPI yang sudah ada dan meneruskan params ke method yang established.
 * Pola sama dengan EducationSummaryService yang inject KompetensiGapRepository,
 * MetodePembelajaranRepository, PembiayaanRepository.
 *
 * Inject:
 *   - KeterserapanRepository::getDistribusiStatusSnapshot()  → card Keterserapan
 *   - MasaTungguRepository::getBarData()                    → card Kerja ≤6bln
 *   - KesesuaianRepository::getPieData()                    → card Kesesuaian
 *   - WirausahaRepository::getBarData() + getBarDataTotal() → card Wirausaha
 *   - PendapatanRepository::getGajiPerTahun()               → card Avg Pendapatan
 *   - SebaranInstansiRepository::getTingkatData()            → card Level Nasional
 *
 */
class EmploymentSummaryRepository
{
    public function __construct(
        private readonly KeterserapanRepository   $keterserapanRepo,
        private readonly MasaTungguRepository     $masaTungguRepo,
        private readonly KesesuaianRepository     $kesesuaianRepo,
        private readonly WirausahaRepository      $wirausahaRepo,
        private readonly PendapatanRepository     $pendapatanRepo,
        private readonly SebaranInstansiRepository $sebaranInstansiRepo,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  1. KETERSERAPAN — distribusi status alumni (Bekerja/Wirausaha/dll)
    // ──────────────────────────────────────────────────────────────

    /**
     * @return Collection<array{status: string, count: int}>
     */
    public function getKeterserapanData(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        return $this->keterserapanRepo->getDistribusiStatusSnapshot(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  2. MASA TUNGGU — agregat untuk pct cepat terserap (≤6bln)
    // ──────────────────────────────────────────────────────────────

    /**
     * @return Collection<array{count_alumni, count_terserap, count_masa_tunggu_cepat, ...}>
     */
    public function getMasaTungguData(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        return $this->masaTungguRepo->getBarData(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  3. KESESUAIAN — distribusi label kesesuaian bidang
    // ──────────────────────────────────────────────────────────────

    /**
     * @return Collection<array{label: string, count: int}>
     */
    public function getKesesuaianData(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        return $this->kesesuaianRepo->getPieData(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  4. WIRAUSAHA — count wirausaha + total alumni
    // ──────────────────────────────────────────────────────────────

    /**
     * @return array{wirausaha: Collection, total: Collection}
     */
    public function getWirausahaData(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): array {
        return [
            // count_wirausaha per prodi×tahun (sudah filter status=3)
            'wirausaha' => $this->wirausahaRepo->getBarData(
                jenjang:        $jenjang,
                jurusan:        $jurusan,
                namaProdi:      $namaProdi,
                tahunLulus:     $tahunLulus,
                mingguSnapshot: $mingguSnapshot,
            ),
            // total alumni tanpa filter status (untuk denominator pct)
            'total' => $this->wirausahaRepo->getBarDataTotal(
                jenjang:        $jenjang,
                jurusan:        $jurusan,
                namaProdi:      $namaProdi,
                tahunLulus:     $tahunLulus,
                mingguSnapshot: $mingguSnapshot,
            ),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  5. PENDAPATAN — avg gaji + pct above UMP
    // ──────────────────────────────────────────────────────────────

    /**
     * @return Collection<array{tahun_lulus, avg_gaji, total_alumni_ump, count_above_ump, pct_above_ump}>
     */
    public function getPendapatanData(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        // PendapatanRepository::getGajiPerTahun tidak punya param tahun_lulus
        // (karena axis X-nya memang tahun_lulus, tidak difilter)
        return $this->pendapatanRepo->getGajiPerTahun(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            mingguSnapshot: $mingguSnapshot,
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  6. SEBARAN INSTANSI — distribusi tingkat perusahaan
    // ──────────────────────────────────────────────────────────────

    /**
     * @return Collection<array{nama_prodi, jenjang, jurusan, label_tingkat, count}>
     */
    public function getTingkatData(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        return $this->sebaranInstansiRepo->getTingkatData(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
        );
    }
}