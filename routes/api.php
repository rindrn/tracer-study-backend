<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;

use App\Http\Controllers\Api\Transactional\ThresholdIndicatorController;
use App\Http\Controllers\Api\Auth\AlumniAuthController;
use App\Http\Controllers\Api\Transactional\ThresholdController;
use App\Http\Controllers\Api\Transactional\LamController;
use App\Http\Controllers\Api\Transactional\LamVersionController;
use App\Http\Controllers\Api\Transactional\JurusanController;
use App\Http\Controllers\Api\Transactional\LamProgramController;
use App\Http\Controllers\Api\Transactional\ProgramController;
use App\Http\Controllers\Api\Transactional\RefUmpController;
use App\Http\Controllers\Api\Transactional\SemanticRoleController;
use App\Http\Controllers\Api\Transactional\QuestionSemanticMappingController;
use App\Http\Controllers\Api\Transactional\EtlRunController;
use App\Http\Controllers\Api\Analytical\KpiCategoryMappingController;
use App\Http\Controllers\Api\Analytical\EtlAnomalyLogController;
// Alias: dua controller bernama PublicReportController (satu sisi admin, satu
// sisi publik). Yang publik diberi alias supaya keduanya bisa dipakai di file
// ini tanpa menulis FQCN panjang di setiap baris route.
use App\Http\Controllers\Api\PublicAccess\PublicReportController as PublicReportViewController;
use App\Http\Controllers\Api\PublicAccess\PublicStatisticsController;
use App\Http\Controllers\Api\Admin\PublicReportController;
use App\Http\Controllers\Api\Admin\PublicDisplaySettingController;

// use App\Http\Controllers\Api\Transactional\TracerOfficerController;
use App\Http\Controllers\Api\Transactional\QuestionnaireController;
use App\Http\Controllers\Api\Transactional\ApprovalController;
use App\Http\Controllers\Api\Transactional\TracerStudySubmitController;
use App\Http\Controllers\Api\Transactional\TracerStudyDraftController;
use App\Http\Controllers\Api\Transactional\QuestionnaireFetchController;
use App\Http\Controllers\Api\Transactional\RoleController;
use App\Http\Controllers\Api\Transactional\UserController;

use App\Http\Controllers\Api\Analytical\Kpi13Controller;
// Controllers — Dashboard (OLAP page config)
use App\Http\Controllers\Api\Analytical\KeterserapanController;
use App\Http\Controllers\Api\Analytical\PendapatanController;
use App\Http\Controllers\Api\Analytical\FilterMetaController;
use App\Http\Controllers\Api\Analytical\MasaTungguController;
use App\Http\Controllers\Api\Analytical\KesesuaianController;
use App\Http\Controllers\Api\Analytical\WirausahaController;
use App\Http\Controllers\Api\Analytical\SebaranInstansiController;
use App\Http\Controllers\Api\Analytical\KompetensiGapController;
use App\Http\Controllers\Api\Analytical\MetodePembelajaranController;
use App\Http\Controllers\Api\Analytical\PembiayaanController;
use App\Http\Controllers\Api\Analytical\ResponseRateController;
use App\Http\Controllers\Api\Analytical\SummaryController;
use App\Http\Controllers\Api\Analytical\EducationSummaryController;
use App\Http\Controllers\Api\Analytical\EmploymentSummaryController;


// Controllers — DataPipeline (ETL)
// use App\Http\Controllers\Api\DataPipeline\ExcelImportController;

// ═══════════════════════════════════════════════════════════
// PUBLIC
// ═══════════════════════════════════════════════════════════
// Kedua rute masuk dibatasi lajunya, lihat RateLimiter 'login' di
// AppServiceProvider::boot(). Tanpa itu seluruh akun bisa disapu skrip dalam
// hitungan menit: NIM berpola TAHUN+urutan sehingga mudah dienumerasi, dan
// bagi alumni NIM sekaligus menjadi kata sandinya.
//
// Rute GET auth/demo-accounts DIHAPUS. Isinya seluruh akun staf beserta surel,
// peran, dan `password_hint` yang benar-benar berlaku — terbuka tanpa
// autentikasi sama sekali. Tidak ada pemakainya di frontend.
Route::prefix('auth')->middleware('throttle:login')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('alumni-login', [AlumniAuthController::class, 'login']);
});

