<?php
// app/Services/Transactional/PublicReportService.php
namespace App\Services\Transactional;

use App\Repositories\Transactional\PublicReportRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Laporan Tracer Study tahunan untuk publik.
 *
 * Berkas disimpan di disk PRIVAT (storage/app/private/public-reports) dan
 * hanya bisa diambil lewat route unduhan yang mengecek is_published lebih
 * dulu. Kalau ditaruh di disk publik + storage:link, URL yang sudah tersebar
 * tetap bisa diunduh walau publikasinya dicabut.
 *
 * Nama berkas di disk di-hash; nama asli disimpan terpisah di kolom file_name
 * dan dipakai saat Content-Disposition, sehingga dua unggahan bernama sama
 * tidak saling menimpa tapi pengguna tetap menerima nama yang wajar.
 */
class PublicReportService
{
    /** Disk privat -- lihat config/filesystems.php. */
    public const DISK = 'local';

    private const DIRECTORY = 'public-reports';

    public function __construct(
        private readonly PublicReportRepository $repo,
    ) {}

    public function listForAdmin(): array
    {
        return $this->repo->listAll()->map(fn ($row) => $this->toAdminArray($row))->all();
    }

    /** @param array{start?: int, end?: int} $yearRange */
    public function listForPublic(array $yearRange = []): array
    {
        return $this->repo->listPublished($yearRange)
            ->map(fn ($row) => $this->toPublicArray($row))
            ->all();
    }

    public function create(UploadedFile $file, array $attributes, ?int $uploadedBy): array
    {
        $path = $file->store(self::DIRECTORY, self::DISK);

        if ($path === false) {
            throw new RuntimeException('Berkas gagal disimpan.');
        }

        $isPublished = (bool) ($attributes['is_published'] ?? false);

        $id = $this->repo->insert([
            'title'          => $attributes['title'],
            'description'    => $attributes['description'] ?? null,
            'report_year'    => (int) $attributes['report_year'],
            'file_path'      => $path,
            'file_name'      => $this->safeFileName($file),
            'file_size'      => $file->getSize(),
            'mime_type'      => $file->getClientMimeType(),
            'is_published'   => $isPublished,
            'published_at'   => $isPublished ? now() : null,
            'uploaded_by'    => $uploadedBy,
        ]);

        return $this->toAdminArray($this->repo->findById($id));
    }

    /**
     * Ubah metadata. Berkasnya sendiri tidak bisa diganti di sini -- untuk
     * mengganti PDF, hapus lalu unggah ulang, supaya tidak ada laporan yang
     * isinya berubah diam-diam setelah dipublikasikan.
     */
    public function update(int $id, array $attributes): array
    {
        $report = $this->repo->findById($id);

        if ($report === null) {
            throw new RuntimeException('Laporan tidak ditemukan.');
        }

        $data = array_filter([
            'title'       => $attributes['title'] ?? null,
            'description' => $attributes['description'] ?? null,
            'report_year' => isset($attributes['report_year']) ? (int) $attributes['report_year'] : null,
        ], fn ($v) => $v !== null);

        if (array_key_exists('is_published', $attributes)) {
            $isPublished = (bool) $attributes['is_published'];
            $data['is_published'] = $isPublished;

            // published_at dicatat saat PERTAMA kali terbit dan tidak direset
            // saat dicabut, supaya tanggal terbit aslinya tidak hilang kalau
            // laporan sempat disembunyikan lalu ditampilkan lagi.
            if ($isPublished && $report->published_at === null) {
                $data['published_at'] = now();
            }
        }

        if ($data !== []) {
            $this->repo->updateById($id, $data);
        }

        return $this->toAdminArray($this->repo->findById($id));
    }

    public function delete(int $id): void
    {
        $report = $this->repo->findById($id);

        if ($report === null) {
            throw new RuntimeException('Laporan tidak ditemukan.');
        }

        // Baris database dihapus lebih dulu. Kalau urutannya dibalik dan
        // penghapusan baris gagal, yang tersisa adalah laporan yang terdaftar
        // tapi berkasnya sudah hilang -- unduhannya jadi 404 tanpa penjelasan.
        $this->repo->deleteById($id);

        Storage::disk(self::DISK)->delete($report->file_path);
    }

    /**
     * Siapkan berkas laporan untuk dikirim ke publik. Mengembalikan null kalau
     * laporan tidak ada, belum terbit, atau berkasnya hilang dari disk.
     *
     * @param bool $countAsDownload false untuk pratinjau inline -- membuka
     *        halaman daftar tidak boleh dihitung sebagai unduhan, kalau tidak
     *        angkanya membengkak tanpa ada yang benar-benar mengunduh.
     * @return array{path: string, name: string, mime: string}|null
     */
    public function prepareDownload(int $id, bool $countAsDownload = true): ?array
    {
        $report = $this->repo->findPublishedById($id);

        if ($report === null || !Storage::disk(self::DISK)->exists($report->file_path)) {
            return null;
        }

        if ($countAsDownload) {
            $this->repo->incrementDownloadCount($id);
        }

        return [
            'path' => Storage::disk(self::DISK)->path($report->file_path),
            'name' => $report->file_name,
            'mime' => $report->mime_type,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // Private helpers
    // ═══════════════════════════════════════════════════════════

    /**
     * Nama asli dari klien tidak pernah dipercaya apa adanya: dipakai di header
     * Content-Disposition, jadi karakter aneh (kutip, newline, path traversal)
     * dibersihkan lebih dulu.
     */
    private function safeFileName(UploadedFile $file): string
    {
        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension() ?: 'pdf';

        $clean = Str::limit(Str::slug($original, '_'), 100, '');

        return ($clean !== '' ? $clean : 'laporan') . '.' . $extension;
    }

    private function toAdminArray(object $row): array
    {
        return [
            'id'               => (int) $row->id,
            'title'            => $row->title,
            'description'      => $row->description,
            'report_year'      => (int) $row->report_year,
            'file_name'        => $row->file_name,
            'file_size'        => (int) $row->file_size,
            'mime_type'        => $row->mime_type,
            'is_published'     => (bool) $row->is_published,
            'published_at'     => $row->published_at,
            'download_count'   => (int) $row->download_count,
            'uploaded_by_name' => $row->uploaded_by_name ?? null,
            'created_at'       => $row->created_at,
        ];
    }

    private function toPublicArray(object $row): array
    {
        return [
            'id'             => (int) $row->id,
            'title'          => $row->title,
            'description'    => $row->description,
            'report_year'    => (int) $row->report_year,
            'file_name'      => $row->file_name,
            'file_size'      => (int) $row->file_size,
            'published_at'   => $row->published_at,
            'download_count' => (int) $row->download_count,
        ];
    }
}
