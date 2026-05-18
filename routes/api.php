<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\AlumniAuthController;
use App\Http\Controllers\Api\Transactional\ThresholdController;
use App\Http\Controllers\Api\Transactional\ProgramController;
use App\Http\Controllers\Api\Transactional\QuestionnaireController;
use App\Http\Controllers\Api\Transactional\TracerStudySubmitController;
use App\Http\Controllers\Api\Transactional\QuestionnaireFetchController;

// ═══════════════════════════════════════════════════════════
// PUBLIC
// ═══════════════════════════════════════════════════════════
Route::prefix('auth')->group(function () {
    Route::get('demo-accounts', [AuthController::class, 'demoAccounts']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('alumni-login', [AlumniAuthController::class, 'login']);
});

Route::get('tracer-study/forms', [QuestionnaireFetchController::class, 'getActiveForms']);
Route::post('tracer-study/submit', [TracerStudySubmitController::class, 'store']);
Route::apiResource('questionnaires', QuestionnaireController::class)->only(['show']);

// ═══════════════════════════════════════════════════════════
// PROTECTED — wajib login (Sanctum)
// ═══════════════════════════════════════════════════════════
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    // Questionnaires — index (semua role bisa lihat list)
    Route::get('questionnaires', [QuestionnaireController::class, 'index']);

    // Programs — read (semua role)
    Route::get('programs', [ProgramController::class, 'index']);
    Route::get('programs/{id}', [ProgramController::class, 'show']);

    // ── Super Admin only (head_tracer) ───────────────────────────────────
    Route::middleware('role:head_tracer')->group(function () {
        // Programs CRUD
        Route::post('programs', [ProgramController::class, 'store']);
        Route::put('programs/{id}', [ProgramController::class, 'update']);
        Route::delete('programs/{id}', [ProgramController::class, 'destroy']);

        // Questionnaire full manage (add/delete langsung tanpa approval)
        Route::apiResource('questionnaires', QuestionnaireController::class)->only(['store', 'destroy']);

        // Approval management
        // Route::get('approvals', [ApprovalController::class, 'index']);
        // Route::post('approvals/{id}/approve', [ApprovalController::class, 'approve']);
        // Route::post('approvals/{id}/reject', [ApprovalController::class, 'reject']);
    });

    // ── Super Admin + Admin (head_tracer, tracer_team) ───────────────────
    Route::middleware('role:head_tracer,tracer_team')->group(function () {
        // Edit kuesioner
        Route::put('questionnaires/{questionnaire}', [QuestionnaireController::class, 'update']);

        // Thresholds
        Route::apiResource('thresholds', ThresholdController::class);
    });

    // ── Admin request (tracer_team) — perlu approval ─────────────────────
    Route::middleware('role:tracer_team')->group(function () {
        // Route::post('approval-requests', [ApprovalRequestController::class, 'store']);
        // Route::get('approval-requests/mine', [ApprovalRequestController::class, 'myRequests']);
    });

    // ── Data viewers — semua role bisa akses sesuai scope ────────────────
    // Alumni management
    Route::get('alumni/stats', [\App\Http\Controllers\Api\Admin\AlumniController::class, 'stats']);
    Route::get('alumni/template', [\App\Http\Controllers\Api\Admin\AlumniController::class, 'downloadTemplate']);
    Route::post('alumni/import', [\App\Http\Controllers\Api\Admin\AlumniController::class, 'importAlumni']);
    Route::apiResource('alumni', \App\Http\Controllers\Api\Admin\AlumniController::class);

    // Reports
    Route::get('reports/export-alumni', [\App\Http\Controllers\Api\Admin\ReportController::class, 'exportAlumniResponses']);
});
