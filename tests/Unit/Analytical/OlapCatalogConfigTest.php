<?php

namespace Tests\Unit\Analytical;

use Tests\TestCase;

/**
 * Menjaga config/olap_catalog.php tetap konsisten dengan dirinya sendiri.
 *
 * Katalog itu tabel data yang panjang dan gampang salah ketik, dan salah ketik
 * di sana tidak memunculkan galat sampai ada pengguna yang kebetulan memilih
 * baris itu. Tes ini yang memunculkannya lebih awal.
 *
 * Disesuaikan dengan skema katalog Explorer yang dipakai ExplorerService
 * (cubes[].measures, cubes[].dimension_groups, cubes[].base_measure). Versi
 * awalnya mengunci skema OlapCatalog dan nama cube *Terkini; maksudnya tetap
 * dijaga di sini: tidak salah ketik, tidak ada PII, tidak ada DimWaktu, dan
 * setiap cube punya ukuran dasar untuk mengunci snapshot.
 */
class OlapCatalogConfigTest extends TestCase
{
    /** @return array<string, list<string>> nama cube => kunci dimensinya */
    private function dimensionsByCube(): array
    {
        $out = [];

        foreach (config('olap_catalog.cubes') as $cubeName => $cube) {
            $out[$cubeName] = [];

            foreach ($cube['dimension_groups'] as $members) {
                foreach (array_keys($members) as $key) {
                    $out[$cubeName][] = $key;
                }
            }
        }

        return $out;
    }

    public function test_setiap_measure_memakai_prefix_cube_pemiliknya(): void
    {
        foreach (config('olap_catalog.cubes') as $cubeName => $cube) {
            foreach (array_keys($cube['measures']) as $measure) {
                $this->assertStringStartsWith(
                    $cubeName . '.',
                    $measure,
                    "Measure {$measure} terdaftar di cube {$cubeName} tapi prefiksnya beda.",
                );
            }
        }
    }

    public function test_setiap_dimensi_berbentuk_dim_cube_titik_kolom_dan_tidak_dobel(): void
    {
        foreach ($this->dimensionsByCube() as $cubeName => $keys) {
            $this->assertSame(
                $keys,
                array_values(array_unique($keys)),
                "Cube {$cubeName} punya dimensi yang terdaftar dua kali.",
            );

            foreach ($keys as $key) {
                $this->assertMatchesRegularExpression(
                    '/^Dim[A-Za-z]+\.[a-z0-9_]+$/',
                    $key,
                    "Dimensi {$key} di cube {$cubeName} salah bentuk.",
                );
            }
        }
    }

    /**
     * Aturan satu sumbu tahun, dijaga di tingkat config supaya tidak hilang
     * kalau suatu saat ada yang menambahkan DimWaktu "supaya lengkap".
     */
    public function test_dim_waktu_tidak_pernah_masuk_katalog(): void
    {
        foreach ($this->dimensionsByCube() as $cubeName => $keys) {
            foreach ($keys as $key) {
                $this->assertStringStartsNotWith('DimWaktu.', $key, "Cube {$cubeName} membuka {$key}.");
            }
        }
    }

    /**
     * Cacah dihitung terhadap SATU snapshot (ExplorerService::withSnapshot),
     * dan snapshot itu dicari lewat ukuran dasar cube. Cube tanpa ukuran dasar
     * yang valid akan menghitung lintas snapshot: tanpa galat, hanya angka
     * yang menggelembung.
     */
    public function test_setiap_cube_punya_ukuran_dasar_yang_terdaftar(): void
    {
        foreach (config('olap_catalog.cubes') as $cubeName => $cube) {
            $this->assertNotEmpty($cube['base_measure'] ?? null, "Cube {$cubeName} tanpa base_measure.");
            $this->assertArrayHasKey(
                $cube['base_measure'],
                $cube['measures'],
                "base_measure cube {$cubeName} tidak ada di daftar ukurannya.",
            );
        }
    }

    /**
     * Surrogate key, penanda SCD Type 2, dan identitas alumni tidak boleh
     * bocor ke daftar pilihan pengguna.
     */
    public function test_kolom_teknis_dan_pii_tidak_diekspos(): void
    {
        foreach ($this->dimensionsByCube() as $cubeName => $keys) {
            foreach ($keys as $key) {
                $field = substr($key, strpos($key, '.') + 1);

                $this->assertFalse(
                    str_ends_with($field, '_sk')
                    || str_starts_with($field, 'flag_')
                    || in_array($field, ['valid_from', 'valid_to', 'nim', 'nama'], true),
                    "Dimensi {$key} di cube {$cubeName} tidak seharusnya bisa dipilih pengguna.",
                );
            }
        }
    }
}
