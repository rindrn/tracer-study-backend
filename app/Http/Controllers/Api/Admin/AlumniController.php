<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Api\Admin\StoreAlumniRequest;
use App\Http\Requests\Api\Admin\UpdateAlumniRequest;

class AlumniController extends Controller
{
    /**
     * Display a listing of the alumni.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = DB::connection('oltp')->table('alumni_profiles')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->select(
                'alumni_profiles.*',
                'programs.name as program_name',
                'programs.jurusan as jurusan_name'
            );

        // ROLE CHECK: Jika prodi, paksa filter hanya untuk prodinya saja
        if ($user->isProdi()) {
            $query->where('alumni_profiles.program_id', $user->program_id);
        }

        // Pencarian (Search)
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('alumni_profiles.nim', 'like', "%{$search}%")
                  ->orWhere('alumni_profiles.name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->query('per_page', 15);
        $alumni = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $alumni
        ]);
    }

    /**
     * Store a newly created alumni.
     */
    public function store(StoreAlumniRequest $request)
    {
        $user = $request->user();

        // P2MPP tidak boleh Create
        if ($user->isP2mpp()) {
            return response()->json(['message' => 'P2MPP tidak diizinkan menambah data alumni.'], 403);
        }

        $validated = $request->validated();

        // ROLE CHECK: Force program_id untuk prodi
        if ($user->isProdi()) {
            $validated['program_id'] = $user->program_id;
        }

        $validated['created_at'] = now();
        $validated['updated_at'] = now();

        $id = DB::connection('oltp')->table('alumni_profiles')->insertGetId($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data alumni berhasil ditambahkan.',
            'data' => ['id' => $id]
        ], 201);
    }

    /**
     * Display the specified alumni.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $alumni = DB::connection('oltp')->table('alumni_profiles')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->select(
                'alumni_profiles.*',
                'programs.name as program_name',
                'programs.jurusan as jurusan_name'
            )
            ->where('alumni_profiles.id', $id)
            ->first();

        if (!$alumni) {
            return response()->json(['message' => 'Alumni tidak ditemukan.'], 404);
        }

        // ROLE CHECK: Cegah akses jika beda prodi
        if ($user->isProdi() && $alumni->program_id !== $user->program_id) {
            return response()->json(['message' => 'Anda tidak memiliki hak akses untuk alumni prodi lain.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $alumni
        ]);
    }

    /**
     * Update the specified alumni.
     */
    public function update(UpdateAlumniRequest $request, $id)
    {
        $user = $request->user();

        // P2MPP tidak boleh Update
        if ($user->isP2mpp()) {
            return response()->json(['message' => 'P2MPP tidak diizinkan mengubah data alumni.'], 403);
        }

        $alumni = DB::connection('oltp')->table('alumni_profiles')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->select('alumni_profiles.*', 'programs.name as program_name', 'programs.jurusan as jurusan_name')
            ->where('alumni_profiles.id', $id)
            ->first();

        if (!$alumni) {
            return response()->json(['message' => 'Alumni tidak ditemukan.'], 404);
        }

        // ROLE CHECK: Cegah akses jika beda prodi
        if ($user->isProdi() && $alumni->program_id !== $user->program_id) {
            return response()->json(['message' => 'Anda tidak memiliki hak akses untuk mengubah alumni prodi lain.'], 403);
        }

        $validated = $request->validated();
        $validated['updated_at'] = now();

        // Admin Prodi tidak boleh mengubah program_id (membajak ke prodi lain)
        if ($user->isProdi() && isset($validated['program_id'])) {
            unset($validated['program_id']); 
        }

        DB::connection('oltp')->table('alumni_profiles')->where('id', $id)->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data alumni berhasil diperbarui.'
        ]);
    }

    /**
     * Remove the specified alumni from storage.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        // P2MPP tidak boleh Delete
        if ($user->isP2mpp()) {
            return response()->json(['message' => 'P2MPP tidak diizinkan menghapus data alumni.'], 403);
        }

        $alumni = DB::connection('oltp')->table('alumni_profiles')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->select('alumni_profiles.*', 'programs.name as program_name', 'programs.jurusan as jurusan_name')
            ->where('alumni_profiles.id', $id)
            ->first();

        if (!$alumni) {
            return response()->json(['message' => 'Alumni tidak ditemukan.'], 404);
        }

        // ROLE CHECK: Cegah akses jika beda prodi
        if ($user->isProdi() && $alumni->program_id !== $user->program_id) {
            return response()->json(['message' => 'Anda tidak memiliki hak akses untuk menghapus alumni prodi lain.'], 403);
        }

        DB::connection('oltp')->table('alumni_profiles')->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data alumni berhasil dihapus.'
        ]);
    }
}
