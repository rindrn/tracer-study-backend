<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class SubmitTracerStudyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            // Identitas (selalu wajib)
            'nim' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'tahun_lulus' => ['required'],
            'kdpstmsmh' => ['required', 'string'],
            'kode_pt' => ['nullable', 'string', 'max:10'],
            'nik' => ['nullable', 'string', 'max:20'],
            'npwp' => ['nullable', 'string', 'max:25'],
            'questionnaire_ids' => ['nullable', 'array'],
            'questionnaire_ids.*' => ['integer'],
        ];

        // Build dynamic rules from questionnaire questions
        $qIds = $this->input('questionnaire_ids', []);
        if (!empty($qIds)) {
            $dynamicRules = $this->buildDynamicRules($qIds);
            $rules = array_merge($rules, $dynamicRules);
        }

        return $rules;
    }

    private function buildDynamicRules(array $questionnaireIds): array
    {
        $conn = DB::connection('oltp');

        // Get all questions for these questionnaires
        $questions = $conn->table('questionnaire_questions')
            ->whereIn('questionnaire_id', $questionnaireIds)
            ->select('code', 'question_type', 'is_required', 'metadata')
            ->get();

        // Get options keyed by question code
        $options = $conn->table('questionnaire_options as o')
            ->join('questionnaire_questions as q', 'o.question_id', '=', 'q.id')
            ->whereIn('q.questionnaire_id', $questionnaireIds)
            ->select('q.code as question_code', 'o.option_code')
            ->get()
            ->groupBy('question_code');

        $rules = [];
        $seen = [];
        // Identity fields already have hardcoded rules — skip them
        $identityKeys = ['nim', 'name', 'email', 'phone', 'tahun_lulus', 'kdpstmsmh', 'kode_pt', 'nik', 'npwp',
            'nimhsmsmh', 'kdptimsmh', 'nmmhsmsmh', 'telpomsmh', 'emailmsmh', 'questionnaire_ids'];

        foreach ($questions as $q) {
            if (isset($seen[$q->code]) || in_array($q->code, $identityKeys, true)) continue;
            $seen[$q->code] = true;

            $rule = $q->is_required ? ['required'] : ['nullable'];

            switch ($q->question_type) {
                case 'single_choice':
                    $rule[] = 'string';
                    $validOptions = ($options[$q->code] ?? collect())->pluck('option_code')->toArray();
                    if (!empty($validOptions)) {
                        $rule[] = 'in:' . implode(',', $validOptions);
                    }
                    break;

                case 'multiple_choice':
                    // Can be array or string
                    $rule = $q->is_required ? ['required'] : ['nullable'];
                    break;

                case 'number':
                    $metadata = $q->metadata ? json_decode($q->metadata, true) : null;
                    $rule[] = 'numeric';
                    if ($metadata && isset($metadata['scale_min'], $metadata['scale_max'])) {
                        $rule[] = "between:{$metadata['scale_min']},{$metadata['scale_max']}";
                    }
                    break;

                case 'boolean':
                    $rule[] = 'in:0,1,true,false';
                    break;

                case 'short_text':
                    $rule[] = 'string';
                    $rule[] = 'max:500';
                    break;

                case 'long_text':
                    $rule[] = 'string';
                    $rule[] = 'max:5000';
                    break;

                case 'date':
                    $rule[] = 'date';
                    break;

                default:
                    $rule[] = 'string';
                    break;
            }

            $rules[$q->code] = $rule;
        }

        return $rules;
    }

    /**
     * Return all input so dynamic question codes pass through to the service.
     */
    public function validated($key = null, $default = null)
    {
        if ($key) {
            return data_get($this->all(), $key, $default);
        }
        return $this->all();
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        \Log::warning('[TracerStudy] Validation failed', [
            'errors' => $validator->errors()->toArray(),
            'input_keys' => array_keys($this->all()),
        ]);

        parent::failedValidation($validator);
    }
}
