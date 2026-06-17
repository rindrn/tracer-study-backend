<?php

namespace App\Exports\Sheets;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\Transactional\User;
use App\Repositories\Transactional\ResponseRepository;
use App\Repositories\Transactional\QuestionnaireRepository;

/**
 * Sheet 1 "Data Kementrian" -- berisi SEMUA alumni yang lolos filter.
 *
 * ROLE-BASED VALUE FORMAT (requirement baru):
 *   - User::isHeadTracer() true -> nilai field choice (single_choice/
 *     multiple_choice) ditampilkan MENTAH sebagai option_code
 *     (contoh: "1", "2", "3" untuk status f8), TANPA resolve label.
 *   - Role lain (p2mpp, kaprodi, admin, wadir, dst) -> nilai field
 *     choice ditampilkan sebagai LABEL hasil lookup questionnaire_options
 *     (contoh: "Bekerja", "Belum Bekerja").
 *   - Field non-choice (short_text, number, date) SELALU sama untuk
 *     semua role -- tidak ada proses resolve apapun untuk jenis ini,
 *     karena tidak punya option_code untuk di-lookup.
 *   - PENGECUALIAN: f5a1 (provinsi) dan f5a2 (kota) BUKAN field choice
 *     kuesioner (tidak ada di questionnaire_options), melainkan berisi
 *     foreign key NUMERIK ke tabel master tracer_oltp.provinces.id dan
 *     tracer_oltp.cities.id. Untuk role selain head_tracer, kedua field
 *     ini di-resolve ke provinces.name / cities.name. head_tracer tetap
 *     melihat ID mentah, konsisten dengan field choice lainnya.
 *
 * Lookup label (questionnaire_options) dilakukan berdasarkan
 * (question_code, option_code) SAJA, TANPA questionnaire_id -- keputusan
 * sadar karena tahun_lulus WAJIB di endpoint export, menjamin satu batch
 * data berasal dari questionnaire yang konsisten (lihat
 * QuestionnaireRepository::getOptionsGroupedByCode() untuk detail
 * trade-off ini).
 */
class MinistrySheetExport implements FromQuery, WithHeadings, WithTitle, WithStyles, WithChunkReading, WithMapping
{
    private const MINISTRY_QUESTION_CODES = [
        'f8', 'f502', 'f505', 'f5a1', 'f5a2', 'f1101', 'f1102', 'f5b', 'f5c', 'f5d',
        'f18a', 'f18b', 'f18c', 'f18d', 'f1201', 'f1202', 'f14', 'f15',
        'f1761', 'f1762', 'f1763', 'f1764', 'f1765', 'f1766', 'f1767', 'f1768',
        'f1769', 'f1770', 'f1771', 'f1772', 'f1773', 'f1774',
        'f21', 'f22', 'f23', 'f24', 'f25', 'f26', 'f27',
        'f301', 'f302', 'f303',
        'f401', 'f402', 'f403', 'f404', 'f405', 'f406', 'f407', 'f408',
        'f409', 'f410', 'f411', 'f412', 'f413', 'f414', 'f415', 'f416',
        'f6', 'f7', 'f7a',
        'f1001', 'f1002',
        'f1601', 'f1602', 'f1603', 'f1604', 'f1605', 'f1606', 'f1607', 'f1608',
        'f1609', 'f1610', 'f1611', 'f1612', 'f1613', 'f1614',
    ];

    /** question_code yang berisi FK numerik ke provinces.id / cities.id,
     *  BUKAN option_code kuesioner -- ditangani terpisah dari
     *  resolveDisplayValue() biasa karena sumber lookup-nya beda tabel. */
    private const PROVINCE_CODE = 'f5a1';
    private const CITY_CODE = 'f5a2';

    /** @var array<string,string> code => label pertanyaan */
    private array $questionLabels;

    /**
     * @var Collection<string, Collection> question_code => Collection
     *      of {option_code, option_label}. KOSONG jika user adalah
     *      head_tracer -- tidak perlu di-load sama sekali karena tidak
     *      akan dipakai (menghindari query yang sia-sia).
     */
    private Collection $optionsByCode;

    /** @var array<int,string> provinces.id => provinces.name. KOSONG jika head_tracer. */
    private array $provinceNamesById;

    /** @var array<int,string> cities.id => cities.name. KOSONG jika head_tracer. */
    private array $cityNamesById;

    private bool $showRawOptionCode;

