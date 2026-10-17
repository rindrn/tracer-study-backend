<?php

namespace Tests\Unit\Services;

use App\Repositories\ETL\OltpExtractRepository;
use App\Services\ETL\AnswerResolverService;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Pasangan "Lainnya, tuliskan": label kategori diseragamkan jadi "Lainnya",
 * teks ketikannya dikembalikan terpisah; dan label data lama yang beda huruf
 * besar-kecil dipetakan ke label opsi resminya.
 */
class AnswerResolverCompanionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function resolver(): AnswerResolverService
    {
        $repo = Mockery::mock(OltpExtractRepository::class);
        $repo->shouldReceive('getShowIfCompanionDefinitions')->andReturn(collect([(object) [
            'questionnaire_id' => 1, 'parent_code' => 'f1101', 'companion_code' => 'f1102', 'trigger_values' => ['7'],
        ]]));
        $repo->shouldReceive('getOptionsForQuestionnaire')->andReturn(collect([
            (object) ['question_code' => 'f1101', 'option_code' => '3', 'option_label' => 'Perusahaan swasta'],
            (object) ['question_code' => 'f1101', 'option_code' => '7', 'option_label' => 'Lainnya, tuliskan'],
        ]));

        return new AnswerResolverService($repo);
    }

    private static function answers(array $byCode)
    {
        return collect(array_map(
            fn ($code, $text) => (object) ['question_code' => $code, 'answer_text' => $text],
            array_keys($byCode), $byCode,
        ));
    }

    public function test_lainnya_berisian_jadi_satu_kelompok_dan_teksnya_terpisah(): void
    {
        [$resolved, $texts] = $this->resolver()->applyCompanionSubstitutions(
            1, ['f1101' => 'Lainnya, tuliskan'], self::answers(['f1101' => '7', 'f1102' => ' Freelance ']),
        );

        $this->assertSame('Lainnya', $resolved['f1101']);
        $this->assertSame(['f1101' => 'Freelance'], $texts);
    }

    public function test_lainnya_tanpa_isian_tetap_lainnya_tanpa_teks(): void
    {
        [$resolved, $texts] = $this->resolver()->applyCompanionSubstitutions(
            1, ['f1101' => 'Lainnya, tuliskan'], self::answers(['f1101' => '7', 'f1102' => '  ']),
        );

        $this->assertSame('Lainnya', $resolved['f1101']);
        $this->assertSame([], $texts);
    }

    public function test_opsi_selain_lainnya_tidak_diubah(): void
    {
        [$resolved, $texts] = $this->resolver()->applyCompanionSubstitutions(
            1, ['f1101' => 'Perusahaan swasta'], self::answers(['f1101' => '3', 'f1102' => 'sisa isian lama']),
        );

        $this->assertSame('Perusahaan swasta', $resolved['f1101']);
        $this->assertSame([], $texts);
    }

    public function test_isian_bebas_data_lama_di_jawaban_induk_masuk_lainnya(): void
    {
        $resolver = $this->resolver();
        $apply = fn (string $raw) => $resolver->applyCompanionSubstitutions(1, ['f1101' => $raw], self::answers(['f1101' => $raw]));

        $this->assertSame([['f1101' => 'Lainnya'], ['f1101' => 'Freelance']], $apply('Freelance'));
        // Label opsi resmi (beda huruf) dan kode angka tak dikenal tidak disentuh.
        $this->assertSame([['f1101' => 'Perusahaan Swasta'], []], $apply('Perusahaan Swasta'));
        $this->assertSame([['f1101' => '0'], []], $apply('0'));
    }

    public function test_kode_lainnya_tanpa_pemicu_show_if_tetap_lainnya(): void
    {
        $repo = Mockery::mock(OltpExtractRepository::class);
        $repo->shouldReceive('getShowIfCompanionDefinitions')->andReturn(collect());
        $repo->shouldReceive('getOptionsForQuestionnaire')->andReturn(collect([
            (object) ['question_code' => 'f1101', 'option_code' => '7', 'option_label' => 'Lainnya, tuliskan'],
        ]));

        [$resolved, $texts] = (new AnswerResolverService($repo))->applyCompanionSubstitutions(
            1, ['f1101' => 'Lainnya, tuliskan'], self::answers(['f1101' => '7']),
        );

        $this->assertSame('Lainnya', $resolved['f1101']);
        $this->assertSame([], $texts);
    }

    public function test_label_data_lama_beda_huruf_dipetakan_ke_label_opsi(): void
    {
        $resolver = $this->resolver();
        $value = fn (string $raw) => $resolver->resolveValue(1, 'f1101', (object) ['answer_text' => $raw]);

        $this->assertSame('Perusahaan swasta', $value('Perusahaan Swasta'));
        $this->assertSame('Perusahaan swasta', $value('3'));
        $this->assertSame('0', $value('0')); // kode tak dikenal dibiarkan apa adanya
    }
}
