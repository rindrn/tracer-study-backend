<?php
 
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Transactional\ThresholdController;
use App\Http\Controllers\Api\Transactional\ProgramController; 
// use App\Http\Controllers\Api\Transactional\TracerOfficerController;
// use App\Http\Controllers\Api\Transactional\QuestionnaireController;

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
    Route::post('login', [AuthController::class, 'login']);
});
 
// ═══════════════════════════════════════════════════════════
// PROTECTED — wajib login (Sanctum token)
// ═══════════════════════════════════════════════════════════
Route::middleware("auth:sanctum")->group(function () {
 
    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me',     [AuthController::class, 'me']);
 
    // Programs — hanya admin yang bisa CRUD (p2mpp & prodi hanya GET)
    Route::get('programs',       [ProgramController::class, 'index']);
    Route::get('programs/{id}',  [ProgramController::class, 'show']);
 
    Route::middleware('role:admin')->group(function () {
        Route::post('programs',          [ProgramController::class, 'store']);
        Route::put('programs/{id}',      [ProgramController::class, 'update']);
        Route::delete('programs/{id}',   [ProgramController::class, 'destroy']);
    });
 
    // ── Transactional CRUD (hanya admin) ────────────
    Route::middleware("role:admin")->group(function () {
        // Route::apiResource("tracer-officers", TracerOfficerController::class);
        // Route::apiResource("questionnaires",  QuestionnaireController::class);
        Route::apiResource("thresholds",      ThresholdController::class);
        // Menghasilkan 5 endpoint per resource:
        // GET    /api/thresholds            -> index
        // POST   /api/thresholds            -> store
        // GET    /api/thresholds/{id}       -> show
        // PUT    /api/thresholds/{id}       -> update
        // DELETE /api/thresholds/{id}       -> destroy
    });
 
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
