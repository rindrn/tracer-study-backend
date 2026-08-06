<?php

namespace App\Http\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class QuestionnaireValidator
{
    public function validateCreate(array $data): array
    {
        return $this->validate($data, false);
    }

    public function validateUpdate(array $data): array
    {
        return $this->validate($data, true);
    }

    private function validate(array $data, bool $isUpdate): array
    {
        // 'lookup' = isian yang pilihannya diambil dari tabel referensi
        // (programs/provinces/cities), bukan diketik pembuat borang. Di
        // database tetap tersimpan sebagai short_text; pembedanya ada di
        // metadata — lihat QuestionnaireService::buildQuestionMetadata().
        $questionTypes = ['short', 'paragraph', 'number', 'multiple_choice', 'checkbox', 'dropdown', 'lookup', 'file_upload', 'linear_scale', 'rating', 'multiple_choice_grid', 'checkbox_grid', 'date', 'time'];

        $validator = Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'target' => ['nullable', 'string', 'max:255'],
            'respondents' => ['nullable', 'array'],
            'respondents.*' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:draft,published,archived'],
            'period_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'code' => ['nullable', 'string', 'max:80'],
            'version' => ['nullable', 'integer', 'min:1'],
            'program_code' => ['nullable', 'string', 'exists:oltp.programs,code'],
            'program_id' => ['nullable', 'integer', 'exists:oltp.programs,id'],
            'target_graduation_years' => ['nullable', 'array'],
            'target_graduation_years.*' => ['integer'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.title' => ['required', 'string', 'max:200'],
            'sections.*.description' => ['nullable', 'string'],
            'sections.*.order_no' => ['nullable', 'integer', 'min:1'],
            'sections.*.questions' => ['required', 'array', 'min:1'],
            'sections.*.questions.*.code' => ['nullable', 'string', 'max:80'],
            'sections.*.questions.*.order_no' => ['nullable', 'integer', 'min:1'],
            'sections.*.questions.*.question' => ['required', 'string'],
            'sections.*.questions.*.type' => ['required', 'string', 'in:' . implode(',', $questionTypes)],
            'sections.*.questions.*.description' => ['nullable', 'string', 'max:300'],
            // Medan bantu pengisian. Disimpan di metadata pertanyaan dan
            // dibaca perender formulir maupun pratinjau — lihat
            // QuestionnaireService::EDITABLE_METADATA_KEYS.
            'sections.*.questions.*.hint' => ['nullable', 'string', 'max:300'],
            'sections.*.questions.*.format' => ['nullable', 'string', 'in:email,phone,url,currency'],
            'sections.*.questions.*.divider_label' => ['nullable', 'string', 'max:300'],
            // Metadata semantik untuk ETL, dibawa apa adanya lintas salinan.
            'sections.*.questions.*.competency' => ['nullable', 'string', 'max:100'],
            'sections.*.questions.*.dimension' => ['nullable', 'string', 'max:100'],
            'sections.*.questions.*.method' => ['nullable', 'string', 'max:100'],
            // Batas kewajaran isian angka. PERINGATAN, bukan penolakan —
            // alumni tetap boleh mengirim nilai di luar rentang ini.
            'sections.*.questions.*.warn_min' => ['nullable', 'numeric'],
            'sections.*.questions.*.warn_max' => ['nullable', 'numeric'],
            'sections.*.questions.*.option_hints' => ['nullable', 'array'],
            'sections.*.questions.*.option_hints.*' => ['nullable', 'string', 'max:300'],
            'sections.*.questions.*.required' => ['sometimes', 'boolean'],
            'sections.*.questions.*.allowOther' => ['sometimes', 'boolean'],
            'sections.*.questions.*.scaleMin' => ['nullable', 'integer'],
            'sections.*.questions.*.scaleMax' => ['nullable', 'integer'],
            // Keterangan ujung skala. WAJIB punya aturan: validated() hanya
            // mengembalikan kunci yang tervalidasi, jadi medan tanpa aturan
            // ikut terbuang diam-diam. Itulah sebabnya salinan kuesioner
            // kehilangan label "Sangat Rendah / Sangat Tinggi" pada seluruh
            // pertanyaan kompetensi.
            'sections.*.questions.*.scaleLabels' => ['nullable', 'array'],
            'sections.*.questions.*.scaleLabels.*' => ['nullable', 'string', 'max:255'],
            'sections.*.questions.*.gridRows' => ['nullable', 'array'],
            'sections.*.questions.*.gridRows.*' => ['nullable', 'string'],
            'sections.*.questions.*.gridColumns' => ['nullable', 'array'],
            'sections.*.questions.*.gridColumns.*' => ['nullable', 'string'],
            'sections.*.questions.*.options' => ['nullable', 'array'],
            'sections.*.questions.*.options.*.label' => ['nullable', 'string', 'max:255'],
            'sections.*.questions.*.options.*.value' => ['nullable', 'string', 'max:255'],
            'sections.*.questions.*.options.*.code' => ['nullable', 'string', 'max:80'],
            'sections.*.questions.*.options.*.is_hidden' => ['nullable', 'boolean'],
            'sections.*.questions.*.logic' => ['nullable', 'array'],
            'sections.*.questions.*.logic.type' => ['nullable', 'string', 'in:always,in_array'],
            'sections.*.questions.*.logic.dependsOn' => ['nullable', 'string', 'max:100'],
            'sections.*.questions.*.logic.values' => ['nullable', 'array'],
            'sections.*.questions.*.logic.values.*' => ['nullable', 'string', 'max:255'],
            'sections.*.questions.*.lookup' => ['nullable', 'string', 'in:program,province,city'],
            'sections.*.questions.*.lookupValue' => ['nullable', 'string', 'in:id,code'],
            'sections.*.questions.*.dependsOn' => ['nullable', 'string', 'max:100'],
            'sections.*.questions.*.group_code' => ['nullable', 'string', 'max:100'],
            'sections.*.questions.*.group_label' => ['nullable', 'string', 'max:500'],
            'sections.*.questions.*.group_title' => ['nullable', 'string', 'max:500'],
        ], [
            'title.required' => 'Judul kuisioner wajib diisi.',
            'status.required' => 'Status kuisioner wajib diisi.',
            'sections.required' => 'Minimal 1 section harus diisi.',
            'sections.min' => 'Minimal 1 section harus diisi.',
            'sections.*.title.required' => 'Judul section wajib diisi.',
            'sections.*.questions.required' => 'Minimal 1 pertanyaan harus diisi di setiap section.',
            'sections.*.questions.min' => 'Minimal 1 pertanyaan harus diisi di setiap section.',
            'sections.*.questions.*.question.required' => 'Teks pertanyaan wajib diisi.',
            'sections.*.questions.*.type.required' => 'Tipe pertanyaan wajib diisi.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}