<?php

namespace Tests\Unit\Services;

use App\Repositories\ETL\OlapLoadRepository;
use App\Services\ETL\PerusahaanDimService;
use Mockery;
use PHPUnit\Framework\TestCase;

/** Jenis instansi yang disimpan ke dim_perusahaan: label "Lainnya" + isiannya, kecuali nama lama opsi resmi. */
class PerusahaanDimJenisTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function inserted(?string $jenis, ?string $lainnya): array
    {
        $saved = null;
        $repo = Mockery::mock(OlapLoadRepository::class);
        $repo->shouldReceive('getActivePerusahaanVersion')->andReturn(null);
        $repo->shouldReceive('insertNewPerusahaanVersion')->andReturnUsing(function (array $attrs) use (&$saved) {
            $saved = $attrs;
            return 1;
        });

        (new PerusahaanDimService($repo))->syncAndResolveSk([
            'nama_perusahaan' => 'PT Uji', 'jenis_perusahaan' => $jenis, 'jenis_perusahaan_lainnya' => $lainnya,
            'kota' => 'Bandung', 'provinsi' => 'Jawa Barat', 'tingkat_instansi' => null,
        ], new \DateTimeImmutable('2026-10-18'));

        return [$saved['label_jenis_perusahaan'], $saved['jenis_perusahaan_lainnya']];
    }

    public function test_isian_lainnya_disimpan_terpisah_dari_labelnya(): void
    {
        $this->assertSame(['Lainnya', 'Freelance'], $this->inserted('Lainnya', 'Freelance'));
    }

    public function test_nama_lama_opsi_resmi_dikembalikan_ke_opsinya(): void
    {
        $this->assertSame(['Instansi pemerintah', null], $this->inserted('Lainnya', ' Lembaga Pemerintah '));
    }
}
