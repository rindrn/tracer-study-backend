<?php

namespace Tests\Unit\Analytical;

use App\Services\Analytical\OlapCatalog;
use Tests\TestCase;

/**
 * Menjaga config/olap_catalog.php tetap konsisten dengan dirinya sendiri.
 *
 * Katalog itu tabel data yang panjang dan gampang salah ketik, dan salah ketik
 * di sana tidak memunculkan galat sampai ada pengguna yang kebetulan memilih
 * baris itu. Tes ini yang memunculkannya lebih awal.
 */
class OlapCatalogConfigTest extends TestCase
{
    public function test_setiap_cube_merujuk_dimension_cube_yang_ada(): void
    {
        $catalog = config('olap_catalog');

        foreach ($catalog['cubes'] as $cubeName => $cube) {
            foreach ($cube['dimensions'] as $dimCube) {
                $this->assertArrayHasKey(
                    $dimCube,
                    $catalog['dimensions'],
                    "Cube {$cubeName} merujuk dimension cube {$dimCube} yang tidak ada di katalog.",
                );
            }
        }
    }

    public function test_setiap_measure_memakai_prefix_cube_pemiliknya(): void
    {
        $catalog = config('olap_catalog');

        foreach ($catalog['cubes'] as $cubeName => $cube) {
            foreach (array_keys($cube['measures']) as $measure) {
                $this->assertStringStartsWith(
                    $cubeName . '.',
                    $measure,
                    "Measure {$measure} terdaftar di cube {$cubeName} tapi prefiksnya beda.",
                );
            }
        }
    }

    public function test_setiap_dimensi_memakai_prefix_dimension_cube_pemiliknya(): void
    {
        $catalog = config('olap_catalog');

        foreach ($catalog['dimensions'] as $dimCube => $def) {
            foreach (array_keys($def['members']) as $member) {
                $this->assertStringStartsWith($dimCube . '.', $member);
            }
        }
    }

    /**
     * Aturan satu sumbu tahun, dijaga di tingkat config supaya tidak hilang
     * kalau suatu saat ada yang menambahkan DimWaktu "supaya lengkap".
     */
    public function test_dim_waktu_tidak_pernah_masuk_katalog(): void
    {
        $catalog = config('olap_catalog');

        $this->assertArrayNotHasKey('DimWaktu', $catalog['dimensions']);

        foreach ($catalog['cubes'] as $cube) {
            $this->assertNotContains('DimWaktu', $cube['dimensions']);
        }
    }

    /**
     * Katalog harus menunjuk cube *Terkini yang sudah ter-dedup, bukan fact
     * mentah. Menunjuk balik ke FactTracerStudy dkk membuat setiap cacah
     * menggelembung sebanyak snapshot ETL yang memuat alumni itu — tanpa galat,
     * hanya angka yang salah. Lihat FactTracerStudyTerkini.js.
     */
    public function test_katalog_menunjuk_cube_ter_dedup(): void
    {
        foreach (array_keys(config('olap_catalog.cubes')) as $cubeName) {
            $this->assertStringEndsWith(
                'Terkini',
                $cubeName,
                "Cube {$cubeName} bukan varian ter-dedup.",
            );
        }
    }

    /**
     * Surrogate key, penanda SCD Type 2, dan identitas alumni tidak boleh
     * bocor ke daftar pilihan pengguna.
     */
    public function test_kolom_teknis_dan_pii_tidak_diekspos(): void
    {
        $keys = (new OlapCatalog())->allDimensionKeys();

        foreach ($keys as $key) {
            $field = substr($key, strpos($key, '.') + 1);

            $this->assertFalse(
                str_ends_with($field, '_sk')
                || str_starts_with($field, 'flag_')
                || in_array($field, ['valid_from', 'valid_to', 'nim', 'nama'], true),
                "Dimensi {$key} tidak seharusnya bisa dipilih pengguna.",
            );
        }
    }
}
