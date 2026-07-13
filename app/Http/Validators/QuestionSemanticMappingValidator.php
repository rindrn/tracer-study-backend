<?php
namespace App\Http\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class QuestionSemanticMappingValidator
{
    public function validateStore(array $data): array
    {
        return $this->validate($data, [
            'questionnaire_id' => ['required', 'integer', 'exists:oltp.questionnaires,id'],
            'question_code'    => ['required', 'string', 'max:80'],
            'semantic_role'    => ['required', 'string', 'max:50', 'exists:oltp.semantic_role_registry,role_key'],
            'force_replace'    => ['nullable', 'boolean'],
        ]);
    }

    public function validateSimilar(array $data): array
    {
        return $this->validate($data, [
            'questionnaire_id' => ['required', 'integer', 'exists:oltp.questionnaires,id'],
            'question_text'    => ['required', 'string'],
            'exclude_code'     => ['nullable', 'string', 'max:80'],
        ]);
    }

    private function validate(array $data, array $rules): array
    {
        $v = Validator::make($data, $rules, [
            'questionnaire_id.required' => 'Kuesioner wajib dipilih.',
            'questionnaire_id.exists'   => 'Kuesioner tidak ditemukan.',
            'question_code.required'    => 'Kode pertanyaan wajib diisi.',
            'semantic_role.required'    => 'Semantic role wajib dipilih.',
            'semantic_role.exists'      => 'Semantic role tidak dikenal.',
            'question_text.required'    => 'Teks pertanyaan wajib diisi.',
        ]);

        if ($v->fails()) throw new ValidationException($v);
        return $v->validated();
    }
}
