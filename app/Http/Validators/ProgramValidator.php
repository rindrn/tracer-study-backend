<?php
namespace App\Http\Validators;
 
use App\Support\Degree;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
 
class ProgramValidator
{
    public function validateCreate(array $data): array
    {
        return $this->validate($data, isUpdate: false);
    }
 
    public function validateUpdate(array $data, int $id): array
    {
        return $this->validate($data, isUpdate: true, id: $id);
    }
 
    private function validate(array $data, bool $isUpdate = false, int $id = 0): array
    {
        // Rule code: unique kecuali dirinya sendiri saat update
        $codeRule = $isUpdate
            ? "unique:oltp.programs,code,{$id}"
            : 'unique:oltp.programs,code';
 
        $v = Validator::make($data, [
            'name'      => ['required', 'string', 'max:100'],
            'code'      => ['required', 'string', 'max:20', $codeRule],
            // Kosakata tertutup, tapi ditetapkan regulasi — bukan oleh
            // kampus. Daftarnya di config/academic.php supaya politeknik
            // (D1-D4), universitas (S1-S3), dan kampus dengan program
            // profesi atau spesialis sama-sama tertampung.
            'degree'    => ['required', Degree::in()],
            'is_active' => ['sometimes', 'boolean'],
            // Bebas teksnya: peringkat berbeda antar lembaga dan antar zaman
            // (A/B/C, Unggul/Baik Sekali/Baik, atau istilah khas tiap LAM).
            // Daftar tertutup akan memaksa perubahan kode tiap kali ada
            // lembaga yang memakai istilah lain.
            'accreditation'    => ['sometimes', 'nullable', 'string', 'max:30'],
            'accredited_until' => ['sometimes', 'nullable', 'date'],
        ], [
            'name.required'   => 'Nama program studi wajib diisi.',
            'code.required'   => 'Kode program studi wajib diisi.',
            'code.unique'     => 'Kode program studi sudah digunakan.',
            'degree.required' => 'Jenjang wajib diisi.',
            'degree.in'       => 'Jenjang harus salah satu dari: '
                                 . implode(', ', Degree::all()) . '.',
        ]);
 
        if ($v->fails()) throw new ValidationException($v);
        return $v->validated();
    }
}
