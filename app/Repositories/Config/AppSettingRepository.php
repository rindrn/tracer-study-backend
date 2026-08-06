<?php
// app/Repositories/Config/AppSettingRepository.php
namespace App\Repositories\Config;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pengaturan aplikasi key-value (tabel app_settings).
 *
 * Nilai selalu dikembalikan sebagai string apa adanya -- pemaknaan tipe
 * (integer, boolean, JSON) adalah urusan service pemakainya, supaya repository
 * tidak perlu tahu daftar key yang ada.
 */
class AppSettingRepository
{
    private const CONN = 'oltp';
    private const TABLE = 'app_settings';

    public function get(string $key): ?string
    {
        $value = DB::connection(self::CONN)->table(self::TABLE)
            ->where('key', $key)
            ->value('value');

        return $value !== null ? (string) $value : null;
    }

    /**
     * @param string[] $keys
     * @return array<string,string> key => value, hanya yang ada di tabel
     */
    public function getMany(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        return DB::connection(self::CONN)->table(self::TABLE)
            ->whereIn('key', $keys)
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Simpan nilai, buat barisnya kalau key belum ada -- jadi pengaturan baru
     * tidak perlu di-seed lebih dulu, halaman admin bisa langsung menulisnya.
     *
     * Tidak memakai updateOrInsert() karena kolom yang dioper ke sana dipakai
     * untuk update MAUPUN insert, sehingga created_at ikut tertimpa tiap kali
     * nilainya diubah.
     */
    public function set(string $key, ?string $value): void
    {
        $now = Carbon::now();
        $table = DB::connection(self::CONN)->table(self::TABLE);

        if ($table->clone()->where('key', $key)->exists()) {
            $table->where('key', $key)->update(['value' => $value, 'updated_at' => $now]);

            return;
        }

        $table->insert([
            'key'        => $key,
            'value'      => $value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string,string|null> $values */
    public function setMany(array $values): void
    {
        DB::connection(self::CONN)->transaction(function () use ($values) {
            foreach ($values as $key => $value) {
                $this->set($key, $value);
            }
        });
    }
}
