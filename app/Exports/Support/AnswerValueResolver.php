<?php

namespace App\Exports\Support;

use Illuminate\Support\Collection;

/**
 * Mengubah nilai mentah response_answers.answer_text menjadi teks yang
 * bisa dibaca manusia untuk keperluan export Excel.
 *
 * Dipakai bersama oleh MinistrySheetExport dan ProdiSheetExport supaya
 * aturan resolve-nya SATU tempat -- sebelumnya kedua sheet punya logika
 * sendiri-sendiri yang berbeda hasilnya untuk data yang sama.
 *
 * Kenapa tidak cukup lookup ke questionnaire_options: dari 76 pertanyaan
 * kuesioner DIKTI, hanya 10 yang punya baris di questionnaire_options.
 * Sisanya 28 bertipe `number` (skala Likert 1-5, label-nya disimpan di
 * questionnaire_questions.metadata->scale_labels, BUKAN di tabel options)
 * dan 28 bertipe `boolean` (disimpan sebagai 0/1). Tanpa dua aturan
 * tambahan itu, mayoritas kolom export tetap tampil sebagai angka mentah.
 *
 * Urutan aturan (yang pertama cocok menang):
 *   1. provinsi/kota  -> lookup ke tabel master provinces/cities (FK numerik)
 *   2. questionnaire_options -> option_label
 *   3. question_type boolean -> Ya / Tidak
 *   4. question_type number + metadata.scale_labels -> label skala
 *   5. selain itu -> nilai mentah apa adanya
 *
 * Nilai mentah SELALU jadi fallback, tidak pernah diganti '-', supaya data
 * kotor (opsi sudah dihapus, nilai di luar rentang skala) tetap terlihat
 * daripada hilang diam-diam.
 */
class AnswerValueResolver
{
    /** question_code yang berisi FK numerik ke provinces.id / cities.id,
     *  BUKAN option_code kuesioner -- sumber lookup-nya beda tabel. */
    private const PROVINCE_CODE = 'f5a1';
    private const CITY_CODE = 'f5a2';

    /**
     * @param Collection<string,Collection> $optionsByCode question_code => Collection of {option_code, option_label}
     * @param array<string,object>          $questionMetaByCode question_code => object{question_type, metadata}
     * @param array<int,string>             $provinceNamesById
     * @param array<int,string>             $cityNamesById
     */
    public function __construct(
        private readonly Collection $optionsByCode,
        private readonly array $questionMetaByCode,
        private readonly array $provinceNamesById = [],
        private readonly array $cityNamesById = [],
    ) {}

    /**
     * Resolver yang tidak melakukan apa-apa -- semua nilai dikembalikan
     * mentah. Dipakai untuk mode export "kode mentah" (format=code) yang
     * file-nya ditujukan untuk diunggah ke portal Kementerian, bukan
     * untuk dibaca manusia.
     */
    public static function raw(): self
    {
        return new self(collect(), []);
    }

    public function resolve(string $code, mixed $rawValue): string
    {
        if ($rawValue === null || $rawValue === '' || $rawValue === '-') {
            return '-';
        }

        $raw = (string) $rawValue;

        if ($code === self::PROVINCE_CODE) {
            return $this->provinceNamesById[(int) $raw] ?? $raw;
        }

        if ($code === self::CITY_CODE) {
            return $this->cityNamesById[(int) $raw] ?? $raw;
        }

        $match = $this->optionsByCode->get($code)?->firstWhere('option_code', $raw);
        if ($match !== null) {
            return $match->option_label;
        }

        $meta = $this->questionMetaByCode[$code] ?? null;
        if ($meta === null) {
            return $raw;
        }

        $type = $meta->question_type ?? null;

        if ($type === 'boolean') {
            return $this->booleanLabel($raw);
        }

        if ($type === 'number') {
            return $this->scaleLabel($raw, $this->decodeMetadata($meta));
        }

        return $raw;
    }

    /**
     * Nilai boolean di response_answers disimpan sebagai teks dan bentuknya
     * tidak seragam antar sumber data (import lama vs form builder), jadi
     * ketiga bentuk yang pernah muncul diterima. Bentuk lain dikembalikan
     * mentah daripada ditebak jadi "Tidak".
     */
    private function booleanLabel(string $raw): string
    {
        return match (strtolower($raw)) {
            '1', 'true', 't', 'ya'          => 'Ya',
            '0', 'false', 'f', 'tidak'      => 'Tidak',
            default                         => $raw,
        };
    }

    /**
     * Pertanyaan skala (mis. f1761-f1774 kompetensi, f21-f27 metode
     * pembelajaran) menyimpan label tiap poin di metadata.scale_labels,
     * terindeks dari scale_min. Pertanyaan number biasa (mis. f301 jumlah
     * lamaran) tidak punya scale_labels dan memang harus tetap angka.
     */
    private function scaleLabel(string $raw, ?array $metadata): string
    {
        $labels = $metadata['scale_labels'] ?? null;
        if (!is_array($labels) || $labels === [] || !is_numeric($raw)) {
            return $raw;
        }

        $index = (int) $raw - (int) ($metadata['scale_min'] ?? 1);

        return $labels[$index] ?? $raw;
    }

    private function decodeMetadata(object $meta): ?array
    {
        $metadata = $meta->metadata ?? null;

        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
