<?php

namespace Tests\Unit\Services;

use App\Repositories\ETL\OlapLoadRepository;
use App\Repositories\ETL\OltpExtractRepository;
use App\Repositories\ETL\SemanticMappingRepository;
use App\Services\ETL\AlumniFactBuilderService;
use App\Services\ETL\AnomalyLoggerService;
use App\Services\ETL\AnswerResolverService;
use App\Services\ETL\KesesuaianBidangDimService;
use App\Services\ETL\KesesuaianLevelDimService;
use App\Services\ETL\MultiSelectFactBuilderService;
use App\Services\ETL\PerusahaanDimService;
use App\Services\ETL\RangeEvaluasiFactBuilderService;
use App\Services\ETL\StatusAlumniDimService;
use App\Services\ETL\WirausahaDimService;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Data impor kementerian mengisi pertanyaan bersyarat tanpa memedulikan
 * show_if-nya: f5c jabatan wirausaha pada alumni Bekerja, studi lanjut pada
 * Wiraswasta, "0" untuk sel kosong. Jawaban seperti itu tidak boleh sampai
 * ke dimensi.
 */
class JawabanBerlakuTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private const PERTANYAAN = [
        ['f8', 'single_choice', null],
        ['f5a1', 'short_text', ['f8' => [1, 3]]],
        ['f5b', 'short_text', ['f8' => [1]]],
        ['f5c', 'single_choice', ['f8' => [3]]],
        ['f14', 'single_choice', ['f8' => [1]]],
        ['f18b', 'short_text', ['f8' => [4]]],
        ['f502', 'number', ['f8' => [1, 3]]],
        ['f303', 'number', ['f301' => [2]]],
    ];

    private const ROLE = [
        'f8' => 'status_pekerjaan', 'f5a1' => 'provinsi_kerja', 'f5b' => 'nama_perusahaan',
        'f5c' => 'jabatan_wirausaha', 'f14' => 'relevansi_bidang', 'f18b' => 'perguruan_tinggi',
    ];

    /** Kode jawaban yang lolos untuk satu response. */
    private function lolos(array $jawaban): array
    {
        $oltp = Mockery::mock(OltpExtractRepository::class);
        // f301 sengaja tidak ada: induk di luar whitelist ETL.
        $oltp->shouldReceive('getQuestionMetaForQuestionnaire')->andReturn(collect(array_map(
            fn ($q) => (object) [
                'question_code' => $q[0],
                'question_type' => $q[1],
                'metadata'      => $q[2] ? json_encode(['show_if' => $q[2]]) : null,
            ],
            self::PERTANYAAN,
        )));

        $semantic = Mockery::mock(SemanticMappingRepository::class);
        $semantic->shouldReceive('questionCodeFor')->with(1, 'status_pekerjaan')->andReturn('f8');

        $service = new AlumniFactBuilderService(
            new AnswerResolverService($oltp),
            Mockery::mock(StatusAlumniDimService::class),
            Mockery::mock(KesesuaianLevelDimService::class),
            Mockery::mock(KesesuaianBidangDimService::class),
            Mockery::mock(PerusahaanDimService::class),
            Mockery::mock(WirausahaDimService::class),
            Mockery::mock(MultiSelectFactBuilderService::class),
            Mockery::mock(RangeEvaluasiFactBuilderService::class),
            $oltp,
            Mockery::mock(OlapLoadRepository::class),
            $semantic,
            Mockery::mock(AnomalyLoggerService::class),
        );

        $byCode = collect(self::ROLE)->mapWithKeys(fn ($role, $code) => ["1:$code" => (object) ['semantic_role' => $role]]);
        $answers = collect($jawaban)->map(fn ($text, $code) => (object) ['question_code' => $code, 'answer_text' => $text])->values();

        $method = new ReflectionMethod($service, 'jawabanBerlaku');

        return $method->invoke($service, 1, $answers, collect(['by_code' => $byCode]))->pluck('question_code')->all();
    }

    public function test_jabatan_wirausaha_dibuang_dari_alumni_bekerja(): void
    {
        $this->assertSame(
            ['f8', 'f5a1', 'f5b', 'f14'],
            $this->lolos(['f8' => '1', 'f5a1' => '30', 'f5b' => 'Shouei Kougyo', 'f5c' => '2', 'f14' => '1']),
        );
    }

    public function test_wiraswasta_tetap_dihitung_di_usaha_kesesuaian_dan_perusahaan_tetapi_bukan_studi_lanjut(): void
    {
        $this->assertSame(
            ['f8', 'f5a1', 'f5b', 'f5c', 'f14'],
            $this->lolos(['f8' => '3', 'f5a1' => '30', 'f5b' => 'Usaha Sendiri', 'f5c' => '1', 'f14' => '2', 'f18b' => 'ITB']),
        );
    }

    public function test_studi_sambil_wiraswasta_setara_wiraswasta_dan_studi_lanjut(): void
    {
        $this->assertSame(['f8', 'f5c', 'f18b'], $this->lolos(['f8' => '7', 'f5c' => '1', 'f18b' => 'ITB']));
    }

    public function test_status_tanpa_pekerjaan_tidak_membawa_jawaban_kerja(): void
    {
        $this->assertSame(['f8'], $this->lolos(['f8' => '2', 'f5a1' => '30', 'f5c' => '1', 'f502' => '3']));
    }

    public function test_nol_di_isian_teks_dibuang_tetapi_angka_nol_dibiarkan(): void
    {
        $this->assertSame(['f8', 'f502'], $this->lolos(['f8' => '1', 'f5b' => '0', 'f502' => '0']));
    }

    public function test_induk_di_luar_whitelist_tidak_dinilai(): void
    {
        $this->assertSame(['f8', 'f303'], $this->lolos(['f8' => '1', 'f303' => '2']));
    }
}
