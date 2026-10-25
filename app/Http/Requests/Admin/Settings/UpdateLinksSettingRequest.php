<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\{ValidationRule, Validator};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateLinksSettingRequest extends FormRequest
{
    public $keys = [
        'privacy_policy',
        'refund_policy',
        'terms_and_conditions',
        'disclaimer',
        'bf_markets_manifesto',
        'filter_guide',
    ];

    public function authorize(): bool {
        return true;
    }

    public function rules(): array
    {
        $rules = [];
        foreach ($this->keys as $key) {
            $rules[$key] = ['required'];
            $rules[$key . '_translations'] = ['required'];
        }
        return $rules;
    }

    public function validated($key = null, $default = null) {
        $data = parent::validated($key, $default);
        foreach ($this->keys as $settingKey) {
            $translationKey = $settingKey . '_translations';
            if (!empty($data[$translationKey])) {
                $data[$translationKey] = json_encode($data[$translationKey],JSON_UNESCAPED_UNICODE);
            }
        }
        return $data;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
