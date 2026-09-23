<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessException;
use App\Models\Transactional\User;
use App\Repositories\Analytical\ExplorerRepository;
use App\Services\Analytical\ExplorerService;
use App\Services\Transactional\InsightBoardService;
use App\Services\Transactional\InsightQuestionService;
use Mockery;
use Tests\Support\UsesInsightTestSchema;
use Tests\TestCase;

/**
 * "Dashboard Saya": menyematkan, mengurutkan, mengubah ukuran, dan melepas
 * pertanyaan — serta batas pertanyaan orang lain yang boleh disematkan.
 */
class InsightBoardServiceTest extends TestCase
{
    use UsesInsightTestSchema;

    private User $ani;
    private User $budi;
    private InsightQuestionService $questions;
    private InsightBoardService $board;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInsightSchema();

        $this->ani  = $this->makeUser('Ani');
        $this->budi = $this->makeUser('Budi');

        $this->questions = new InsightQuestionService(
            new ExplorerService(Mockery::mock(ExplorerRepository::class)),
        );
        $this->board = new InsightBoardService($this->questions);
    }

    protected function tearDown(): void
    {
        $this->tearDownInsightSchema();
        parent::tearDown();
    }

    private function question(User $owner, string $title, bool $shared = false): int
    {
        return $this->questions->create($owner, [
            'title'     => $title,
            'is_shared' => $shared,
            'query'     => [
                'cube'     => 'FactTracerStudy',
                'measures' => ['FactTracerStudy.count_alumni'],
                'rowDims'  => ['DimProdi.jurusan'],
                'colDim'   => null,
                'filters'  => [],
            ],
        ])['id'];
    }

    private function titles(User $user): array
    {
        return array_map(fn ($i) => $i['question']['title'], $this->board->list($user));
    }

    // ── Menyematkan ─────────────────────────────────────────────────────

    public function test_menyematkan_menambah_kartu_di_urutan_terakhir(): void
    {
        $this->board->pin($this->ani, $this->question($this->ani, 'Satu'));
        $this->board->pin($this->ani, $this->question($this->ani, 'Dua'), 'lg');

        $items = $this->board->list($this->ani);

        $this->assertSame(['Satu', 'Dua'], $this->titles($this->ani));
        $this->assertSame('lg', $items[1]['size']);
        $this->assertSame([], $this->board->list($this->budi));
    }

    public function test_menyematkan_dua_kali_tidak_membuat_kartu_kembar(): void
    {
        $q = $this->question($this->ani, 'Satu');

        $first  = $this->board->pin($this->ani, $q);
        $second = $this->board->pin($this->ani, $q);

        $this->assertSame($first['id'], $second['id']);
        $this->assertCount(1, $this->board->list($this->ani));
    }

    public function test_boleh_menyematkan_pertanyaan_yang_dibagikan_orang_lain(): void
    {
        $this->board->pin($this->budi, $this->question($this->ani, 'Milik Ani', shared: true));

        $item = $this->board->list($this->budi)[0];

        $this->assertSame('Milik Ani', $item['question']['title']);
        $this->assertFalse($item['question']['is_mine']);
    }

    public function test_tidak_boleh_menyematkan_pertanyaan_pribadi_orang_lain(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionCode(404);

        $this->board->pin($this->budi, $this->question($this->ani, 'Rahasia Ani'));
    }

    public function test_kartu_hilang_saat_pemilik_berhenti_membagikan_dan_kembali_saat_dibagikan_lagi(): void
    {
        $q = $this->question($this->ani, 'Milik Ani', shared: true);
        $this->board->pin($this->budi, $q);

        $this->questions->update($this->ani, $q, ['is_shared' => false]);
        $this->assertSame([], $this->board->list($this->budi));

        $this->questions->update($this->ani, $q, ['is_shared' => true]);
        $this->assertSame(['Milik Ani'], $this->titles($this->budi));
    }

    public function test_menghapus_pertanyaan_ikut_melepas_sematannya(): void
    {
        $q = $this->question($this->ani, 'Satu', shared: true);
        $this->board->pin($this->ani, $q);
        $this->board->pin($this->budi, $q);

        $this->questions->delete($this->ani, $q);

        $this->assertSame([], $this->board->list($this->ani));
        $this->assertSame([], $this->board->list($this->budi));
    }

    // ── Urutan, ukuran, lepas ───────────────────────────────────────────

    public function test_urutan_baru_tersimpan(): void
    {
        $a = $this->board->pin($this->ani, $this->question($this->ani, 'A'))['id'];
        $b = $this->board->pin($this->ani, $this->question($this->ani, 'B'))['id'];
        $c = $this->board->pin($this->ani, $this->question($this->ani, 'C'))['id'];

        $this->board->reorder($this->ani, [$c, $a, $b]);

        $this->assertSame(['C', 'A', 'B'], $this->titles($this->ani));
    }

    public function test_urutan_harus_memuat_seluruh_kartu_milik_sendiri(): void
    {
        $a = $this->board->pin($this->ani, $this->question($this->ani, 'A'))['id'];
        $b = $this->board->pin($this->ani, $this->question($this->ani, 'B'))['id'];
        $milikBudi = $this->board->pin($this->budi, $this->question($this->budi, 'X'))['id'];

        foreach ([[$a], [$a, $b, $milikBudi], [$a, $a]] as $salah) {
            try {
                $this->board->reorder($this->ani, $salah);
                $this->fail('Seharusnya ditolak: ' . json_encode($salah));
            } catch (BusinessException $e) {
                $this->assertSame(422, $e->getCode());
            }
        }

        $this->assertSame(['A', 'B'], $this->titles($this->ani));
    }

    public function test_ukuran_kartu_bisa_diubah_hanya_ke_nilai_yang_sah(): void
    {
        $id = $this->board->pin($this->ani, $this->question($this->ani, 'A'))['id'];

        $this->assertSame('lg', $this->board->resize($this->ani, $id, 'lg')['size']);

        $this->expectException(BusinessException::class);
        $this->board->resize($this->ani, $id, 'xl');
    }

    public function test_tidak_bisa_melepas_kartu_orang_lain(): void
    {
        $id = $this->board->pin($this->ani, $this->question($this->ani, 'A'))['id'];

        try {
            $this->board->unpin($this->budi, $id);
            $this->fail('Seharusnya ditolak');
        } catch (BusinessException $e) {
            $this->assertSame(404, $e->getCode());
        }

        $this->board->unpin($this->ani, $id);
        $this->assertSame([], $this->board->list($this->ani));
    }
}
