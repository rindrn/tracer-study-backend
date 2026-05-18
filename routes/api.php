<?php
 
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\AlumniAuthController;
use App\Http\Controllers\Api\Transactional\ThresholdController;
use App\Http\Controllers\Api\Transactional\ProgramController; 
// use App\Http\Controllers\Api\Transactional\TracerOfficerController;
use App\Http\Controllers\Api\Transactional\QuestionnaireController;

// Form Submission & Fetch Tracer Study
use App\Http\Controllers\Api\Transactional\TracerStudySubmitController;
use App\Http\Controllers\Api\Transactional\QuestionnaireFetchController;

// Controllers — Dashboard (OLAP page config)
// use App\Http\Controllers\Api\Dashboard\OverviewController;
// use App\Http\Controllers\Api\Dashboard\EmploymentController;
// use App\Http\Controllers\Api\Dashboard\EducationController;
// use App\Http\Controllers\Api\Dashboard\AnalyticsController;
 
// Controllers — Charts (OLAP chart data)
// use App\Http\Controllers\Api\Charts\StatusChartController;
// use App\Http\Controllers\Api\Charts\GenderChartController;
// use App\Http\Controllers\Api\Charts\SalaryChartController;
// use App\Http\Controllers\Api\Charts\WaitingTimeChartController;
 
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
 
    // ── Transactional CRUD (admin + kaprodi) ──────────
    // Kaprodi boleh kelola threshold & questionnaire di prodinya; filter by
    // program_id di-handle di level Service/Controller.
    Route::middleware("role:admin,kaprodi")->group(function () {
        // Route::apiResource("tracer-officers", TracerOfficerController::class);
        Route::apiResource("questionnaires",  QuestionnaireController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource("thresholds",      ThresholdController::class);
        // Menghasilkan 5 endpoint per resource:
        // GET    /api/thresholds            -> index
        // POST   /api/thresholds            -> store
        // GET    /api/thresholds/{id}       -> show
        // PUT    /api/thresholds/{id}       -> update
        // DELETE /api/thresholds/{id}       -> destroy
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
    // Route stats & template HARUS sebelum apiResource agar tidak ketangkap oleh `/{id}`.
    Route::get('alumni/stats',    [\App\Http\Controllers\Api\Admin\AlumniController::class, 'stats']);
    Route::get('alumni/template', [\App\Http\Controllers\Api\Admin\AlumniController::class, 'downloadTemplate']);
    Route::post('alumni/import',  [\App\Http\Controllers\Api\Admin\AlumniController::class, 'importAlumni']);
    Route::apiResource('alumni', \App\Http\Controllers\Api\Admin\AlumniController::class);

    // ── Reports (Laporan / Unduhan) ──────────────────
    Route::get('reports/export-alumni', [\App\Http\Controllers\Api\Admin\ReportController::class, 'exportAlumniResponses']);
 
    // ── ETL — hanya admin ───────────────────────────────────
    // Route::middleware("role:admin")->group(function () {
    //     Route::post("data-pipeline/import", [ExcelImportController::class, "store"]);
    //     Route::get("data-pipeline/status",  [ExcelImportController::class, "status"]);
    // });
 
    // ── Dashboard page config (semua role yang login) ────────
    // Route::prefix("dashboard")->group(function () {
    //     Route::get("overview",   [OverviewController::class,   "index"]);
    //     Route::get("employment", [EmploymentController::class, "index"]);
    //     Route::get("education",  [EducationController::class,  "index"]);
    //     Route::get("analytics",  [AnalyticsController::class,  "index"]);
    // });
 
    // ── Chart data endpoints (semua role yang login) ─────────
    // Route::prefix("charts")->group(function () {
    //     Route::get("status",           [StatusChartController::class,      "index"]);
    //     Route::get("gender",           [GenderChartController::class,      "index"]);
    //     Route::get("salary",           [SalaryChartController::class,      "index"]);
    //     Route::get("salary/detail",    [SalaryChartController::class,      "detail"]);
    //     Route::get("waiting-time",     [WaitingTimeChartController::class, "index"]);
    // });
 
});
