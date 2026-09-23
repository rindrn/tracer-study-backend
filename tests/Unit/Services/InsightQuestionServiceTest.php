<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessException;
use App\Models\Transactional\User;
use App\Repositories\Analytical\ExplorerRepository;
use App\Services\Analytical\ExplorerService;
use App\Services\Transactional\InsightQuestionService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\UsesInsightTestSchema;
use Tests\TestCase;

/**
 * Aturan akses pertanyaan tersimpan: siapa boleh melihat, mengubah, dan
 * menghapus. Berjalan di schema sementara — lihat UsesInsightTestSchema.
 */
class InsightQuestionServiceTest extends TestCase
{
    use UsesInsightTestSchema;

    private User $ani;
    private User $budi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInsightSchema();

        $this->ani  = $this->makeUser('Ani');
        $this->budi = $this->makeUser('Budi');
    }

    protected function tearDown(): void
    {
        $this->tearDownInsightSchema();
        parent::tearDown();
    }

    private function service(): InsightQuestionService
    {
        // Validasi katalog tidak menyentuh Cube.js, jadi repository-nya
        // cukup tiruan kosong.
        return new InsightQuestionService(
            new ExplorerService(Mockery::mock(ExplorerRepository::class)),
        );
    }

    private function data(array $override = []): array
    {
        return array_merge([
            'title' => 'Alumni per jurusan',
            'query' => [
                'cube'     => 'FactTracerStudy',
                'measures' => ['FactTracerStudy.count_alumni'],
                'rowDims'  => ['DimProdi.jurusan'],
                'colDim'   => 'DimAlumni.tahun_lulus',
                'filters'  => [['member' => 'DimProdi.jenjang', 'values' => ['D3']]],
            ],
        ], $override);
    }

    // ── Simpan ──────────────────────────────────────────────────────────

    public function test_menyimpan_susunan_pertanyaan_apa_adanya(): void
    {
        $saved = $this->service()->create($this->ani, $this->data(['is_shared' => true]));

        $this->assertSame('Alumni per jurusan', $saved['title']);
        $this->assertSame('DimAlumni.tahun_lulus', $saved['query']['colDim']);
        $this->assertSame([['member' => 'DimProdi.jenjang', 'values' => ['D3']]], $saved['query']['filters']);
        $this->assertTrue($saved['is_shared']);
        $this->assertTrue($saved['is_mine']);
        $this->assertSame('Ani', $saved['owner_name']);
    }

    public function test_susunan_di_luar_katalog_tidak_bisa_disimpan(): void
    {
        $data = $this->data();
        $data['query']['rowDims'] = ['DimAlumni.nim'];

        try {
            $this->service()->create($this->ani, $data);
            $this->fail('Seharusnya ditolak');
        } catch (BusinessException $e) {
            $this->assertSame(422, $e->getCode());
        }

        $this->assertSame(0, DB::connection('oltp')->table('insight_questions')->count());
    }

    // ── Melihat ─────────────────────────────────────────────────────────

    public function test_daftar_berisi_milik_sendiri_dan_yang_dibagikan_saja(): void
    {
        $s = $this->service();
        $s->create($this->ani, $this->data(['title' => 'Ani pribadi']));
        $s->create($this->ani, $this->data(['title' => 'Ani dibagikan', 'is_shared' => true]));
        $s->create($this->budi, $this->data(['title' => 'Budi pribadi']));

        $titles = array_column($s->list($this->budi), 'title');

        $this->assertSame(['Budi pribadi', 'Ani dibagikan'], $titles);
    }

    public function test_pertanyaan_pribadi_orang_lain_tidak_bisa_dibuka(): void
    {
        $id = $this->service()->create($this->ani, $this->data())['id'];

        $this->expectException(BusinessException::class);
        $this->expectExceptionCode(404);

        $this->service()->show($this->budi, $id);
    }

    public function test_pertanyaan_dibagikan_bisa_dibuka_tapi_bukan_milik_pembuka(): void
    {
        $id = $this->service()->create($this->ani, $this->data(['is_shared' => true]))['id'];

        $shown = $this->service()->show($this->budi, $id);

        $this->assertFalse($shown['is_mine']);
        $this->assertSame('Ani', $shown['owner_name']);
    }

    // ── Mengubah & menghapus ────────────────────────────────────────────

    public function test_pemilik_bisa_mengubah_judul_dan_berhenti_membagikan(): void
    {
        $id = $this->service()->create($this->ani, $this->data(['is_shared' => true]))['id'];

        $updated = $this->service()->update($this->ani, $id, ['title' => 'Judul baru', 'is_shared' => false]);

        $this->assertSame('Judul baru', $updated['title']);
        $this->assertFalse($updated['is_shared']);
        $this->assertSame([], $this->service()->list($this->budi));
    }

    public function test_orang_lain_tidak_bisa_mengubah_pertanyaan_yang_dibagikan(): void
    {
        $id = $this->service()->create($this->ani, $this->data(['is_shared' => true]))['id'];

        $this->expectException(BusinessException::class);
        $this->expectExceptionCode(403);

        $this->service()->update($this->budi, $id, ['title' => 'Dibajak']);
    }

    public function test_orang_lain_tidak_bisa_menghapus(): void
    {
        $id = $this->service()->create($this->ani, $this->data(['is_shared' => true]))['id'];

        try {
            $this->service()->delete($this->budi, $id);
            $this->fail('Seharusnya ditolak');
        } catch (BusinessException $e) {
            $this->assertSame(403, $e->getCode());
        }

        $this->service()->delete($this->ani, $id);
        $this->assertSame(0, DB::connection('oltp')->table('insight_questions')->count());
    }

    public function test_mengubah_susunan_tetap_divalidasi_katalog(): void
    {
        $id = $this->service()->create($this->ani, $this->data())['id'];

        $data = $this->data();
        $data['query']['measures'] = ['FactTracerStudy.id_fact'];

        $this->expectException(BusinessException::class);
        $this->service()->update($this->ani, $id, ['query' => $data['query']]);
    }
}
