<?php

namespace Tests\Unit\Repositories;

use App\Repositories\Analytical\BaseAnalyticalRepository;
use App\Services\CubeJsClient;
use Mockery;
use Tests\TestCase;

/**
 * Harness publik untuk buildGlobalFilters(), dipakai oleh SEMUA (9+)
 * repository analytical -- jenjang, jurusan, keterserapan, kesesuaian,
 * masa tunggu, pendapatan, kompetensi gap, dst. Bug di sini menular ke
 * seluruh dashboard sekaligus, jadi diuji terpisah dari repo manapun.
 */
class FilterHarnessRepository extends BaseAnalyticalRepository
{
    public function callBuildGlobalFilters(
        ?string $jenjang = null,
        ?string $jurusan = null,
        ?string $namaProdi = null,
        ?string $tahunLulus = null,
        ?string $mingguSnapshot = null,
        array $extra = [],
        ?array $idProdiIn = null,
    ): array {
        return $this->buildGlobalFilters(
            jenjang: $jenjang,
            jurusan: $jurusan,
            namaProdi: $namaProdi,
            tahunLulus: $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
            extra: $extra,
            idProdiIn: $idProdiIn,
        );
    }

    public function callLabelOrTidakDiisi(?string $value): string
    {
        return $this->labelOrTidakDiisi($value);
    }

    public function callLabelFilter(string $member, array $values): ?array
    {
        return $this->labelFilter($member, $values);
    }
}

class BaseAnalyticalRepositoryFilterTest extends TestCase
{
    private function repo(): FilterHarnessRepository
    {
        return new FilterHarnessRepository(Mockery::mock(CubeJsClient::class));
    }

    /** Tanpa filter apapun -> tidak ada batasan yang terpasang. */
    public function test_tanpa_filter_menghasilkan_array_kosong(): void
    {
        $this->assertSame([], $this->repo()->callBuildGlobalFilters());
    }

    /**
     * id_prodi_in (cakupan kajur/dekan) DAN jurusan (penyempit pilihan user)
     * harus terpasang BERSAMAAN, bukan salah satu (elseif) -- riwayat bug:
     * dekan yang memilih satu jurusan tetap melihat data seluruh fakultas
     * karena penyempitnya diam-diam tidak pernah terpasang.
     */
    public function test_id_prodi_in_dan_jurusan_terpasang_bersamaan(): void
    {
        $filters = $this->repo()->callBuildGlobalFilters(
            jurusan: 'Teknik Informatika',
            idProdiIn: [10, 11, 12],
        );

        $members = array_column($filters, 'member');

        $this->assertContains('DimProdi.id_prodi', $members);
        $this->assertContains('DimProdi.jurusan', $members);

        $idProdiFilter = collect($filters)->firstWhere('member', 'DimProdi.id_prodi');
        $this->assertSame(['10', '11', '12'], $idProdiFilter['values']);
    }

    /** idProdiIn kosong/null tidak memasang filter DimProdi.id_prodi sama sekali. */
    public function test_id_prodi_in_kosong_tidak_memasang_filter(): void
    {
        $filters = $this->repo()->callBuildGlobalFilters(idProdiIn: []);

        $members = array_column($filters, 'member');
        $this->assertNotContains('DimProdi.id_prodi', $members);
    }

    /** Filter string kosong ("") diperlakukan sama dengan null -- tidak memasang batasan. */
    public function test_string_kosong_tidak_memasang_filter(): void
    {
        $filters = $this->repo()->callBuildGlobalFilters(jenjang: '', namaProdi: '');

        $this->assertSame([], $filters);
    }

    /** jenjang, nama_prodi, tahun_lulus semua terisi -> tiga filter terpisah, nilainya sesuai. */
    public function test_semua_filter_dasar_terisi_benar(): void
    {
        $filters = $this->repo()->callBuildGlobalFilters(
            jenjang: 'D4',
            namaProdi: 'Teknik Informatika',
            tahunLulus: '2024',
        );

        $byMember = collect($filters)->keyBy('member');

        $this->assertSame(['D4'], $byMember['DimProdi.jenjang']['values']);
        $this->assertSame(['Teknik Informatika'], $byMember['DimProdi.nama_prodi']['values']);
        $this->assertSame(['2024'], $byMember['DimAlumni.tahun_lulus']['values']);
    }

    public function test_label_kosong_jadi_tidak_mengisi(): void
    {
        $this->assertSame('Tidak Mengisi', $this->repo()->callLabelOrTidakDiisi(null));
        $this->assertSame('Tidak Mengisi', $this->repo()->callLabelOrTidakDiisi('  '));
        $this->assertSame('BUMN/BUMD', $this->repo()->callLabelOrTidakDiisi('BUMN/BUMD'));
    }

    /** "Tidak Mengisi" bukan nilai di dimensi: drill-nya harus notSet, bukan equals. */
    public function test_filter_label_menerjemahkan_tidak_mengisi_ke_not_set(): void
    {
        $m = 'DimPerusahaan.label_jenis_perusahaan';

        $this->assertNull($this->repo()->callLabelFilter($m, ['']));
        $this->assertSame(
            ['member' => $m, 'operator' => 'equals', 'values' => ['BUMN/BUMD']],
            $this->repo()->callLabelFilter($m, ['BUMN/BUMD']),
        );
        $this->assertSame(
            ['member' => $m, 'operator' => 'notSet'],
            $this->repo()->callLabelFilter($m, ['Tidak Mengisi']),
        );
        $this->assertSame(
            ['or' => [
                ['member' => $m, 'operator' => 'equals', 'values' => ['Lainnya']],
                ['member' => $m, 'operator' => 'notSet'],
            ]],
            $this->repo()->callLabelFilter($m, ['Lainnya', 'Tidak Mengisi']),
        );
    }
}