    public function __construct(
        private readonly Builder $query,
        private readonly ResponseRepository $responseRepo,
        private readonly QuestionnaireRepository $questionnaireRepo,
        private readonly User $user,
    ) {
        $this->questionLabels = $this->questionnaireRepo->getQuestionLabelsByCode(self::MINISTRY_QUESTION_CODES);

        $this->showRawOptionCode = $this->user->isHeadTracer();

        // Hanya load options/provinces/cities kalau benar-benar
        // dibutuhkan (bukan head_tracer) -- hindari query sia-sia.
        if ($this->showRawOptionCode) {
            $this->optionsByCode = collect();
            $this->provinceNamesById = [];
            $this->cityNamesById = [];
        } else {
            $this->optionsByCode = $this->questionnaireRepo->getOptionsGroupedByCode(self::MINISTRY_QUESTION_CODES);
            $this->provinceNamesById = DB::connection('oltp')->table('provinces')->pluck('name', 'id')->toArray();
            $this->cityNamesById = DB::connection('oltp')->table('cities')->pluck('name', 'id')->toArray();
        }
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function map($alumni): array
    {
        $answers = $alumni->response_id
            ? $this->responseRepo->getAnswersByResponseIds([$alumni->response_id])
                ->pluck('answer_text', 'question_code')
                ->toArray()
            : [];

        $row = [
            $alumni->kode_pt ?? '-',
            $alumni->program_code ?? '-',
            $alumni->nim,
            $alumni->name,
            $alumni->phone,
            $alumni->email,
            $alumni->graduation_year,
            $alumni->nik,
            $alumni->npwp,
        ];

        foreach (self::MINISTRY_QUESTION_CODES as $code) {
            $row[] = $this->resolveDisplayValue($code, $answers[$code] ?? null);
        }

        return $row;
    }

    /**
     * Resolve nilai yang ditampilkan di sel, sesuai role:
     *   - head_tracer: kembalikan apa adanya (ID/option_code mentah,
     *     atau nilai non-choice yang memang sudah mentah dari awal).
     *   - role lain, untuk f5a1/f5a2: lookup ke provinces/cities by id.
     *   - role lain, untuk field choice lain: lookup ke questionnaire_options.
     *   - role lain, untuk field non-choice: kembalikan apa adanya,
     *     tidak ada resolve yang dipaksakan.
     */
    private function resolveDisplayValue(string $code, ?string $rawValue): string
    {
        if ($rawValue === null || $rawValue === '') {
            return '-';
        }

        if ($this->showRawOptionCode) {
            return $rawValue;
        }

        if ($code === self::PROVINCE_CODE) {
            return $this->provinceNamesById[(int) $rawValue] ?? $rawValue;
        }

        if ($code === self::CITY_CODE) {
            return $this->cityNamesById[(int) $rawValue] ?? $rawValue;
        }

        $optionsForCode = $this->optionsByCode->get($code);
        if ($optionsForCode === null) {
            // Tidak ada opsi terdaftar untuk question_code ini -> field
            // non-choice, kembalikan nilai mentah tanpa resolve.
            return $rawValue;
        }

        $match = $optionsForCode->firstWhere('option_code', $rawValue);

        // Jika field choice tapi option_code tidak match apapun (data
        // kotor / opsi sudah dihapus), fallback ke nilai mentah daripada
        // menyembunyikan datanya sebagai '-'.
        return $match?->option_label ?? $rawValue;
    }

    public function headings(): array
    {
        $headers = [
            'Kode PT', 'Kode Prodi', 'Nomor Mhs', 'Nama', 'Hp', 'Email',
            'Tahun Lulus', 'NIK', 'NPWP',
        ];

        foreach (self::MINISTRY_QUESTION_CODES as $code) {
            $text = $this->questionLabels[$code] ?? $code;
            if (mb_strlen($text) > 80) {
                $text = mb_substr($text, 0, 77) . '...';
            }
            $headers[] = "{$text} ({$code})";
        }

        return $headers;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        return [];
    }

    public function title(): string
    {
        return 'Data Kementrian';
    }
}

/*
 * ARAH OPTIMASI LANJUTAN untuk dataset SANGAT besar: lihat catatan
 * ShouldQueue di riwayat diskusi sebelumnya -- tidak berubah dari
 * analisis itu, hanya disingkat di sini agar file tidak terlalu panjang.
 * Intinya: jika trade-off "1 query response_answers per baris" masih
 * terasa lambat di skala produksi nyata, opsi resmi yang didukung
 * maatwebsite/excel adalah ShouldQueue (chunk dieksekusi sebagai job
 * terpisah, request HTTP selesai cepat).
 */