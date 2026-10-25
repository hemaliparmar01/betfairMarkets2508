<?php

namespace App\Http\Requests\Admin\HorseRacing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHorseRacingContentRequest extends FormRequest
{
    private array $keys = [
        'horse_racing_analysis',
        'horse_racing_consensus',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [];

        foreach ($this->keys as $key) {
            $rules[$key] = ['nullable'];
            $rules[$key . '_translations'] = ['nullable', 'array'];
        }

        return $rules;
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        foreach ($this->keys as $settingKey) {
            $data[$settingKey] = $data[$settingKey] ?? null;

            $translationKey = $settingKey . '_translations';
            $data[$translationKey] = json_encode($data[$translationKey] ?? [], JSON_UNESCAPED_UNICODE);
        }

        return $data;
    }
}
