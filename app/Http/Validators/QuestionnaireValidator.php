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
        $questionTypes = ['short', 'paragraph', 'multiple_choice', 'checkbox', 'dropdown', 'file_upload', 'linear_scale', 'rating', 'multiple_choice_grid', 'checkbox_grid', 'date', 'time'];

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
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.title' => ['required', 'string', 'max:200'],
            'sections.*.description' => ['nullable', 'string'],
            'sections.*.order_no' => ['nullable', 'integer', 'min:1'],
            'sections.*.questions' => ['required', 'array', 'min:1'],
            'sections.*.questions.*.code' => ['nullable', 'string', 'max:80'],
            'sections.*.questions.*.question' => ['required', 'string'],
            'sections.*.questions.*.type' => ['required', 'string', 'in:' . implode(',', $questionTypes)],
            'sections.*.questions.*.description' => ['nullable', 'string'],
            'sections.*.questions.*.required' => ['sometimes', 'boolean'],
            'sections.*.questions.*.allowOther' => ['sometimes', 'boolean'],
            'sections.*.questions.*.scaleMin' => ['nullable', 'integer'],
            'sections.*.questions.*.scaleMax' => ['nullable', 'integer'],
            'sections.*.questions.*.gridRows' => ['nullable', 'array'],
            'sections.*.questions.*.gridRows.*' => ['nullable', 'string'],
            'sections.*.questions.*.gridColumns' => ['nullable', 'array'],
            'sections.*.questions.*.gridColumns.*' => ['nullable', 'string'],
            'sections.*.questions.*.options' => ['nullable', 'array'],
            'sections.*.questions.*.options.*.label' => ['nullable', 'string', 'max:255'],
            'sections.*.questions.*.options.*.value' => ['nullable', 'string', 'max:255'],
            'sections.*.questions.*.options.*.code' => ['nullable', 'string', 'max:80'],
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