Route::get('tracer-study/forms', [QuestionnaireFetchController::class, 'getActiveForms']);
Route::apiResource('questionnaires', QuestionnaireController::class)->only(['show']);

// ── Halaman publik (masyarakat umum, tanpa login) ────────────────────────
// Semua yang di sini menegakkan sendiri batasnya di server: laporan hanya
// yang is_published, statistik hanya tahun di dalam rentang pengarsipan yang
// diatur Ketua Tracer. Menyaring di frontend saja tidak cukup — query string
// bisa diketik siapa pun.
Route::prefix('public')->group(function () {
    Route::get('reports',                [PublicReportViewController::class, 'index']);
    Route::get('reports/{id}/download',  [PublicReportViewController::class, 'download'])->whereNumber('id');
    Route::get('reports/{id}/preview',   [PublicReportViewController::class, 'preview'])->whereNumber('id');

    Route::get('statistics/years',    [PublicStatisticsController::class, 'years']);
    Route::get('statistics/progress', [PublicStatisticsController::class, 'progress']);
});

// Unduhan CSV referensi kode (provinsi, kab/kota, prodi). Publik karena dibuka
// lewat window.open() dari halaman pengisian — tab baru tidak membawa header
// Authorization, sehingga versi ber-guard selalu 401 untuk siapa pun, staff
// maupun alumni. Isinya daftar kode wilayah dan prodi yang memang publik.
Route::get('provinces/download', [\App\Http\Controllers\Api\Transactional\RegionController::class, 'downloadProvinces']);
Route::get('cities/download',    [\App\Http\Controllers\Api\Transactional\RegionController::class, 'downloadCities']);
Route::get('programs/download',  [\App\Http\Controllers\Api\Transactional\RegionController::class, 'downloadPrograms']);

// ═══════════════════════════════════════════════════════════
// REFERENSI — boleh dibaca staff MAUPUN alumni
// ═══════════════════════════════════════════════════════════
// Data master untuk isian bertipe lookup di borang (Kode Prodi, Provinsi,
// Kab/Kota). Alumni memakai guard 'alumni' yang tidak berlaku di
// 'auth:sanctum', jadi rutenya tidak bisa ikut grup PROTECTED di bawah —
// tanpa ini dropdown referensi di halaman pengisian selalu kosong 401.
Route::middleware('auth:sanctum,alumni')->group(function () {
    Route::get('programs',      [ProgramController::class, 'index']);
    Route::get('programs/{id}', [ProgramController::class, 'show']);
    Route::get('provinces',     [\App\Http\Controllers\Api\Transactional\RegionController::class, 'provinces']);
    Route::get('cities',        [\App\Http\Controllers\Api\Transactional\RegionController::class, 'cities']);
});

// ═══════════════════════════════════════════════════════════
// ALUMNI — wajib login alumni (Sanctum, guard 'alumni')
// ═══════════════════════════════════════════════════════════
// Guard terpisah dari staff: token alumni tidak berlaku di 'auth:sanctum',
// dan token staff tidak berlaku di sini (lihat catatan di config/auth.php).
Route::middleware('auth:alumni')->group(function () {
    Route::post('auth/alumni-logout', [AlumniAuthController::class, 'logout']);

    // Submit kuesioner. Dulu publik — siapa pun bisa mengirim jawaban atas
    // nama NIM mana pun tanpa login. Sekarang NIM pada payload wajib cocok
    // dengan alumni pemegang token (dicek di controller).
    Route::post('tracer-study/submit', [TracerStudySubmitController::class, 'store']);

    // Draf pengisian (autosave lintas perangkat). Identitas diambil dari token,
    // bukan dari body — lihat TracerStudyDraftService.
    Route::get('tracer-study/draft',    [TracerStudyDraftController::class, 'show']);
    Route::post('tracer-study/draft',   [TracerStudyDraftController::class, 'store']);
    Route::delete('tracer-study/draft', [TracerStudyDraftController::class, 'destroy']);
});

