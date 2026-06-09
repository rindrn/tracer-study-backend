<?php
 
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;

use App\Http\Controllers\Api\Transactional\ThresholdIndicatorController;
use App\Http\Controllers\Api\Auth\AlumniAuthController;
use App\Http\Controllers\Api\Transactional\ThresholdController;
use App\Http\Controllers\Api\Transactional\LamController; 
use App\Http\Controllers\Api\Transactional\LamVersionController; 
use App\Http\Controllers\Api\Transactional\LamProgramController; 
use App\Http\Controllers\Api\Transactional\ProgramController; 

// use App\Http\Controllers\Api\Transactional\TracerOfficerController;
use App\Http\Controllers\Api\Transactional\QuestionnaireController;

// Form Submission & Fetch Tracer Study
use App\Http\Controllers\Api\Transactional\TracerStudySubmitController;
use App\Http\Controllers\Api\Transactional\QuestionnaireFetchController;

use App\Http\Controllers\Api\Analytical\Kpi13Controller;
// Controllers — Dashboard (OLAP page config)
use App\Http\Controllers\Api\Analytical\KeterserapanController;
use App\Http\Controllers\Api\Analytical\FilterMetaController;
 
// Controllers — DataPipeline (ETL)
// use App\Http\Controllers\Api\DataPipeline\ExcelImportController;
 
// ═══════════════════════════════════════════════════════════
// PUBLIC — tidak butuh autentikasi
// ═══════════════════════════════════════════════════════════
Route::prefix('auth')->group(function () {
    Route::get('demo-accounts', [AuthController::class, 'demoAccounts']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('alumni-login', [AlumniAuthController::class, 'login']);  // Login alumni untuk isi kuesioner
});
 
// ═══════════════════════════════════════════════════════════
// PROTECTED — wajib login (Sanctum token)
// ═══════════════════════════════════════════════════════════
Route::get('tracer-study/forms', [QuestionnaireFetchController::class, 'getActiveForms']); // Endpoint penarik soal untuk frontend UI
Route::post('tracer-study/submit', [TracerStudySubmitController::class, 'store']); // Bisa dibuat public atau diproteksi sanctum sesuai policy. Disini diset public dahulu krn blm ada kepastian login as alumni.

Route::apiResource('questionnaires', QuestionnaireController::class)->only(['show']); // Public show for student form fetch

Route::middleware("auth:sanctum")->group(function () {
 
    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me',     [AuthController::class, 'me']);

    // Questionnaires — index inside auth so we can filter by role
    Route::get('questionnaires', [QuestionnaireController::class, 'index']);
 
    // Programs — hanya admin yang bisa CRUD (p2mpp & prodi hanya GET)
    Route::get('programs',       [ProgramController::class, 'index']);
    Route::get('programs/{id}',  [ProgramController::class, 'show']);
 
    Route::middleware('role:admin')->group(function () {
        Route::post('programs',          [ProgramController::class, 'store']);
        Route::put('programs/{id}',      [ProgramController::class, 'update']);
        Route::delete('programs/{id}',   [ProgramController::class, 'destroy']);
    });


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

    // ── Admin only ───────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {

        // Programs
        Route::post('programs',        [ProgramController::class, 'store']);
        Route::put('programs/{id}',    [ProgramController::class, 'update']);
        Route::delete('programs/{id}', [ProgramController::class, 'destroy']);

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

        // Thresholds
        Route::post('thresholds',        [ThresholdController::class, 'store']);
        Route::put('thresholds/{id}',    [ThresholdController::class, 'update']);
        Route::delete('thresholds/{id}', [ThresholdController::class, 'destroy']);
        Route::post('lam-versions/{id}/thresholds/bulk', [ThresholdController::class, 'bulkStore']);
        Route::put('lam-versions/{id}/thresholds/bulk',  [ThresholdController::class, 'bulkUpdate']);

    });

    // ── Manajemen Staff & Tim Tracer (admin + head_tracer) ─────────────────
    // Scaffolding untuk endpoint CRUD staff / team — controller belum dibuat,
    // biarkan group kosong dulu agar struktur konsisten dengan permission FE:
    //   admin.staff (CRUD akun staff) + admin.team (CRUD tim tracer).
    Route::middleware("role:admin,head_tracer")->group(function () {
        // Route::apiResource('admin/staff',        StaffController::class);
        // Route::apiResource('admin/tracer-team',  TracerTeamController::class);
    });

    // ── Manajemen Alumni (Admin & Prodi & P2MPP) ─────
    // Route stats HARUS sebelum apiResource agar tidak ketangkap oleh `/{id}`.
    Route::get('admin/alumni/stats', [\App\Http\Controllers\Api\Admin\AlumniController::class, 'stats']);
    Route::apiResource('admin/alumni', \App\Http\Controllers\Api\Admin\AlumniController::class);

    // ── Reports (Laporan / Unduhan) ──────────────────
    Route::get('admin/reports/export-alumni', [\App\Http\Controllers\Api\Admin\ReportController::class, 'exportAlumniResponses']);
 
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
    });
});
