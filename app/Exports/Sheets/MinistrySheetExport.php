<?php

namespace App\Exports\Sheets;

use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Exports\Support\AnswerValueResolver;
use App\Exports\Support\ColumnLetter;
use App\Repositories\Transactional\ResponseRepository;
use App\Repositories\Transactional\QuestionnaireRepository;
use App\Support\PersonalData;

/**
 * Sheet "Data Kementrian" -- berisi SEMUA alumni yang lolos filter.
 *
 * FORMAT NILAI
 * ------------
 * Ditentukan oleh flag $rawCode yang datang dari query param `format` di
 * endpoint export, BUKAN lagi dari role user:
 *
 *   - format=label (default) -> nilai jawaban ditampilkan sebagai teks
 *     ("Bekerja", "Sangat Tinggi", "Ya"), lihat AnswerValueResolver.
 *   - format=code            -> nilai ditampilkan mentah apa adanya
 *     ("1", "5", "0"), format yang dibutuhkan kalau file ini mau diunggah
 *     ke portal Kementerian.
 *
 * Sebelumnya kode mentah dipilih otomatis untuk role head_tracer. Aturan
 * itu dibuang: role tidak bisa menebak apakah file mau dibaca manusia atau
 * diunggah ke portal, jadi keputusan format sekarang eksplisit dari UI.
 *
 * Lookup label (questionnaire_options) dilakukan berdasarkan
 * (question_code, option_code) SAJA, TANPA questionnaire_id -- keputusan
 * sadar karena tahun_lulus WAJIB di endpoint export, menjamin satu batch
 * data berasal dari questionnaire yang konsisten (lihat
 * QuestionnaireRepository::getOptionsGroupedByCode() untuk detail
 * trade-off ini).
 */
class MinistrySheetExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithChunkReading, WithMapping, WithCustomValueBinder
{
    public const MINISTRY_QUESTION_CODES = [
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

    /** Kolom identitas alumni, selalu di depan dan tidak ikut aturan resolve. */
    private const IDENTITY_HEADERS = [
        'Kode PT', 'Kode Prodi', 'Nomor Mhs', 'Nama', 'Hp', 'Email',
        'Tahun Lulus', 'NIK', 'NPWP',
    ];

    /**
     * f1601-f1613 (AlasanKerjaTdkSesuai) dan f401-f415 (CaraCariKerja)
     * semua berbagi question_text dengan prefix panjang yang sama per
     * grupnya ("Jika menurut Anda pekerjaan saat ini tidak sesuai
     * dengan pendidikan, mengapa Anda mengambilnya? — ..." untuk grup
     * pertama, "Bagaimana Anda mencari pekerjaan tersebut? — ..." untuk
     * grup kedua), sehingga truncate 80-karakter biasa memotong justru
     * di bagian yang membedakan tiap field. Untuk code-code ini, header
     * diambil dari metadata.group_label (sudah berisi HANYA bagian
     * spesifiknya, tanpa prefix berulang) -- BUKAN question_text.
     *
     * f416 SENGAJA TIDAK diikutkan: berbeda dari f401-f415, field ini
     * question_type='short_text' (pertanyaan susulan "Sebutkan cara
     * lainnya", muncul jika f415="Lainnya" dipilih) -- tidak punya
     * group_code/group_label sama sekali, jadi tetap pakai question_text
     * seperti biasa.
     *
     * Field lain di MINISTRY_QUESTION_CODES yang tidak disebut di sini
     * tetap pakai question_text seperti biasa (keputusan eksplisit,
     * bukan generik untuk semua field yang punya group_label).
     */
    private const HEADER_FROM_GROUP_LABEL_CODES = [
        'f1601', 'f1602', 'f1603', 'f1604', 'f1605', 'f1606', 'f1607', 'f1608',
        'f1609', 'f1610', 'f1611', 'f1612', 'f1613',
        'f401', 'f402', 'f403', 'f404', 'f405', 'f406', 'f407', 'f408',
        'f409', 'f410', 'f411', 'f412', 'f413', 'f414', 'f415',
    ];

    /** Batas panjang teks header. Lebih dari ini dipotong -- kolom jadi
     *  terlalu tinggi kalau seluruh pertanyaan ditulis penuh. */
    private const HEADER_MAX_LENGTH = 80;

    /** @var array<string,string> code => label header final (sudah
     *  resolve group_label untuk HEADER_FROM_GROUP_LABEL_CODES) */
    private array $questionLabels;

    public function __construct(
        private readonly Builder $query,
        private readonly ResponseRepository $responseRepo,
        private readonly QuestionnaireRepository $questionnaireRepo,
        private readonly AnswerValueResolver $valueResolver,
        /** Questionnaire yang jadi sumber teks header. Wajib dibatasi:
         *  question_code yang sama ada di beberapa questionnaire dengan
         *  metadata berbeda -- lihat QuestionnaireRepository::getQuestionMetaByCode(). */
        private readonly array $sourceQuestionnaireIds = [],
    ) {
        $this->questionLabels = $this->buildQuestionLabels();
    }

    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * Resolve label header final per question_code:
     *   - Untuk code di HEADER_FROM_GROUP_LABEL_CODES: ambil
     *     metadata.group_label (bagian spesifik saja, tanpa prefix
     *     berulang). Fallback ke question_text jika metadata tidak ada
     *     atau group_label tidak ditemukan di JSON-nya (data tidak
     *     konsisten), supaya header tidak pernah kosong.
     *   - Code lain: question_text apa adanya, seperti sebelumnya.
     */
    private function buildQuestionLabels(): array
    {
        $metaByCode = $this->questionnaireRepo->getQuestionMetaByCode(
            self::MINISTRY_QUESTION_CODES,
            $this->sourceQuestionnaireIds,
        );

        $labels = [];
        foreach ($metaByCode as $code => $row) {
            if (in_array($code, self::HEADER_FROM_GROUP_LABEL_CODES, strict: true)) {
                $metadata = $row->metadata !== null ? json_decode($row->metadata, true) : null;
                $labels[$code] = $metadata['group_label'] ?? $row->question_text;
            } else {
                $labels[$code] = $row->question_text;
            }
        }

        return $labels;
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
            $alumni->nim ?: '-',
            $alumni->name,
            $alumni->phone ?: '-',
            $alumni->email ?: '-',
            $alumni->graduation_year,
            // Didekripsi di sini, bukan di repository. Lembar ini memakai
            // FromQuery + WithChunkReading: maatwebsite/excel yang menjalankan
            // query-nya sendiri secara bertahap, sehingga
            // AlumniProfileRepository::getForReportQuery() tidak pernah
            // memegang barisnya dan tidak punya kesempatan mendekripsi.
            // Tanpa dua panggilan ini, kolom NIK dan NPWP di berkas yang
            // diunggah ke portal Kementerian berisi ciphertext.
            PersonalData::reveal($alumni->nik) ?: '-',
            PersonalData::reveal($alumni->npwp) ?: '-',
        ];

        foreach (self::MINISTRY_QUESTION_CODES as $code) {
            $row[] = $this->valueResolver->resolve($code, $answers[$code] ?? null);
        }

        return $row;
    }