// ═══════════════════════════════════════════════════════════
// PROTECTED — wajib login (Sanctum)
// ═══════════════════════════════════════════════════════════
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/change-password', [AuthController::class, 'changePassword']);

    // Questionnaires — index (semua role bisa lihat list)
    Route::get('questionnaires', [QuestionnaireController::class, 'index']);

    // Programs & Regions — read: pindah ke grup REFERENSI di atas supaya
    // alumni ikut bisa membacanya. Unduhan CSV-nya kini publik.

    // Roles — read (semua role)
    Route::get('roles', [RoleController::class, 'index']);

    // Ringkasan per tahun lulusan — dipakai halaman kartu tahun di FE
    // (alumni-data, student-management, questionnaire-results,
    // form-management). Cakupan datanya menyesuaikan jabatan pemanggil,
    // jadi tidak perlu gate role tambahan di sini.
    Route::get('meta/ringkasan-tahun', [\App\Http\Controllers\Api\Transactional\RingkasanTahunController::class, 'index']);

    // LAMs — semua role bisa GET
    Route::get('lams',          [LamController::class, 'index']);
    Route::get('lams/{id}',     [LamController::class, 'show']);

    // LAM nested reads — semua role
    Route::get('lams/{id}/versions',  [LamVersionController::class, 'byLam']);
    Route::get('lams/{id}/programs',  [LamProgramController::class, 'byLam']);
    Route::get('lams/{id}/full',      [LamController::class, 'full']);         // ?year=2025

    // LAM Versions — semua role bisa GET
    Route::get('lam-versions/{id}',            [LamVersionController::class, 'show']);
    Route::get('lam-versions/{id}/thresholds', [ThresholdController::class, 'byVersion']);
    Route::get('lams/{lamId}/thresholds/tracer-response', [ThresholdController::class, 'tracerResponseByLam']);

    // ── Super Admin only (head_tracer) ───────────────────────────────────
    // Catatan merge: gate lama 'role:kotc' (Integrasi) dan 'role:admin'
    // diseragamkan ke 'role:head_tracer' — 'kotc' tidak ada di RoleSeeder
    // maupun enum users, jadi endpoint-nya akan selalu 403 kalau dibiarkan.
    Route::middleware('role:head_tracer')->group(function () {

        // Roles CUD
        Route::post('roles', [RoleController::class, 'store']);
        Route::put('roles/{role}', [RoleController::class, 'update']);
        Route::delete('roles/{role}', [RoleController::class, 'destroy']);

        // Users (staff) CRUD
        Route::apiResource('users', UserController::class);
        Route::patch('users/{id}/toggle-status', [UserController::class, 'toggleStatus']);

        // Programs CRUD
        Route::post('programs',        [ProgramController::class, 'store']);
        Route::put('programs/{id}',    [ProgramController::class, 'update']);
        Route::delete('programs/{id}', [ProgramController::class, 'destroy']);

        // Jurusan CRUD (RBAC-02). Pembacaannya ada di kelompok data viewer
        // karena dipakai penyaring di banyak halaman.
        Route::post('jurusans',        [JurusanController::class, 'store']);
        Route::put('jurusans/{id}',    [JurusanController::class, 'update']);
        Route::delete('jurusans/{id}', [JurusanController::class, 'destroy']);

        // Provinces CRUD
        Route::post('provinces', [\App\Http\Controllers\Api\Transactional\RegionController::class, 'storeProvince']);
        Route::put('provinces/{id}', [\App\Http\Controllers\Api\Transactional\RegionController::class, 'updateProvince']);
        Route::delete('provinces/{id}', [\App\Http\Controllers\Api\Transactional\RegionController::class, 'destroyProvince']);

        // Cities CRUD
        Route::post('cities', [\App\Http\Controllers\Api\Transactional\RegionController::class, 'storeCity']);
        Route::put('cities/{id}', [\App\Http\Controllers\Api\Transactional\RegionController::class, 'updateCity']);
        Route::delete('cities/{id}', [\App\Http\Controllers\Api\Transactional\RegionController::class, 'destroyCity']);

        // Questionnaire delete (langsung tanpa approval)
        Route::delete('questionnaires/{questionnaire}', [QuestionnaireController::class, 'destroy']);

        // Approval management (approve/reject)
        Route::post('approvals/{id}/approve', [ApprovalController::class, 'approve']);
        Route::post('approvals/{id}/reject', [ApprovalController::class, 'reject']);

        // Pembukaan kembali pengisian alumni (Finish → Ongoing).
        //
        // Dipindahkan ke sini dari grup 'head_tracer,tracer_team': RBAC-04
        // menempatkan wewenang ini pada Ketua Tracer, sedangkan Tim Tracer
        // dan Kaprodi hanya mengajukan lewat approvals/request-reopen.
        // Sebelumnya Tim Tracer bisa mereset sendiri tanpa persetujuan
        // siapa pun, dan tanpa jejak.
        //
        // Jalur langsung ini tetap ada supaya Ketua Tracer tidak perlu
        // mengajukan permintaan kepada dirinya sendiri.
        Route::post('alumni/{alumniId}/reset-response', [\App\Http\Controllers\Api\Admin\AlumniController::class, 'resetResponse']);

        // Penerbitan kredensial alumni (RBAC-16). Membangkitkan kata sandi
        // acak, menyimpan cincangannya, lalu MENGALIRKAN berkas CSV berisi
        // teks polosnya dalam balasan yang sama — satu-satunya kesempatan,
        // karena cincangan bcrypt tidak dapat dibalik.
        //
        // Berkasnya setara daftar kata sandi banyak orang sekaligus, jadi
        // wewenangnya dipegang Ketua Tracer saja, sejalan dengan RBAC-01.
        Route::post('alumni/credentials/issue', [\App\Http\Controllers\Api\Admin\AlumniCredentialController::class, 'issue']);

        // LAMs
        Route::post('lams',        [LamController::class, 'store']);
        Route::put('lams/{id}',    [LamController::class, 'update']);
        Route::delete('lams/{id}', [LamController::class, 'destroy']);

        // LAM Versions
        Route::post('lam-versions',        [LamVersionController::class, 'store']);
        Route::put('lam-versions/{id}',    [LamVersionController::class, 'update']);
        Route::delete('lam-versions/{id}', [LamVersionController::class, 'destroy']);

        // LAM <-> Program mapping
        Route::post('lam-programs',   [LamProgramController::class, 'store']);
        Route::delete('lam-programs', [LamProgramController::class, 'destroy']);

        // Threshold Indicators
        Route::get('threshold-indicators', [ThresholdIndicatorController::class, 'index']);
        Route::put('threshold-indicators/{id}', [ThresholdIndicatorController::class, 'update']);

        // Thresholds
        Route::post('thresholds',        [ThresholdController::class, 'store']);
        Route::put('thresholds/{id}',    [ThresholdController::class, 'update']);
        Route::delete('thresholds/{id}', [ThresholdController::class, 'destroy']);
        Route::post('lam-versions/{id}/thresholds/bulk', [ThresholdController::class, 'bulkStore']);
        Route::put('lam-versions/{id}/thresholds/bulk',  [ThresholdController::class, 'bulkUpdate']);

    });

    // ── Semantic Role Mapping (ETL admin config) ──────────────────────
    // Read: semua role login (auth:sanctum). Write: hanya head_tracer, sama gate
    // dengan Threshold/LamVersion (lihat blok role:head_tracer di bawah).
    Route::get('semantic-roles', [SemanticRoleController::class, 'index']);

    Route::prefix('question-semantic-mappings')->group(function () {
        Route::get('/',        [QuestionSemanticMappingController::class, 'index']);
        Route::get('unmapped', [QuestionSemanticMappingController::class, 'unmapped']);
        Route::get('similar',  [QuestionSemanticMappingController::class, 'similar']);
        Route::get('option-candidates', [QuestionSemanticMappingController::class, 'optionCandidates']);
        Route::get('questionnaires', [QuestionSemanticMappingController::class, 'questionnaires']);
    });

    Route::prefix('kpi-category-mappings')->group(function () {
        Route::get('/',             [KpiCategoryMappingController::class, 'index']);
        Route::get('formula',       [KpiCategoryMappingController::class, 'formula']);
        Route::get('taxonomy-all',  [KpiCategoryMappingController::class, 'taxonomyAll']);
        Route::get('taxonomy',      [KpiCategoryMappingController::class, 'taxonomy']);
    });

    Route::get('etl-anomaly-log', [EtlAnomalyLogController::class, 'index']);
    Route::get('etl-runs/{id}', [EtlRunController::class, 'show']);

    // ── Publikasi (Ketua Tracer) ──────────────────────────────────
    // Requirement menyebut Ketua Tracer secara spesifik, jadi tidak
    // menumpang guard admin lain yang juga dipegang tracer_team.
    Route::middleware('role:head_tracer')->group(function () {
        Route::get('admin/public-reports',           [PublicReportController::class, 'index']);
        Route::post('admin/public-reports',          [PublicReportController::class, 'store']);
        Route::put('admin/public-reports/{id}',      [PublicReportController::class, 'update'])->whereNumber('id');
        Route::delete('admin/public-reports/{id}',   [PublicReportController::class, 'destroy'])->whereNumber('id');

        Route::get('admin/public-settings',  [PublicDisplaySettingController::class, 'show']);
        Route::put('admin/public-settings',  [PublicDisplaySettingController::class, 'update']);
    });

    Route::middleware('role:head_tracer')->group(function () {
        Route::post('question-semantic-mappings',              [QuestionSemanticMappingController::class, 'store']);
        Route::post('question-semantic-mappings/{id}/deactivate', [QuestionSemanticMappingController::class, 'deactivate']);

        Route::post('kpi-category-mappings',                   [KpiCategoryMappingController::class, 'store']);
        Route::post('kpi-category-mappings/{id}/deactivate',   [KpiCategoryMappingController::class, 'deactivate']);
    });

    Route::middleware('role:head_tracer')->prefix('ump')->group(function () {
        Route::get('years',                              [RefUmpController::class, 'years']);
        Route::get('template',                           [RefUmpController::class, 'template']);
        Route::get('{tahun}',                            [RefUmpController::class, 'show']);
        Route::get('{tahun}/fetch-bps',                  [RefUmpController::class, 'fetchBps']);
        Route::post('import',                            [RefUmpController::class, 'import']);
        Route::post('{tahun}/bulk-save',                 [RefUmpController::class, 'bulkSave']);
        Route::patch('{tahun}/provinces/{idProvinsi}',   [RefUmpController::class, 'updateSingle']);
    });

    // ── Super Admin + Admin (head_tracer, tracer_team) ───────────────────
    Route::middleware('role:head_tracer,tracer_team')->group(function () {
        // Create & edit kuesioner (tracer_team creates as draft, head_tracer can publish)
        Route::post('questionnaires', [QuestionnaireController::class, 'store']);
        Route::put('questionnaires/{questionnaire}', [QuestionnaireController::class, 'update']);

        // Request delete (tracer_team submits)
        Route::post('approvals/request-delete', [ApprovalController::class, 'requestDelete']);

        // Thresholds
        Route::apiResource('thresholds', ThresholdController::class);
    });

    // ── Pemohon persetujuan (tracer_team, kaprodi) ───────────────────────
    // Mengajukan saja; eksekusinya menunggu persetujuan head_tracer.
    // RBAC-08 (Tim Tracer) dan RBAC-12 (Kaprodi).
    Route::middleware('role:tracer_team,kaprodi')->group(function () {
        Route::post('approvals/request-reopen', [ApprovalController::class, 'requestReopen']);
    });

    // Daftar permintaan. Satu deklarasi untuk ketiga peran -- kalau rute
    // dengan method+URI yang sama didaftarkan dua kali, pendaftaran terakhir
    // menimpa yang sebelumnya, dan peran di grup lain kehilangan aksesnya.
    // Ketua Tracer melihat seluruh antrean, pemohon hanya miliknya sendiri;
    // penyaringnya ada di ApprovalController::index().
    Route::middleware('role:head_tracer,tracer_team,kaprodi')->group(function () {
        Route::get('approvals', [ApprovalController::class, 'index']);
    });

    // ── Data viewers — semua role bisa akses sesuai scope ────────────────
    // Daftar jurusan: mengisi penyaring di halaman Responden, Kelola Staff,
    // dan Kelola Mahasiswa, jadi tidak dibatasi peran admin.
    Route::get('jurusans', [JurusanController::class, 'index']);

    // Alumni management
    Route::get('alumni/stats', [\App\Http\Controllers\Api\Admin\AlumniController::class, 'stats']);
    // Harus di atas apiResource('alumni'): tanpa itu 'respondent-stats'
    // tertangkap sebagai {alumni} di route show.
    Route::get('alumni/respondent-stats', [\App\Http\Controllers\Api\Admin\AlumniController::class, 'respondentStats']);
    Route::get('alumni/template', [\App\Http\Controllers\Api\Admin\AlumniController::class, 'downloadTemplate']);
    Route::post('alumni/import', [\App\Http\Controllers\Api\Admin\AlumniController::class, 'importAlumni']);
    Route::apiResource('alumni', \App\Http\Controllers\Api\Admin\AlumniController::class);

    // ── Kontak Penilai (stakeholder) ─────────────────────────────────────
    // Dibatasi Ketua Tracer dan Tim Tracer. Isinya nama dan surel pihak
    // ketiga — atasan, rekan kerja, dosen pembimbing — bukan statistik
    // alumni, dan controller-nya tidak memakai EnforcesProdiScope. Tanpa
    // gate ini Kaprodi maupun Kajur yang memanggilnya akan menerima kontak
    // seluruh institusi. Merekalah pula yang mengirim email blast, jadi
    // pembatasan ini sekaligus mengikuti siapa yang benar-benar memakainya.
    Route::middleware('role:head_tracer,tracer_team')->group(function () {
        Route::get('stakeholder-contacts/export', [\App\Http\Controllers\Api\Admin\StakeholderContactController::class, 'export']);
        Route::get('stakeholder-contacts', [\App\Http\Controllers\Api\Admin\StakeholderContactController::class, 'index']);
        Route::post('stakeholder-contacts', [\App\Http\Controllers\Api\Admin\StakeholderContactController::class, 'store']);
    });

    // ── Reports (Laporan / Unduhan) ──────────────────
    // Dua path ke handler yang sama: 'admin/reports/...' dipakai FE sisi
    // Integrasi, 'reports/...' dipakai FE sisi Faiz. Keduanya dipertahankan
    // supaya tidak ada frontend yang patah setelah merge.
    Route::get('admin/reports/export-alumni', [\App\Http\Controllers\Api\Admin\ReportController::class, 'exportAlumniResponses']);
    Route::get('reports/export-alumni', [\App\Http\Controllers\Api\Admin\ReportController::class, 'exportAlumniResponses']);

    // ── ETL — hanya admin ───────────────────────────────────
    // Route::middleware("role:admin")->group(function () {
    //     Route::post("data-pipeline/import", [ExcelImportController::class, "store"]);
    //     Route::get("data-pipeline/status",  [ExcelImportController::class, "status"]);
    // });
 
    Route::prefix('dashboard/kpi')->group(function () {
        // KPI 13 — Perbandingan KPI Lintas Program Studi
        Route::get('13/chart',  [Kpi13Controller::class, 'chart']);
        Route::get('13/export', [Kpi13Controller::class, 'export']);
    });

    Route::prefix('dashboard')->group(function () {

        Route::get('meta/filter-options',        [FilterMetaController::class, 'filterOptions']);
        Route::get('thresholds', [ThresholdController::class, 'forChart']);
 
        // ── Segmen: Tingkat Keterserapan Lulusan ─────────────────────────
        Route::prefix('keterserapan')->group(function () {
    
            // Bar stacked 100% — distribusi status per tahun lulus
            // GET /api/dashboard/keterserapan/bar
            Route::get('bar',        [KeterserapanController::class, 'bar']);
    
            // Pie — distribusi status snapshot terkini
            // GET /api/dashboard/keterserapan/pie
            Route::get('pie',        [KeterserapanController::class, 'pie']);
    
            // Drill-down list alumni (modal klik chart)
            // GET /api/dashboard/keterserapan/drill-down
            Route::get('drill-down', [KeterserapanController::class, 'drillDown']);
    
            // Halaman Bandingkan Prodi — bar stacked + tabel
            // Chip filter prodi dibaca dari GET /api/dashboard/meta/filter-options → field prodi[]
            // GET /api/dashboard/keterserapan/bandingkan
            Route::get('bandingkan', [KeterserapanController::class, 'bandingkan']);
    
        });
        // ── Segmen: Tingkat Pendapatan Lulusan ─────────────────────────
        Route::prefix('pendapatan')->group(function () {

            // GET /api/dashboard/pendapatan/bar
            Route::get('bar',        [PendapatanController::class, 'bar']);

            // GET /api/dashboard/pendapatan/distribusi
            Route::get('distribusi', [PendapatanController::class, 'distribusi']);

            // GET /api/dashboard/pendapatan/drill-down
            Route::get('drill-down', [PendapatanController::class, 'drillDown']);

            // GET /api/dashboard/pendapatan/bandingkan
            Route::get('bandingkan', [PendapatanController::class, 'bandingkan']);

        });
    });

    Route::prefix('dashboard/masa-tunggu')->group(function () {
        Route::get('bar',        [MasaTungguController::class, 'bar']);
        Route::get('distribusi', [MasaTungguController::class, 'distribusi']);
        Route::get('drill-down', [MasaTungguController::class, 'drillDown']);
        Route::get('bandingkan', [MasaTungguController::class, 'bandingkan']);
        Route::get('pola-pencarian', [MasaTungguController::class, 'polaPencarianKerja']);
        Route::get('prediksi', [MasaTungguController::class, 'prediksi']);
    });

    Route::prefix('dashboard/kesesuaian')->group(function () {
        Route::get('bar',        [KesesuaianController::class, 'bar']);
        Route::get('pie',        [KesesuaianController::class, 'pie']);
        Route::get('alasan',     [KesesuaianController::class, 'alasan']);
        Route::get('drill-down', [KesesuaianController::class, 'drillDown']);
        Route::get('bandingkan', [KesesuaianController::class, 'bandingkan']);
    });

    Route::prefix('dashboard/wirausaha')->group(function () {
        Route::get('bar',        [WirausahaController::class, 'bar']);
        Route::get('pie',        [WirausahaController::class, 'pie']);
        Route::get('drill-down', [WirausahaController::class, 'drillDown']);
        Route::get('bandingkan', [WirausahaController::class, 'bandingkan']);
    });

    Route::prefix('dashboard/sebaraninstansi')->group(function () {
        Route::get('jenis',      [SebaranInstansiController::class, 'jenis']);
        Route::get('tingkat',    [SebaranInstansiController::class, 'tingkat']);
        Route::get('bandingkan', [SebaranInstansiController::class, 'bandingkan']);
        Route::get('lokasi',     [SebaranInstansiController::class, 'lokasi']);
        Route::get('drill-down', [SebaranInstansiController::class, 'drillDown']);
    });

    Route::prefix('dashboard/kompetensi')->group(function () {
        Route::get('gap',            [KompetensiGapController::class, 'gap']);
        Route::get('gap/bandingkan', [KompetensiGapController::class, 'bandingkan']);
        Route::get('gap/drill-down', [KompetensiGapController::class, 'drillDown']);
        Route::get('metode',            [MetodePembelajaranController::class, 'metode']);
        Route::get('metode/bandingkan', [MetodePembelajaranController::class, 'bandingkan']);
        Route::get('metode/drill-down', [MetodePembelajaranController::class, 'drillDown']);
    });

    Route::prefix('dashboard/pembiayaan')->group(function () {
        Route::get('pie',        [PembiayaanController::class, 'pie']);
        Route::get('per-prodi',  [PembiayaanController::class, 'perProdi']);
        Route::get('bandingkan', [PembiayaanController::class, 'bandingkan']);
        Route::get('antar-periode', [PembiayaanController::class, 'antarPeriode']);
        Route::get('drill-down',    [PembiayaanController::class, 'drillDown']);  

    });

    Route::prefix('dashboard/response-rate')->group(function () {
        Route::get('bar',        [ResponseRateController::class, 'bar']);
        Route::get('pie',        [ResponseRateController::class, 'pie']);
        Route::get('trend',      [ResponseRateController::class, 'trend']);
        Route::get('drill-down', [ResponseRateController::class, 'drillDown']);
    });

    Route::get('dashboard/overview/summary', [SummaryController::class, 'summary']);
    Route::get('dashboard/education/summary',  [EducationSummaryController::class, 'summary']);
    Route::get('dashboard/employment/summary', [EmploymentSummaryController::class, 'summary']);

});
