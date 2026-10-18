<?php

namespace Tests\Unit\Services;

use App\Services\ETL\AlumniFactBuilderService;
use ReflectionClass;
use Tests\TestCase;

/**
 * Masa tunggu 0 dari ekspor kementerian: sah atau sel kosong, ditentukan
 * dari f301 (role mulai_mencari_kerja).
 */
class MasaTungguNolTest extends TestCase
{
    private function masaTunggu(array $byRole, bool $adaPemetaan = true): ?int
    {
        $class   = new ReflectionClass(AlumniFactBuilderService::class);
        $service = $class->newInstanceWithoutConstructor();
        $method  = $class->getMethod('masaTungguBekerja');

        return $method->invoke($service, $byRole, $adaPemetaan);
    }

    public function test_nol_sah_bila_mencari_sebelum_lulus_atau_tidak_mencari(): void
    {
        $this->assertSame(0, $this->masaTunggu(['masa_tunggu_bekerja' => '0', 'mulai_mencari_kerja' => 'Kira-kira __ bulan sebelum lulus']));
        $this->assertSame(0, $this->masaTunggu(['masa_tunggu_bekerja' => '0', 'mulai_mencari_kerja' => 'Saya tidak mencari kerja']));
    }

    public function test_nol_dibaca_tidak_diisi_bila_mencari_sesudah_lulus_atau_f301_kosong(): void
    {
        $this->assertNull($this->masaTunggu(['masa_tunggu_bekerja' => '0', 'mulai_mencari_kerja' => 'Kira-kira __ bulan sesudah lulus']));
        $this->assertNull($this->masaTunggu(['masa_tunggu_bekerja' => '0']));
    }

    public function test_bukan_nol_dan_tanpa_pemetaan_f301_dibiarkan(): void
    {
        $this->assertSame(4, $this->masaTunggu(['masa_tunggu_bekerja' => '4']));
        $this->assertSame(0, $this->masaTunggu(['masa_tunggu_bekerja' => '0'], adaPemetaan: false));
        $this->assertNull($this->masaTunggu([]));
    }
}
