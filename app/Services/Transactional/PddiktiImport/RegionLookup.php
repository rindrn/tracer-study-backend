<?php
namespace App\Services\Transactional\PddiktiImport;

/**
 * Menerjemahkan isian f5a1/f5a2 berkas impor menjadi provinces.id/cities.id —
 * bentuk yang disimpan response_answers (lihat AnswerValueResolver).
 *
 * Berkas datang dalam dua bentuk, sama seperti ekspornya: kode wilayah
 * kementerian (format=code) atau nama wilayah (format=label). Keduanya
 * diterima. Kode dibandingkan tanpa nol di depan: master kementerian menulis
 * DKI Jakarta "010000", tabel provinces menyimpannya "10000".
 */
final class RegionLookup
{
    /** @var array<string,int> */
    private array $provinceByKey = [];
    /** @var array<string,array{id:int,province_code:string}> */
    private array $cityByKey = [];
    /** @var array<int,string> provinces.id => kode ternormalisasi */
    private array $provinceCodeById = [];
    /** @var array<int,string> cities.id => kode provinsinya (ternormalisasi) */
    private array $cityProvinceById = [];

    /**
     * @param iterable<object{id:int,code:string,name:string}> $provinces
     * @param iterable<object{id:int,code:string,name:string,province_code:string}> $cities
     */
    public function __construct(iterable $provinces, iterable $cities)
    {
        foreach ($provinces as $p) {
            $code = self::code($p->code);
            $this->provinceCodeById[(int) $p->id] = $code;
            $this->provinceByKey['c:' . $code] = (int) $p->id;
            $this->provinceByKey['n:' . self::provinceName($p->name)] = (int) $p->id;
        }
        foreach ($cities as $c) {
            $entry = ['id' => (int) $c->id, 'province_code' => self::code($c->province_code)];
            $this->cityProvinceById[(int) $c->id] = $entry['province_code'];
            $this->cityByKey['c:' . self::code($c->code)] = $entry;
            $this->cityByKey['n:' . self::name($c->name)] ??= $entry;
        }
    }

    public function province(string $value): ?int
    {
        return $this->provinceByKey['c:' . self::code($value)]
            ?? $this->provinceByKey['n:' . self::provinceName($value)]
            ?? null;
    }

    /** @return array{id:int,province_code:string}|null */
    public function city(string $value): ?array
    {
        return $this->cityByKey['c:' . self::code($value)]
            ?? $this->cityByKey['n:' . self::name($value)]
            ?? null;
    }

    public function provinceCode(int $provinceId): ?string
    {
        return $this->provinceCodeById[$provinceId] ?? null;
    }

    public function cityProvinceCode(int $cityId): ?string
    {
        return $this->cityProvinceById[$cityId] ?? null;
    }

    private static function code(string $value): string
    {
        $trimmed = ltrim(trim($value), '0');

        return $trimmed === '' ? '0' : $trimmed;
    }

    private static function name(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($value)));
    }

    private static function provinceName(string $value): string
    {
        return preg_replace('/^(prov\.|provinsi)\s*/', '', self::name($value));
    }
}
