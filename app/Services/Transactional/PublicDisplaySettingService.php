<?php
// app/Services/Transactional/PublicDisplaySettingService.php
namespace App\Services\Transactional;

use App\Repositories\Config\AppSettingRepository;
use App\Repositories\Transactional\AlumniProfileRepository;

/**
 * Pengaturan "pengarsipan visual" halaman publik.
 *
 * Halaman publik hanya menampilkan angkatan di dalam rentang tahun lulusan
 * yang diatur Ketua Tracer. Tujuannya efisiensi: tanpa batas ini, halaman
 * statistik publik menghitung seluruh angkatan yang pernah ada tiap kali
 * dibuka, padahal angkatan lama sudah tidak relevan untuk pembaca umum.
 *
 * Rentang ini SENGAJA tidak dipakai di dashboard internal. Staf tetap perlu
 * melihat seluruh tahun, dan membatasi keduanya dari satu pengaturan membuat
 * kajur/kaprodi kehilangan data tanpa tahu sebabnya.
 */
class PublicDisplaySettingService
{
    public const KEY_START = 'public_year_start';
    public const KEY_END   = 'public_year_end';

    /** Batas wajar tahun lulusan, dipakai validasi supaya nilai tidak ngawur. */
    public const MIN_YEAR = 1990;
    public const MAX_YEAR = 2100;

    public function __construct(
        private readonly AppSettingRepository $settings,
        private readonly AlumniProfileRepository $alumniRepo,
    ) {}

    /**
     * Rentang tahun yang berlaku sekarang.
     *
     * Kalau salah satu batas belum pernah diatur, dikembalikan null untuk sisi
     * itu dan pemakainya memperlakukannya sebagai "tak terbatas" -- lebih baik
     * menampilkan terlalu banyak daripada halaman publik kosong karena
     * pengaturan belum ada.
     *
     * @return array{start: int|null, end: int|null}
     */
    public function getRange(): array
    {
        $values = $this->settings->getMany([self::KEY_START, self::KEY_END]);

        return [
            'start' => $this->toYear($values[self::KEY_START] ?? null),
            'end'   => $this->toYear($values[self::KEY_END] ?? null),
        ];
    }

    /**
     * Bentuk yang siap dioper ke repository sebagai filter -- batas yang null
     * dibuang, bukan dikirim sebagai null, supaya klausa WHERE-nya tidak ikut
     * terpasang sama sekali.
     *
     * @return array{start?: int, end?: int}
     */
    public function getRangeFilter(): array
    {
        return array_filter($this->getRange(), fn ($v) => $v !== null);
    }

    /**
     * Daftar tahun lulusan yang benar-benar punya alumni DAN masuk rentang.
     * Dipakai halaman publik untuk mengisi pilihan tahun -- tanpa ini,
     * pengunjung bisa memilih tahun yang datanya kosong sama sekali.
     *
     * @return int[] terbaru dulu
     */
    public function getAvailableYears(): array
    {
        return $this->alumniRepo->getGraduationYearsInRange($this->getRangeFilter());
    }

    /**
     * Apakah satu tahun boleh ditampilkan publik. Dipakai endpoint statistik
     * supaya pengunjung tidak bisa melewati pengarsipan hanya dengan mengetik
     * tahun lain di query string.
     */
    public function isYearVisible(int $year): bool
    {
        $range = $this->getRange();

        if ($range['start'] !== null && $year < $range['start']) {
            return false;
        }

        return !($range['end'] !== null && $year > $range['end']);
    }

    /** @return array{start: int|null, end: int|null} rentang setelah disimpan */
    public function setRange(?int $start, ?int $end): array
    {
        $this->settings->setMany([
            self::KEY_START => $start !== null ? (string) $start : null,
            self::KEY_END   => $end !== null ? (string) $end : null,
        ]);

        return $this->getRange();
    }

    /**
     * Rentang tahun lulusan yang ada di database, untuk membantu admin memilih
     * batas yang masuk akal di form pengaturan.
     *
     * @return array{min: int|null, max: int|null}
     */
    public function getDataBounds(): array
    {
        return $this->alumniRepo->getGraduationYearBounds();
    }

    private function toYear(?string $raw): ?int
    {
        if ($raw === null || $raw === '' || !is_numeric($raw)) {
            return null;
        }

        return (int) $raw;
    }
}
