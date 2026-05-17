<?php
 
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Transactional\ThresholdIndicatorController;
use App\Http\Controllers\Api\Transactional\ThresholdController;
use App\Http\Controllers\Api\Transactional\LamController; 
use App\Http\Controllers\Api\Transactional\LamVersionController; 
use App\Http\Controllers\Api\Transactional\LamProgramController; 
use App\Http\Controllers\Api\Transactional\ProgramController; 
use App\Http\Controllers\Api\Analytical\Kpi7Controller;

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

    Route::prefix('dashboard/kpi')->group(function () {
        Route::get('7/chart',   [Kpi7Controller::class, 'chart']);
        Route::get('7/details', [Kpi7Controller::class, 'details']);
        Route::get('7/export',  [Kpi7Controller::class, 'export']);
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