    /**
     * Kolom yang isinya HARUS diperlakukan sebagai teks: Nomor Mhs, Hp,
     * NIK, NPWP. Tanpa ini value binder bawaan mengubahnya jadi angka --
     * NIM 14 digit tampil sebagai 2,019E+13, nol di depan nomor HP hilang,
     * dan NIK 16 digit bahkan kehilangan presisi karena disimpan float.
     */
    private const TEXT_COLUMNS = ['C', 'E', 'H', 'I'];

    public function bindValue(Cell $cell, $value): bool
    {
        if ($value !== null && $value !== '' && in_array($cell->getColumn(), self::TEXT_COLUMNS, strict: true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function headings(): array
    {
        $headers = self::IDENTITY_HEADERS;

        foreach (self::MINISTRY_QUESTION_CODES as $code) {
            $text = $this->questionLabels[$code] ?? $code;
            if (mb_strlen($text) > self::HEADER_MAX_LENGTH) {
                $text = mb_substr($text, 0, self::HEADER_MAX_LENGTH - 3) . '...';
            }
            $headers[] = "{$text} ({$code})";
        }

        return $headers;
    }

    /**
     * Tanpa ini semua kolom pakai lebar default (~8 karakter), sehingga
     * header pertanyaan terpotong dan sheet tidak terbaca. Kolom pertanyaan
     * sengaja dipatok 32 (bukan auto-size): auto-size akan melebarkan kolom
     * mengikuti header sepanjang 80 karakter, dan 76 kolom selebar itu
     * justru lebih tidak terbaca.
     */
    public function columnWidths(): array
    {
        $widths = [
            'A' => 10, 'B' => 12, 'C' => 18, 'D' => 30, 'E' => 16,
            'F' => 28, 'G' => 12, 'H' => 20, 'I' => 20,
        ];

        $firstQuestionColumn = count(self::IDENTITY_HEADERS);
        foreach (array_keys(self::MINISTRY_QUESTION_CODES) as $i) {
            $widths[ColumnLetter::at($firstQuestionColumn + $i)] = 32;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        $lastColumn = ColumnLetter::at(count($this->headings()) - 1);

        $header = $sheet->getStyle("A1:{$lastColumn}1");
        $header->getFont()->setBold(true);
        $header->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);

        $sheet->getRowDimension(1)->setRowHeight(75);

        // Bekukan header DAN 4 kolom identitas (Kode PT s/d Nama), supaya
        // saat digulir ke kanan masih terlihat baris ini milik siapa.
        $sheet->freezePane('E2');

        $sheet->setAutoFilter("A1:{$lastColumn}1");

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
