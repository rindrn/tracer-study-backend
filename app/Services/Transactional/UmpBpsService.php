<?php
// app/Services/Transactional/UmpBpsService.php

namespace App\Services\Transactional;

use App\DTOs\Transactional\UmpRowDTO;
use App\Repositories\Transactional\RefUmpRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UmpBpsService
{
    private const VAR_UMP    = '2824';
    private const DOMAIN_UMP = '3300';

    private const BPS_VERVAR_TO_PROVINCE_ID = [
        1  => 29,  // Aceh
        2  => 35,  // Sumatera Utara
        3  => 34,  // Sumatera Barat
        4  => 31,  // Riau
        5  => 1,   // Jambi
        6  => 16,  // Sumatera Selatan
        7  => 14,  // Bengkulu
        8  => 18,  // Lampung
        9  => 17,  // Kepulauan Bangka Belitung
        10 => 21,  // Kepulauan Riau
        11 => 27,  // DKI Jakarta
        12 => 30,  // Jawa Barat
        13 => 32,  // Jawa Tengah
        14 => 28,  // DI Yogyakarta
        15 => 33,  // Jawa Timur
        16 => 11,  // Banten
        17 => 26,  // Bali
        18 => 23,  // Nusa Tenggara Barat
        19 => 15,  // Nusa Tenggara Timur
        20 => 19,  // Kalimantan Barat
        21 => 24,  // Kalimantan Tengah
        22 => 25,  // Kalimantan Selatan
        23 => 22,  // Kalimantan Timur
        24 => 20,  // Kalimantan Utara
        25 => 12,  // Sulawesi Utara
        26 => 8,   // Sulawesi Tengah
        27 => 9,   // Sulawesi Selatan
        28 => 2,   // Sulawesi Tenggara
        29 => 7,   // Gorontalo
        30 => 5,   // Sulawesi Barat
        31 => 3,   // Maluku
        32 => 10,  // Maluku Utara
        33 => 4,   // Papua Barat
        35 => 13,  // Papua
    ];

    public function __construct(
        private readonly RefUmpRepository $repo,
    ) {}

    public function previewByTahun(int $tahun): Collection
    {
        $provinces = $this->repo->allProvinces()->keyBy('id');
        $apiKey    = config('services.bps.api_key');
        $bpsTahun  = $tahun - 1900;

        try {
            $response = Http::timeout(15)->get(
                "https://webapi.bps.go.id/v1/api/list/model/data/lang/ind"
                . "/domain/" . self::DOMAIN_UMP
                . "/var/"    . self::VAR_UMP
                . "/th/{$bpsTahun}"
                . "/key/{$apiKey}"
            );

            if (! $response->ok()) {
                throw new \RuntimeException("HTTP {$response->status()}");
            }

            $json = $response->json();

            if (($json['status'] ?? '') !== 'OK') {
                throw new \RuntimeException("BPS status bukan OK: " . ($json['status'] ?? 'unknown'));
            }

            $datacontent = $json['datacontent'] ?? [];

            // Re-index key integer → string (PHP auto-convert numeric JSON key jadi integer)
            $dcByString = array_combine(
                array_map('strval', array_keys($datacontent)),
                array_values($datacontent)
            );
            // dd($dcByString, array_key_first($dcByString), gettype(array_key_first($dcByString)));

            $varStr    = self::VAR_UMP;
            $tahunStr  = (string)($json['tahun'][0]['val'] ?? $bpsTahun);

            $turvarStr   = (string)($json['turvar'][0]['val']   ?? '0');
            $turtahunStr = (string)($json['turtahun'][0]['val'] ?? '0');

            $bpsValueMap = [];
            foreach (self::BPS_VERVAR_TO_PROVINCE_ID as $vervarVal => $provinceId) {
                $targetKey = (string)$vervarVal . self::VAR_UMP . $turvarStr . $tahunStr . $turtahunStr;
                foreach ($datacontent as $dcKey => $nilai) {
                    if ((string)$dcKey === $targetKey) {
                        $bpsValueMap[$vervarVal] = (int)$nilai;
                        break;
                    }
                }
            }

            // dd($bpsValueMap);

            Log::info('UmpBpsService: parsed', [
                'tahun'     => $tahun,
                'bps_tahun' => $bpsTahun,
                'ok_count'  => count($bpsValueMap),
            ]);

        } catch (\Throwable $e) {
            Log::error('UmpBpsService: Gagal fetch BPS', ['error' => $e->getMessage()]);

            return $provinces->map(fn($p) => UmpRowDTO::preview(
                tahun:        $tahun,
                idProvinsi:   $p->id,
                namaProvinsi: $p->name,
                nilaiUmp:     null,
                sumber:       'GAGAL',
                errorMsg:     $e->getMessage(),
            ));
        }

        $results = collect();
        foreach (self::BPS_VERVAR_TO_PROVINCE_ID as $vervarVal => $provinceId) {
            $province = $provinces->get($provinceId);

            if (! $province) {
                Log::warning("UmpBpsService: province_id={$provinceId} tidak ada di master.");
                continue;
            }

            $nilai = $bpsValueMap[$vervarVal] ?? null;

            $results->push(UmpRowDTO::preview(
                tahun:        $tahun,
                idProvinsi:   $provinceId,
                namaProvinsi: $province->name,
                nilaiUmp:     $nilai,
                sumber:       $nilai !== null ? 'BPS_API' : 'GAGAL',
                errorMsg:     $nilai === null ? 'Tidak ditemukan di datacontent BPS' : null,
            ));
        }

        return $results;
    }
}