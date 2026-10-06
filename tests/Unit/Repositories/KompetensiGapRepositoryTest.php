<?php

namespace Tests\Unit\Repositories;

use App\Repositories\Analytical\KompetensiGapRepository;
use App\Services\CubeJsClient;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class KompetensiGapRepositoryTest extends TestCase
{
    /**
     * gap = skor_dibutuhkan (Kompetensi_B, dari industri) - skor_lulus
     * (Kompetensi_A, dari alumni). Dua query cube terpisah di-join manual
     * per NIM di PHP -- inilah yang diuji di sini tanpa perlu Cube.js nyala.
     */
    public function test_gap_dihitung_dari_selisih_skor_b_dikurangi_skor_a(): void
    {
        $cube = Mockery::mock(CubeJsClient::class);
        $cube->shouldReceive('load')->once()->andReturn(new Collection([
            [
                'DimAlumni.nama'        => 'Budi',
                'DimAlumni.nim'         => '2020001',
                'DimProdi.nama_prodi'   => 'Teknik Informatika',
                'DimProdi.jenjang'      => 'D4',
                'DimAlumni.tahun_lulus' => '2024',
                'FactRangeEvaluasi.avg_skor' => 3.0,
            ],
        ]));
        $cube->shouldReceive('load')->once()->andReturn(new Collection([
            [
                'DimAlumni.nim'               => '2020001',
                'FactRangeEvaluasi.avg_skor'  => 4.5,
            ],
        ]));

        $repo   = new KompetensiGapRepository($cube);
        $result = $repo->getDetailAlumni(grupGap: 'Komunikasi');

        $this->assertSame(1, $result['total_on_page']);
        $this->assertSame(3.0, $result['data'][0]['skor_lulus']);
        $this->assertSame(4.5, $result['data'][0]['skor_dibutuhkan']);
        $this->assertSame(1.5, $result['data'][0]['gap']);
    }

    /**
     * Alumni yang punya skor Kompetensi_A tapi TIDAK punya pasangan
     * Kompetensi_B (belum ada data industri untuk grup itu) harus tampil
     * dengan gap = null, bukan 0 -- 0 berarti "pas sesuai", null berarti
     * "belum bisa dinilai".
     */
    public function test_alumni_tanpa_skor_kompetensi_b_menghasilkan_gap_null(): void
    {
        $cube = Mockery::mock(CubeJsClient::class);
        $cube->shouldReceive('load')->once()->andReturn(new Collection([
            [
                'DimAlumni.nama'        => 'Siti',
                'DimAlumni.nim'         => '2020002',
                'DimProdi.nama_prodi'   => 'Akuntansi',
                'DimProdi.jenjang'      => 'D3',
                'DimAlumni.tahun_lulus' => '2024',
                'FactRangeEvaluasi.avg_skor' => 2.8,
            ],
        ]));
        $cube->shouldReceive('load')->once()->andReturn(new Collection([])); // tidak ada data B sama sekali

        $repo   = new KompetensiGapRepository($cube);
        $result = $repo->getDetailAlumni(grupGap: 'Komunikasi');

        $this->assertNull($result['data'][0]['skor_dibutuhkan']);
        $this->assertNull($result['data'][0]['gap']);
        $this->assertSame(2.8, $result['data'][0]['skor_lulus']);
    }
}
