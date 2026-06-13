<?php
// app/Repositories/Transactional/ProgramRepository.php
namespace App\Repositories\Transactional;
 
use App\Models\Transactional\Program;
use Illuminate\Database\Eloquent\Collection;
 
class ProgramRepository
{
    public function all(bool $includeInactive = false, ?string $degree = null): Collection
    {
        $query = Program::query();
 
        if (! $includeInactive) {
            $query->where('is_active', true);
        }
        if ($degree) {
            $query->where('degree', $degree);
        }
 
        return $query->orderBy('name')->get();
    }
 
    public function findById(int $id): ?Program
    {
        return Program::find($id);
    }

    public function findByCode(string $code): ?Program
    {
        return Program::where('code', $code)->first();
    }

    /** Semua program, keyed by id — dipakai ReportService untuk lookup cepat. */
    public function allIndexedById(): \Illuminate\Database\Eloquent\Collection
    {
        return Program::all()->keyBy('id');
    }
 
    public function create(array $data): Program
    {
        return Program::create($data);
    }
 
    public function update(Program $program, array $data): Program
    {
        $program->update($data);
        return $program->fresh();
    }
 
    // Soft delete — nonaktifkan, jangan hapus permanen
    // (data threshold_programs dan users tetap aman)
    public function deactivate(Program $program): void
    {
        $program->update(['is_active' => false]);
    }
 
    public function hasActiveUsers(Program $program): bool
    {
        return $program->users()->exists();
    }
}
